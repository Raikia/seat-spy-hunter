<?php

namespace Raikia\SeatSpyHunter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatSpyHunter\Models\EveWhoMember;
use Raikia\SeatSpyHunter\Models\EveWhoQueue;
use Raikia\SeatSpyHunter\Jobs\ProcessVpnLookupQueueJob;
use Raikia\SeatSpyHunter\Jobs\RefreshEveWhoMemberEsiJob;
use Raikia\SeatSpyHunter\Models\IntelEntity;
use Raikia\SeatSpyHunter\Models\IpIntelligence;
use Raikia\SeatSpyHunter\Models\VpnLookupQueue;
use Raikia\SeatSpyHunter\Services\IpIntelligenceService;
use Seat\Web\Http\Controllers\Controller;

class IntelCacheController extends Controller
{
    public function index(Request $request)
    {
        $ipSearch = $request->get('ip_search');
        $eveWhoSearch = $request->get('evewho_search');

        $ipRecords = IpIntelligence::query()
            ->when($ipSearch, function ($query) use ($ipSearch) {
                $query->where('ip', 'like', '%' . $this->escapeLike($ipSearch) . '%')
                    ->orWhere('provider', 'like', '%' . $this->escapeLike($ipSearch) . '%');
            })
            ->orderByDesc('checked_at')
            ->orderBy('ip')
            ->paginate(50, ['*'], 'ip_page')
            ->appends($request->except('ip_page'));

        $eveWhoMembers = EveWhoMember::query()
            ->when($eveWhoSearch, function ($query) use ($eveWhoSearch) {
                $escaped = '%' . $this->escapeLike($eveWhoSearch) . '%';

                $query->where('character_name', 'like', $escaped)
                    ->orWhere('corporation_name', 'like', $escaped)
                    ->orWhere('alliance_name', 'like', $escaped)
                    ->orWhere('character_id', $eveWhoSearch)
                    ->orWhere('corporation_id', $eveWhoSearch)
                    ->orWhere('alliance_id', $eveWhoSearch)
                    ->orWhere('source_entity_id', $eveWhoSearch);
            })
            ->orderBy('character_name')
            ->orderByDesc('last_seen_at')
            ->paginate(50, ['*'], 'evewho_page')
            ->appends($request->except('evewho_page'));
        $eveWhoMemberAffiliations = $this->currentAffiliationsFor($eveWhoMembers->getCollection()->pluck('character_id')->filter()->values());
        $sourceEntityNames = $this->sourceEntityNames($eveWhoMembers->getCollection());

        $summary = [
            'ip_count' => IpIntelligence::query()->count(),
            'evewho_member_count' => EveWhoMember::query()->count(),
            'vpn_pending' => VpnLookupQueue::query()->where('status', 'pending')->count(),
            'evewho_pending' => EveWhoQueue::query()->where('status', 'pending')->count(),
        ];

        return view('seat-spy-hunter::caches', compact('ipRecords', 'eveWhoMembers', 'eveWhoMemberAffiliations', 'sourceEntityNames', 'summary', 'ipSearch', 'eveWhoSearch'));
    }

    public function destroyIp(IpIntelligence $record)
    {
        $ip = $record->ip;
        $record->delete();

        VpnLookupQueue::query()->updateOrCreate([
            'ip' => $ip,
        ], [
            'status' => 'pending',
            'available_at' => now(),
            'looked_up_at' => null,
            'last_error' => null,
        ]);

        return redirect()->route('seat-spy-hunter.caches')->with('success', 'IP cache entry deleted and queued for future lookup.');
    }

    public function processVpnQueue()
    {
        ProcessVpnLookupQueueJob::dispatch();

        return redirect()->route('seat-spy-hunter.caches')->with('success', 'VPN lookup queue job dispatched. The worker will process pending VPNAPI.io lookups in the background.');
    }

    public function queueLoginIps(IpIntelligenceService $ipIntelligence)
    {
        $queued = $ipIntelligence->queueKnownLoginIps(null);

        return redirect()->route('seat-spy-hunter.caches')->with('success', sprintf('Queued %d uncached public login IP%s for VPNAPI.io lookup.', $queued, $queued === 1 ? '' : 's'));
    }

