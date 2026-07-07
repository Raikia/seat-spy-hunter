<?php

namespace Raikia\SeatSpyHunter\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatSpyHunter\Models\EveWhoMember;
use Raikia\SeatSpyHunter\Models\EveWhoQueue;
use Raikia\SeatSpyHunter\Models\IntelEntity;
use Seat\Eveapi\Bus\Character as CharacterBus;

class EveWhoService
{
    public function queueConfiguredHostiles(): void
    {
        IntelEntity::query()
            ->where('category', IntelEntity::CATEGORY_HOSTILE)
            ->whereIn('entity_type', ['corporation', 'alliance'])
            ->get()
            ->each(function (IntelEntity $entity) {
                EveWhoQueue::query()->firstOrCreate([
                    'entity_type' => $entity->entity_type,
                    'entity_id' => (int) $entity->entity_id,
                    'page' => 1,
                ], [
                    'status' => 'pending',
                    'available_at' => now(),
                ]);
            });
    }

    public function processQueue(int $limit = 10): int
    {
        $processed = 0;
        $rows = EveWhoQueue::query()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('available_at')
                    ->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $payload = $this->fetchListPage($row->entity_type, (int) $row->entity_id, (int) $row->page);

            if (!$payload) {
                $row->update([
                    'attempts' => $row->attempts + 1,
                    'available_at' => now()->addMinutes(30),
                    'last_error' => 'EveWho request failed or returned no usable response.',
                ]);
                continue;
            }

            $characters = $this->charactersFromListPayload($payload);
            foreach ($characters as $character) {
                $member = $this->cacheCurrentMember($character, $row->entity_type, (int) $row->entity_id);

                if ($member) {
                    $this->queueMemberEsi($member);
                }
            }

            $pagination = data_get($payload, 'pagination', []);
            if (data_get($pagination, 'has_next') && data_get($pagination, 'page')) {
                EveWhoQueue::query()->firstOrCreate([
                    'entity_type' => $row->entity_type,
                    'entity_id' => (int) $row->entity_id,
                    'page' => ((int) data_get($pagination, 'page')) + 1,
                ], [
                    'status' => 'pending',
                    'available_at' => now()->addSeconds(4),
                ]);
            }

            $row->update([
                'status' => 'complete',
                'processed_at' => now(),
                'last_error' => null,
            ]);
            $processed++;
        }

