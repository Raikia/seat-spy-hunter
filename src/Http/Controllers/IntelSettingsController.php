<?php

namespace Raikia\SeatSpyHunter\Http\Controllers;

use Illuminate\Http\Request;
use Raikia\SeatSpyHunter\Models\IgnoredCharacter;
use Raikia\SeatSpyHunter\Models\IntelEntity;
use Raikia\SeatSpyHunter\Models\IpIntelligence;
use Raikia\SeatSpyHunter\Models\EveWhoQueue;
use Raikia\SeatSpyHunter\Models\VpnLookupQueue;
use Raikia\SeatSpyHunter\Services\IntelSettings;
use Seat\Eveapi\Models\Alliances\Alliance;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Eveapi\Models\Universe\UniverseName;
use Seat\Web\Http\Controllers\Controller;

class IntelSettingsController extends Controller
{
    public function index(IntelSettings $settings)
    {
        $monitoredEntities = IntelEntity::query()
            ->where('category', IntelEntity::CATEGORY_MONITORED)
            ->orderBy('entity_type')
            ->orderBy('name')
            ->get();

        $hostileEntities = IntelEntity::query()
            ->where('category', IntelEntity::CATEGORY_HOSTILE)
            ->orderBy('entity_type')
            ->orderBy('name')
            ->get();

        $ignoredCharacters = IgnoredCharacter::query()
            ->orderBy('name')
            ->get();

        $ipRecords = IpIntelligence::query()
            ->orderByDesc('risk_score')
            ->orderBy('ip')
            ->limit(100)
            ->get();
        $vpnQueueSummary = VpnLookupQueue::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
        $eveWhoQueueSummary = EveWhoQueue::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return view('seat-spy-hunter::settings', compact('settings', 'monitoredEntities', 'hostileEntities', 'ignoredCharacters', 'ipRecords', 'vpnQueueSummary', 'eveWhoQueueSummary'));
    }

    public function updateGeneral(Request $request, IntelSettings $settings)
    {
        $data = $request->validate([
            'low_skillpoint_threshold' => 'required|integer|min:0|max:500000000',
            'new_character_days' => 'required|integer|min:1|max:3650',
            'shared_ip_score' => 'required|integer|min:0|max:100',
            'hostile_interaction_score' => 'required|integer|min:0|max:100',
            'vpn_score' => 'required|integer|min:0|max:100',
            'ip_provider' => 'nullable|string|max:100',
            'ip_provider_key' => 'nullable|string|max:255',
        ]);

        $settings->setLowSkillpointThreshold((int) $data['low_skillpoint_threshold']);
        $settings->setNewCharacterDays((int) $data['new_character_days']);
        $settings->setSharedIpScore((int) $data['shared_ip_score']);
        $settings->setHostileInteractionScore((int) $data['hostile_interaction_score']);
        $settings->setVpnScore((int) $data['vpn_score']);
        $settings->setIpProvider($data['ip_provider'] ?? null);
        $settings->setIpProviderKey($data['ip_provider_key'] ?? null);

        return redirect()->route('seat-spy-hunter.settings')->with('success', 'Spy Hunter settings updated successfully.');
    }

