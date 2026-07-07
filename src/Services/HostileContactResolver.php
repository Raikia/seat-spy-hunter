<?php

namespace Raikia\SeatSpyHunter\Services;

use Illuminate\Support\Collection;
use Raikia\SeatSpyHunter\Models\IntelEntity;
use Seat\Eveapi\Models\Contacts\AllianceContact;
use Seat\Eveapi\Models\Contacts\CorporationContact;

class HostileContactResolver
{
    public function hostileEntityIds(): Collection
    {
        $configured = IntelEntity::query()
            ->where('category', IntelEntity::CATEGORY_HOSTILE)
            ->pluck('entity_id');

        $monitored = IntelEntity::query()
            ->where('category', IntelEntity::CATEGORY_MONITORED)
            ->get();

        $negative = collect();

        foreach ($monitored as $entity) {
            if ($entity->entity_type === 'corporation') {
                $negative = $negative->merge($this->corporationNegativeContacts((int) $entity->entity_id));
            }

            if ($entity->entity_type === 'alliance') {
                $negative = $negative->merge($this->allianceNegativeContacts((int) $entity->entity_id));
            }
        }

        return $configured->merge($negative)->map(function ($id) {
            return (int) $id;
        })->filter()->unique()->values();
    }

    private function corporationNegativeContacts(int $corporation_id): Collection
    {
        return CorporationContact::query()
            ->where('corporation_id', $corporation_id)
            ->where('standing', '<', 0)
            ->pluck('contact_id');
    }

    private function allianceNegativeContacts(int $alliance_id): Collection
    {
        return AllianceContact::query()
            ->where('alliance_id', $alliance_id)
            ->where('standing', '<', 0)
            ->pluck('contact_id');
    }
}
