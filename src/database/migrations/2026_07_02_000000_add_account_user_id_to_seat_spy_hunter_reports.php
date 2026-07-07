<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAccountUserIdToSeatSpyHunterReports extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('seat_spy_hunter_character_reports')) {
            return;
        }

        if (!Schema::hasColumn('seat_spy_hunter_character_reports', 'account_user_id')) {
            Schema::table('seat_spy_hunter_character_reports', function (Blueprint $table) {
                $table->unsignedBigInteger('account_user_id')->nullable()->after('id');
            });

            DB::table('seat_spy_hunter_character_reports')
                ->whereNull('account_user_id')
                ->update([
                    'account_user_id' => DB::raw('coalesce(user_id, character_id)'),
                ]);

            Schema::table('seat_spy_hunter_character_reports', function (Blueprint $table) {
                $table->unique('account_user_id');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('seat_spy_hunter_character_reports') || !Schema::hasColumn('seat_spy_hunter_character_reports', 'account_user_id')) {
            return;
        }

        Schema::table('seat_spy_hunter_character_reports', function (Blueprint $table) {
            $table->dropUnique('seat_spy_hunter_character_reports_account_user_id_unique');
            $table->dropColumn('account_user_id');
        });
    }
}