    public function storeEntity(Request $request)
    {
        $data = $request->validate([
            'entity_id' => 'required|integer|min:1',
            'entity_type' => 'required|in:character,corporation,alliance',
            'category' => 'required|in:monitored,hostile',
            'name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($data['category'] === IntelEntity::CATEGORY_MONITORED && $data['entity_type'] === 'character') {
            return redirect()->route('seat-spy-hunter.settings')->with('error', 'Monitored entities must be corporations or alliances.');
        }

        $name = $data['name'] ?: optional(UniverseName::find((int) $data['entity_id']))->name;

        IntelEntity::updateOrCreate([
            'entity_id' => (int) $data['entity_id'],
            'category' => $data['category'],
        ], [
            'entity_type' => $data['entity_type'],
            'name' => $name,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('seat-spy-hunter.settings')->with('success', 'Spy Hunter entity saved successfully.');
    }

    public function searchCorporations(Request $request)
    {
        $query = trim((string) $request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $escapedQuery = $this->escapeLike($query);

        $results = CorporationInfo::player()
            ->where('name', 'like', '%' . $escapedQuery . '%')
            ->orderBy('name')
            ->limit(100)
            ->get(['corporation_id', 'name', 'ticker', 'alliance_id'])
            ->sort(function ($left, $right) use ($query) {
                return $this->compareSearchLabels($left->name, $right->name, $query);
            })
            ->take(20)
            ->map(function ($corporation) {
                $suffix = $corporation->ticker ? sprintf(' [%s]', $corporation->ticker) : '';

                return [
                    'id' => $corporation->corporation_id,
                    'text' => $corporation->name . $suffix,
                    'name' => $corporation->name,
                    'type' => 'corporation',
                ];
            })
            ->values();

        return response()->json(['results' => $results]);
    }

    public function searchAlliances(Request $request)
    {
        $query = trim((string) $request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $escapedQuery = $this->escapeLike($query);

        $results = Alliance::query()
            ->where('name', 'like', '%' . $escapedQuery . '%')
            ->orderBy('name')
            ->limit(100)
            ->get(['alliance_id', 'name', 'ticker'])
            ->sort(function ($left, $right) use ($query) {
                return $this->compareSearchLabels($left->name, $right->name, $query);
            })
            ->take(20)
            ->map(function ($alliance) {
                $suffix = $alliance->ticker ? sprintf(' [%s]', $alliance->ticker) : '';

                return [
                    'id' => $alliance->alliance_id,
                    'text' => $alliance->name . $suffix,
                    'name' => $alliance->name,
                    'type' => 'alliance',
                ];
            })
            ->values();

        return response()->json(['results' => $results]);
    }

    public function searchEntities(Request $request)
    {
        $type = $request->input('type');

        if ($type === 'corporation') {
            return $this->searchCorporations($request);
        }

        if ($type === 'alliance') {
            return $this->searchAlliances($request);
        }

        $query = trim((string) $request->input('q', ''));

        if ($type !== 'character' || strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $escapedQuery = $this->escapeLike($query);

        $results = CharacterInfo::player()
            ->where('name', 'like', '%' . $escapedQuery . '%')
            ->with('affiliation.corporation', 'affiliation.alliance')
            ->orderBy('name')
            ->limit(100)
            ->get(['character_id', 'name'])
            ->sort(function ($left, $right) use ($query) {
                return $this->compareSearchLabels($left->name, $right->name, $query);
            })
            ->take(20)
            ->map(function ($character) {
                $affiliation = collect([
                    optional(optional($character->affiliation)->corporation)->name,
                    optional(optional($character->affiliation)->alliance)->name,
                ])
                    ->filter()
                    ->implode(' / ');

                return [
                    'id' => $character->character_id,
                    'text' => $affiliation ? $character->name . ' - ' . $affiliation : $character->name,
                    'name' => $character->name,
                    'type' => 'character',
                ];
            })
            ->values();

        return response()->json(['results' => $results]);
    }

    public function destroyEntity(IntelEntity $entity)
    {
        $entity->delete();

        return redirect()->route('seat-spy-hunter.settings')->with('success', 'Spy Hunter entity removed successfully.');
    }

    public function storeIgnoredCharacter(Request $request)
    {
        $data = $request->validate([
            'character_id' => 'required|integer|min:1',
            'name' => 'nullable|string|max:255',
            'reason' => 'nullable|string',
        ]);

        $character = CharacterInfo::find((int) $data['character_id']);

        IgnoredCharacter::updateOrCreate([
            'character_id' => (int) $data['character_id'],
        ], [
            'name' => $data['name'] ?: optional($character)->name,
            'reason' => $data['reason'] ?? null,
        ]);

        return redirect()->route('seat-spy-hunter.settings')->with('success', 'Ignored character saved successfully.');
    }

    public function destroyIgnoredCharacter(IgnoredCharacter $character)
    {
        $character->delete();

        return redirect()->route('seat-spy-hunter.settings')->with('success', 'Ignored character removed successfully.');
    }

    public function storeIpIntelligence(Request $request)
    {
        $data = $request->validate([
            'ip' => 'required|ip',
            'is_vpn' => 'nullable|boolean',
            'is_proxy' => 'nullable|boolean',
            'is_tor' => 'nullable|boolean',
            'is_hosting' => 'nullable|boolean',
            'risk_score' => 'required|integer|min:0|max:100',
            'provider' => 'nullable|string|max:100',
        ]);

        IpIntelligence::updateOrCreate([
            'ip' => $data['ip'],
        ], [
            'is_vpn' => (bool) ($data['is_vpn'] ?? false),
            'is_proxy' => (bool) ($data['is_proxy'] ?? false),
            'is_tor' => (bool) ($data['is_tor'] ?? false),
            'is_hosting' => (bool) ($data['is_hosting'] ?? false),
            'risk_score' => (int) $data['risk_score'],
            'provider' => $data['provider'] ?? 'manual',
            'checked_at' => now(),
        ]);

        return redirect()->route('seat-spy-hunter.settings')->with('success', 'IP intelligence record saved successfully.');
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function compareSearchLabels(string $left, string $right, string $query): int
    {
        $leftStartsWith = str_starts_with(strtolower($left), strtolower($query));
        $rightStartsWith = str_starts_with(strtolower($right), strtolower($query));

        if ($leftStartsWith !== $rightStartsWith) {
            return $leftStartsWith ? -1 : 1;
        }

        return strcasecmp($left, $right);
    }
}