    public function destroyEveWhoMember(EveWhoMember $member)
    {
        $sourceEntityType = $member->source_entity_type;
        $sourceEntityId = $member->source_entity_id;
        $member->delete();

        if ($sourceEntityType && $sourceEntityId) {
            EveWhoQueue::query()->updateOrCreate([
                'entity_type' => $sourceEntityType,
                'entity_id' => (int) $sourceEntityId,
                'page' => 1,
            ], [
                'status' => 'pending',
                'available_at' => now(),
                'processed_at' => null,
                'last_error' => null,
            ]);
        }

        return redirect()->route('seat-spy-hunter.caches')->with('success', 'EveWho cache entry deleted and source entity queued for future sync.');
    }

    public function refreshEveWhoMemberEsi()
    {
        RefreshEveWhoMemberEsiJob::dispatch(true);

        return redirect()->route('seat-spy-hunter.caches')->with('success', 'Monthly EveWho member ESI refresh queued in batches. The worker will process a small group, pause, then continue in the background.');
    }

    public function destroyEveWhoCache()
    {
        $deleted = EveWhoMember::query()->delete();

        IntelEntity::query()
            ->where('category', IntelEntity::CATEGORY_HOSTILE)
            ->whereIn('entity_type', ['corporation', 'alliance'])
            ->get()
            ->each(function (IntelEntity $entity) {
                EveWhoQueue::query()->updateOrCreate([
                    'entity_type' => $entity->entity_type,
                    'entity_id' => (int) $entity->entity_id,
                    'page' => 1,
                ], [
                    'status' => 'pending',
                    'available_at' => now(),
                    'processed_at' => null,
                    'last_error' => null,
                ]);
            });

        return redirect()->route('seat-spy-hunter.caches')->with('success', $deleted . ' EveWho cache entr' . ($deleted === 1 ? 'y' : 'ies') . ' deleted. Configured hostile groups were queued for re-sync.');
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function currentAffiliationsFor(Collection $characterIds): Collection
    {
        if ($characterIds->isEmpty() || !Schema::hasTable('character_affiliations')) {
            return collect();
        }

        $rows = DB::table('character_affiliations')
            ->whereIn('character_id', $characterIds->map(fn ($id) => (int) $id)->all())
            ->get(['character_id', 'corporation_id', 'alliance_id']);
        $corporationNames = $this->corporationNames($rows->pluck('corporation_id')->filter()->unique()->values());
        $allianceNames = $this->allianceNames($rows->pluck('alliance_id')->filter()->unique()->values());

        return $rows->mapWithKeys(fn ($row) => [
            (int) $row->character_id => [
                'corporation_id' => $row->corporation_id ? (int) $row->corporation_id : null,
                'corporation_name' => $corporationNames->get((int) $row->corporation_id),
                'alliance_id' => $row->alliance_id ? (int) $row->alliance_id : null,
                'alliance_name' => $allianceNames->get((int) $row->alliance_id),
            ],
        ]);
    }

    private function sourceEntityNames(Collection $members): Collection
    {
        $entities = IntelEntity::query()
            ->whereIn('category', [IntelEntity::CATEGORY_HOSTILE, IntelEntity::CATEGORY_MONITORED])
            ->get()
            ->mapWithKeys(fn (IntelEntity $entity) => [$entity->entity_type . ':' . (int) $entity->entity_id => $entity->name]);
        $corporationIds = $members
            ->where('source_entity_type', 'corporation')
            ->pluck('source_entity_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $allianceIds = $members
            ->where('source_entity_type', 'alliance')
            ->pluck('source_entity_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $this->corporationNames($corporationIds)->each(fn ($name, $id) => $entities->put('corporation:' . (int) $id, $name));
        $this->allianceNames($allianceIds)->each(fn ($name, $id) => $entities->put('alliance:' . (int) $id, $name));

        return $entities;
    }

    private function corporationNames(Collection $corporationIds): Collection
    {
        if ($corporationIds->isEmpty() || !Schema::hasTable('corporation_infos')) {
            return collect();
        }

        return DB::table('corporation_infos')
            ->whereIn('corporation_id', $corporationIds->map(fn ($id) => (int) $id)->all())
            ->pluck('name', 'corporation_id');
    }

    private function allianceNames(Collection $allianceIds): Collection
    {
        if ($allianceIds->isEmpty() || !Schema::hasTable('alliances')) {
            return collect();
        }

        return DB::table('alliances')
            ->whereIn('alliance_id', $allianceIds->map(fn ($id) => (int) $id)->all())
            ->pluck('name', 'alliance_id');
    }
}
