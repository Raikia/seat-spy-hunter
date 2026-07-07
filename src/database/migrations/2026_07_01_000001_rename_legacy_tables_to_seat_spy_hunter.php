<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class RenameLegacyTablesToSeatSpyHunter extends Migration
{
    private const TABLES = [
        'seat_intel_settings' => 'seat_spy_hunter_settings',
        'seat_intel_entities' => 'seat_spy_hunter_entities',
        'seat_intel_ignored_characters' => 'seat_spy_hunter_ignored_characters',
        'seat_intel_ip_intelligence' => 'seat_spy_hunter_ip_intelligence',
        'seat_intel_vpn_lookup_queue' => 'seat_spy_hunter_vpn_lookup_queue',
        'seat_intel_evewho_queue' => 'seat_spy_hunter_evewho_queue',
        'seat_intel_evewho_employments' => 'seat_spy_hunter_evewho_employments',
        'seat_intel_character_reports' => 'seat_spy_hunter_character_reports',
        'seat_intel_character_report_evidence' => 'seat_spy_hunter_character_report_evidence',
        'seat_intel_false_positive_suppressions' => 'seat_spy_hunter_false_positive_suppressions',
    ];

    public function up()
    {
        foreach (self::TABLES as $old => $new) {
            if (Schema::hasTable($old) && !Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
    }

    public function down()
    {
        foreach (array_reverse(self::TABLES) as $old => $new) {
            if (Schema::hasTable($new) && !Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }
}
