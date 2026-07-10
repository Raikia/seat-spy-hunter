<?php

namespace Raikia\SeatSpyHunter\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatSpyHunter\Models\IpIntelligence;
use Raikia\SeatSpyHunter\Models\VpnLookupQueue;

class IpIntelligenceService
{
    private $settings;

    public function __construct(IntelSettings $settings)
    {
        $this->settings = $settings;
    }

    public function suspiciousForIps(Collection $ips): Collection
    {
        if ($ips->isEmpty()) {
            return collect();
        }

        $ips = $ips->values()->unique()->values();
        $records = IpIntelligence::query()
            ->whereIn('ip', $ips->all())
            ->get()
            ->keyBy('ip');

        $missingIps = $ips->reject(fn ($ip) => $records->has($ip))->values();
        if ($missingIps->isNotEmpty()) {
            $this->queuePublicIps($missingIps);
        }

        return $records
            ->filter(function (IpIntelligence $record) {
                return $record->isSuspicious();
            })
            ->keyBy('ip');
    }

    public function processQueue(int $limit = 1000): int
    {
        if (!$this->isVpnApiConfigured()) {
            return 0;
        }

        $this->queueKnownLoginIps(max($limit, 1000));

        if ($this->isVpnApiRateLimited()) {
            return 0;
        }

        $processed = 0;
        $rows = VpnLookupQueue::query()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('available_at')
                    ->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            if ($this->isVpnApiRateLimited()) {
                break;
            }

            $record = $this->lookupVpnApi($row->ip);

            if ($record) {
                $row->update([
                    'status' => 'complete',
                    'looked_up_at' => now(),
                    'last_error' => null,
                ]);
                $processed++;
                continue;
            }

            $row->update([
                'attempts' => $row->attempts + 1,
                'available_at' => now()->addHours(6),
                'last_error' => $this->isVpnApiRateLimited() ? 'VPNAPI.io rate limit reached; paused until next UTC day.' : 'Lookup failed or returned no usable response.',
            ]);
        }

        return $processed;
    }

    public function queueKnownLoginIps(?int $limit = 5000): int
    {
        if (!$this->isVpnApiConfigured() || !Schema::hasTable('user_login_histories')) {
            return 0;
        }

        $query = DB::table('user_login_histories')
            ->whereNotNull('source')
            ->where('source', '<>', '')
            ->select('source')
            ->groupBy('source')
            ->orderBy('source');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $ips = $query->pluck('source');

        return $this->queuePublicIps($ips);
    }

    public function loginIpQueueStats(): array
    {
        if (!Schema::hasTable('user_login_histories')) {
            return [
                'configured' => $this->isVpnApiConfigured(),
                'has_login_history_table' => false,
                'total' => 0,
                'valid' => 0,
                'public' => 0,
                'private_or_reserved' => 0,
                'invalid' => 0,
                'cached' => 0,
                'queued' => 0,
                'queueable' => 0,
            ];
        }

        $ips = DB::table('user_login_histories')
            ->whereNotNull('source')
            ->where('source', '<>', '')
            ->select('source')
            ->groupBy('source')
            ->orderBy('source')
            ->pluck('source')
            ->map(fn ($ip) => trim((string) $ip))
            ->filter()
            ->unique()
            ->values();
        $validIps = $ips->filter(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false)->values();
        $publicIps = $validIps->filter(fn ($ip) => $this->isPublicIp($ip))->values();
        $cachedIps = $publicIps->isEmpty()
            ? collect()
            : IpIntelligence::query()->whereIn('ip', $publicIps->all())->pluck('ip')->flip();
        $queuedIps = $publicIps->isEmpty()
            ? collect()
            : VpnLookupQueue::query()->whereIn('ip', $publicIps->all())->pluck('ip')->flip();

        return [
            'configured' => $this->isVpnApiConfigured(),
            'has_login_history_table' => true,
            'total' => $ips->count(),
            'valid' => $validIps->count(),
            'public' => $publicIps->count(),
            'private_or_reserved' => $validIps->count() - $publicIps->count(),
            'invalid' => $ips->count() - $validIps->count(),
            'cached' => $cachedIps->count(),
            'queued' => $queuedIps->count(),
            'queueable' => $publicIps->reject(fn ($ip) => $cachedIps->has($ip) || $queuedIps->has($ip))->count(),
        ];
    }

    public function queuePublicIps(Collection $ips): int
    {
        if (!$this->isVpnApiConfigured()) {
            return 0;
        }

        $ips = $ips
            ->map(fn ($ip) => trim((string) $ip))
            ->filter(fn ($ip) => $ip !== '' && $this->isPublicIp($ip))
            ->unique()
            ->values();

        if ($ips->isEmpty()) {
            return 0;
        }

        $cachedIps = IpIntelligence::query()
            ->whereIn('ip', $ips->all())
            ->pluck('ip')
            ->flip();
        $queuedIps = VpnLookupQueue::query()
            ->whereIn('ip', $ips->all())
            ->pluck('ip')
            ->flip();
        $queued = 0;

        foreach ($ips as $ip) {
            if ($cachedIps->has($ip) || $queuedIps->has($ip)) {
                continue;
            }

            VpnLookupQueue::query()->create([
                'ip' => $ip,
                'status' => 'pending',
                'available_at' => now(),
            ]);
            $queued++;
        }

        return $queued;
    }

    private function isVpnApiEnabled(): bool
    {
        return $this->isVpnApiConfigured()
            && !$this->isVpnApiRateLimited();
    }

    private function isVpnApiConfigured(): bool
    {
        $provider = strtolower((string) ($this->settings->ipProvider() ?: 'vpnapi.io'));

        return in_array($provider, ['vpnapi', 'vpnapi.io'], true)
            && filled($this->settings->ipProviderKey());
    }

    private function isVpnApiRateLimited(): bool
    {
        $limitedUntil = $this->settings->ipProviderLimitedUntil();

        return $limitedUntil && $limitedUntil->isFuture();
    }

    private function lookupVpnApi(string $ip): ?IpIntelligence
    {
        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get(sprintf('https://vpnapi.io/api/%s', $ip), [
                    'key' => $this->settings->ipProviderKey(),
                ]);
        } catch (\Throwable $exception) {
            return null;
        }

        if ($response->status() === 429) {
            $this->settings->markIpProviderLimitedUntil(now('UTC')->addDay()->startOfDay());

            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return null;
        }

        $security = data_get($payload, 'security', []);
        $isVpn = (bool) data_get($security, 'vpn', false);
        $isProxy = (bool) data_get($security, 'proxy', false);
        $isTor = (bool) data_get($security, 'tor', false);
        $isRelay = (bool) data_get($security, 'relay', false);
        $riskScore = $this->vpnApiRiskScore($isVpn, $isProxy, $isTor, $isRelay);

        return IpIntelligence::query()
            ->updateOrCreate([
                'ip' => data_get($payload, 'ip') ?: $ip,
            ], [
                'is_vpn' => $isVpn,
                'is_proxy' => $isProxy,
                'is_tor' => $isTor,
                'is_hosting' => false,
                'risk_score' => $riskScore,
                'provider' => 'vpnapi.io',
                'raw' => $payload,
                'checked_at' => now(),
            ]);
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function vpnApiRiskScore(bool $isVpn, bool $isProxy, bool $isTor, bool $isRelay): int
    {
        if ($isTor) {
            return 95;
        }

        if ($isVpn || $isProxy) {
            return 90;
        }

        if ($isRelay) {
            return 70;
        }

        return 0;
    }
}
