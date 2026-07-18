<?php

namespace Raikia\SeatSpyHunter\Database\Seeders;

use Seat\Services\Seeding\AbstractScheduleSeeder;

class ScheduleSeeder extends AbstractScheduleSeeder
{
    public function getSchedules(): array
    {
        return [
            [
                'command' => 'seat-spy-hunter:refresh',
                'expression' => '17 3 * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'seat-spy-hunter:vpn-lookup --limit=1000',
                'expression' => '7 0 * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'seat-spy-hunter:evewho-sync --limit=10',
                'expression' => '*/5 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'seat-spy-hunter:evewho-esi-refresh --limit=5',
                'expression' => '3,18,33,48 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
        ];
    }

    public function getDeprecatedSchedules(): array
    {
        return [
            'seat-intel:refresh',
            'seat-intel:vpn-lookup --limit=1000',
            'seat-intel:evewho-sync --limit=10',
            'seat-intel:evewho-esi-refresh --limit=5',
        ];
    }
}