        return $processed;
    }

    public function hostileEmploymentOverlaps(Collection $characterIds): Collection
    {
        if ($characterIds->isEmpty()) {
            return collect();
        }

        $localHistories = $this->localEmploymentHistories($characterIds);
        if ($localHistories->isEmpty()) {
            return collect();
        }

        $corporationIds = $localHistories->pluck('corporation_id')->filter()->unique()->values();
        if ($corporationIds->isEmpty()) {
            return collect();
        }

        $hostileCharacterIds = EveWhoMember::query()
            ->whereNotIn('character_id', $characterIds->all())
            ->pluck('character_id')
            ->filter()
            ->unique()
            ->values();

        if ($hostileCharacterIds->isEmpty()) {
            return collect();
        }

        $hostileMembers = EveWhoMember::query()
            ->whereIn('character_id', $hostileCharacterIds->all())
            ->get()
            ->groupBy('character_id');

        $hostileHistories = $this->employmentHistories($hostileCharacterIds, $corporationIds);
        if ($hostileHistories->isEmpty()) {
            return collect();
        }

        $characterNames = $this->characterNames($characterIds->merge($hostileCharacterIds)->unique()->values());

        return $localHistories->flatMap(function ($local) use ($hostileHistories, $hostileMembers, $characterNames) {
            return $hostileHistories
                ->where('corporation_id', (int) $local->corporation_id)
                ->map(function ($hostile) use ($local, $hostileMembers, $characterNames) {
                    $member = $hostileMembers->get((int) $hostile->character_id, collect())->first();
                    $sameTime = $this->dateRangesOverlap($local->start_date, $local->end_date, $hostile->start_date, $hostile->end_date);

                    return [
                        'character_id' => (int) $local->character_id,
                        'character_name' => $characterNames->get((int) $local->character_id),
                        'corporation_id' => (int) $local->corporation_id,
                        'corporation_name' => $hostile->corporation_name,
                        'hostile_character_id' => (int) $hostile->character_id,
                        'hostile_character_name' => $member && $member->character_name ? $member->character_name : $characterNames->get((int) $hostile->character_id),
                        'same_time' => $sameTime,
                        'local_start_date' => $this->dateString($local->start_date),
                        'local_end_date' => $this->dateString($local->end_date),
                        'hostile_start_date' => $this->dateString($hostile->start_date),
                        'hostile_end_date' => $this->dateString($hostile->end_date),
                        'source_entity_type' => $member ? $member->source_entity_type : null,
                        'source_entity_id' => $member ? $member->source_entity_id : null,
                    ];
                });
        })->values();
    }

    private function fetchListPage(string $entityType, int $entityId, int $page): ?array
    {
        $path = $entityType === 'alliance' ? 'allilist' : 'corplist';
        $url = sprintf('https://evewho.com/api/%s/%d%s', $path, $entityId, $page > 1 ? '/page/' . $page : '');

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'seat-spy-hunter/1.0'])
                ->get($url);
        } catch (\Throwable $exception) {
            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    private function cacheCurrentMember($character, string $sourceEntityType, int $sourceEntityId): ?EveWhoMember
    {
        $characterId = (int) data_get($character, 'character_id', data_get($character, 'characterID', data_get($character, 'id')));

        if (!$characterId) {
            return null;
        }

        return EveWhoMember::query()->updateOrCreate([
            'character_id' => $characterId,
            'source_entity_type' => $sourceEntityType,
            'source_entity_id' => $sourceEntityId,
        ], [
            'character_name' => data_get($character, 'name', data_get($character, 'character_name', data_get($character, 'characterName'))),
            'corporation_id' => $this->optionalInt(data_get($character, 'corporation_id', data_get($character, 'corporationID', data_get($character, 'corporation.id')))),
            'corporation_name' => data_get($character, 'corporation_name', data_get($character, 'corporationName', data_get($character, 'corporation.name'))),
            'alliance_id' => $this->optionalInt(data_get($character, 'alliance_id', data_get($character, 'allianceID', data_get($character, 'alliance.id')))),
            'alliance_name' => data_get($character, 'alliance_name', data_get($character, 'allianceName', data_get($character, 'alliance.name'))),
            'raw' => $character,
            'last_seen_at' => now(),
        ]);
    }

    private function queueMemberEsi(EveWhoMember $member): void
    {
        if ($member->esi_queued_at && $member->esi_queued_at->gt(now()->subDays(7))) {
            return;
        }

        try {
            (new CharacterBus((int) $member->character_id))->fire();
            $member->forceFill(['esi_queued_at' => now()])->save();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function charactersFromListPayload(array $payload): Collection
    {
        return collect(data_get($payload, 'characters', data_get($payload, 'members', data_get($payload, 'data', []))))
            ->filter(fn ($row) => data_get($row, 'character_id') || data_get($row, 'characterID') || data_get($row, 'id'));
    }

    private function localEmploymentHistories(Collection $characterIds): Collection
    {
        return $this->employmentHistories($characterIds);
    }

    private function employmentHistories(Collection $characterIds, ?Collection $corporationIds = null): Collection
    {
        if (!Schema::hasTable('character_corporation_histories')) {
            return collect();
        }

        $query = DB::table('character_corporation_histories')
            ->whereIn('character_id', $characterIds->all())
            ->where('is_deleted', false)
            ->orderBy('character_id')
            ->orderByDesc('start_date');

        $rows = $query->get(['character_id', 'corporation_id', 'start_date']);
        $corporationNames = $this->corporationNames($rows->pluck('corporation_id')->unique()->values());

        $histories = $rows
            ->groupBy('character_id')
            ->flatMap(function ($histories) {
                return $histories->values()->map(function ($row, int $index) use ($histories) {
                    $previous = $histories->values()->get($index - 1);
                    $row->end_date = $previous ? $previous->start_date : null;

                    return $row;
                });
            })
            ->map(function ($row) use ($corporationNames) {
                $row->corporation_name = $corporationNames->get((int) $row->corporation_id);

                return $row;
            })
            ->values();

        if ($corporationIds && $corporationIds->isNotEmpty()) {
            return $histories
                ->whereIn('corporation_id', $corporationIds->all())
                ->values();
        }

        return $histories;
    }

    private function corporationNames(Collection $corporationIds): Collection
    {
        if ($corporationIds->isEmpty() || !Schema::hasTable('corporation_infos')) {
            return collect();
        }

        return DB::table('corporation_infos')
            ->whereIn('corporation_id', $corporationIds->all())
            ->pluck('name', 'corporation_id');
    }

    private function characterNames(Collection $characterIds): Collection
    {
        if ($characterIds->isEmpty() || !Schema::hasTable('character_infos')) {
            return collect();
        }

        return DB::table('character_infos')
            ->whereIn('character_id', $characterIds->all())
            ->pluck('name', 'character_id');
    }

    private function optionalInt($value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function dateRangesOverlap($leftStart, $leftEnd, $rightStart, $rightEnd): bool
    {
        $leftStart = $leftStart ? strtotime((string) $leftStart) : null;
        $leftEnd = $leftEnd ? strtotime((string) $leftEnd) : PHP_INT_MAX;
        $rightStart = $rightStart ? strtotime((string) $rightStart) : null;
        $rightEnd = $rightEnd ? strtotime((string) $rightEnd) : PHP_INT_MAX;

        if (!$leftStart || !$rightStart) {
            return false;
        }

        return $leftStart <= $rightEnd && $rightStart <= $leftEnd;
    }

    private function dateString($value): ?string
    {
        if (!$value) {
            return null;
        }

        return method_exists($value, 'toDateTimeString') ? $value->toDateTimeString() : (string) $value;
    }
}
