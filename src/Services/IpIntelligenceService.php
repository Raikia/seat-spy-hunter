<?php

namespace Raikia\SeatSpyHunter\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
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
            $this->queueMissingIps($missingIps);
        }

        return $records
            ->filter(function (IpIntelligence $record) {
                return $record->isSuspicious();
            })
            ->keyBy('ip');
    }

    public function processQueue(int $limit = 1000): int
    {
        if (!$this->isVpnApiEnabled()) {
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

    private function queueMissingIps(Collection $ips): void
    {
        if (!$this->isVpnApiConfigured()) {
            return;
        }

        foreach ($ips as $ip) {
            VpnLookupQueue::query()->firstOrCreate([
                'ip' => $ip,
            ], [
                'status' => 'pending',
                'available_at' => now(),
            ]);
        }
    }

    private function isVpnApiEnabled(): bool
    {
        return $this->isVpnApiConfigured()
            && !$this->isVpnApiRateLimited();
    }

    private function isVpnApiConfigured(): bool
    {
        return in_array(strtolower((string) $this->settings->ipProvider()), ['vpnapi', 'vpnapi.io'], true)
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
