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
    private const PLAYER_CORPORATION_ID_FLOOR = 90000000;

    private const STARTER_NPC_CORPORATION_IDS = [
        1000166, // Imperial Academy
        1000167, // State War Academy
        1000168, // Federal Navy Academy
        1000170, // Republic Military School
        1000045, // Science and Trade Institute
    ];

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

    public function queueCachedMemberEsiRefresh(bool $force = false, int $afterId = 0, int $limit = 25): array
    {
        $queued = 0;
        $lastId = null;
        $limit = max(1, min($limit, 100));

        $members = EveWhoMember::query()
            ->whereNotNull('character_id')
            ->where('id', '>', $afterId)
            ->when(!$force, function ($query) {
                $query->where(function ($inner) {
                    $inner->whereNull('esi_queued_at')
                        ->orWhere('esi_queued_at', '<=', now()->subDays(30));
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($members as $member) {
            $lastId = (int) $member->id;

            if ($this->queueMemberEsi($member, $force)) {
                $queued++;
            }
        }

        $hasMore = $lastId !== null && EveWhoMember::query()
            ->whereNotNull('character_id')
            ->where('id', '>', $lastId)
            ->when(!$force, function ($query) {
                $query->where(function ($inner) {
                    $inner->whereNull('esi_queued_at')
                        ->orWhere('esi_queued_at', '<=', now()->subDays(30));
                });
            })
            ->exists();

        return [
            'queued' => $queued,
            'last_id' => $lastId,
            'has_more' => $hasMore,
        ];
    }

    public function hostileEmploymentOverlaps(Collection $characterIds): Collection
    {
        if ($characterIds->isEmpty()) {
            return collect();
        }

        $ignoredCorporationIds = $this->ignoredEmploymentCorporationIds();
        $localHistories = $this->localEmploymentHistories($characterIds)
            ->reject(fn ($row) => $this->isIgnoredEmploymentCorporation((int) $row->corporation_id, $ignoredCorporationIds))
            ->values();

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

        $hostileHistories = $this->employmentHistories($hostileCharacterIds, $corporationIds)
            ->reject(fn ($row) => $this->isIgnoredEmploymentCorporation((int) $row->corporation_id, $ignoredCorporationIds))
            ->values();

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
                    $lastRelevantDate = $this->overlapLastRelevantDate($local->start_date, $local->end_date, $hostile->start_date, $hostile->end_date, $sameTime);
                    $ageDays = $lastRelevantDate ? now()->diffInDays($lastRelevantDate) : null;

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
                        'overlap_last_seen_date' => $this->dateString($lastRelevantDate),
                        'overlap_age_days' => $ageDays,
                        'overlap_age_bucket' => $this->overlapAgeBucket($ageDays),
                        'source_entity_type' => $member ? $member->source_entity_type : null,
                        'source_entity_id' => $member ? $member->source_entity_id : null,
                    ];
                });
        })
            ->sortByDesc(fn (array $match) => $match['overlap_last_seen_date'] ? strtotime($match['overlap_last_seen_date']) : 0)
            ->values();
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

    private function queueMemberEsi(EveWhoMember $member, bool $force = false): bool
    {
        if (!$force && $member->esi_queued_at && $member->esi_queued_at->gt(now()->subDays(30))) {
            return false;
        }

        try {
            (new CharacterBus((int) $member->character_id))->fire();
            $member->forceFill(['esi_queued_at' => now()])->save();

            return true;
        } catch (\Throwable $exception) {
            report($exception);
        }

        return false;
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

    private function ignoredEmploymentCorporationIds(): Collection
    {
        return collect(self::STARTER_NPC_CORPORATION_IDS)
            ->merge($this->monitoredCorporationIds())
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function isIgnoredEmploymentCorporation(int $corporationId, Collection $ignoredCorporationIds): bool
    {
        if (!$corporationId) {
            return true;
        }

        if ($corporationId < self::PLAYER_CORPORATION_ID_FLOOR) {
            return true;
        }

        return $ignoredCorporationIds->contains($corporationId);
    }

    private function overlapLastRelevantDate($localStart, $localEnd, $hostileStart, $hostileEnd, bool $sameTime)
    {
        $localStart = $localStart ? \Carbon\Carbon::parse($localStart) : null;
        $localEnd = $localEnd ? \Carbon\Carbon::parse($localEnd) : now();
        $hostileStart = $hostileStart ? \Carbon\Carbon::parse($hostileStart) : null;
        $hostileEnd = $hostileEnd ? \Carbon\Carbon::parse($hostileEnd) : now();

        if ($sameTime) {
            return collect([$localEnd, $hostileEnd])->filter()->sort()->first();
        }

        return collect([$localStart, $localEnd, $hostileStart, $hostileEnd])
            ->filter()
            ->sortDesc()
            ->first();
    }

    private function overlapAgeBucket(?int $ageDays): string
    {
        if ($ageDays === null) {
            return 'unknown';
        }

        if ($ageDays <= 730) {
            return 'recent';
        }

        if ($ageDays <= 1825) {
            return 'aging';
        }

        return 'old';
    }

    private function monitoredCorporationIds(): Collection
    {
        $entities = IntelEntity::query()
            ->where('category', IntelEntity::CATEGORY_MONITORED)
            ->get();

        $corporationIds = $entities
            ->where('entity_type', 'corporation')
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id);
        $allianceIds = $entities
            ->where('entity_type', 'alliance')
            ->pluck('entity_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($allianceIds->isEmpty()) {
            return $corporationIds->filter()->unique()->values();
        }

        $allianceCorporationIds = collect();

        if (Schema::hasTable('alliance_members') && Schema::hasColumn('alliance_members', 'alliance_id') && Schema::hasColumn('alliance_members', 'corporation_id')) {
            $allianceCorporationIds = DB::table('alliance_members')
                ->whereIn('alliance_id', $allianceIds->all())
                ->pluck('corporation_id')
                ->map(fn ($id) => (int) $id);
        }

        if ($allianceCorporationIds->isEmpty() && Schema::hasTable('character_affiliations')) {
            $allianceCorporationIds = DB::table('character_affiliations')
                ->whereIn('alliance_id', $allianceIds->all())
                ->whereNotNull('corporation_id')
                ->pluck('corporation_id')
                ->map(fn ($id) => (int) $id);
        }

        return $corporationIds
            ->merge($allianceCorporationIds)
            ->filter()
            ->unique()
            ->values();
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
