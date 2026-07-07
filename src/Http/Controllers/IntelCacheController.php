<?php

namespace Raikia\SeatSpyHunter\Http\Controllers;

use Illuminate\Http\Request;
use Raikia\SeatSpyHunter\Models\EveWhoMember;
use Raikia\SeatSpyHunter\Models\EveWhoQueue;
use Raikia\SeatSpyHunter\Models\IpIntelligence;
use Raikia\SeatSpyHunter\Models\VpnLookupQueue;
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

        $summary = [
            'ip_count' => IpIntelligence::query()->count(),
            'evewho_member_count' => EveWhoMember::query()->count(),
            'vpn_pending' => VpnLookupQueue::query()->where('status', 'pending')->count(),
            'evewho_pending' => EveWhoQueue::query()->where('status', 'pending')->count(),
        ];

        return view('seat-spy-hunter::caches', compact('ipRecords', 'eveWhoMembers', 'summary', 'ipSearch', 'eveWhoSearch'));
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

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
