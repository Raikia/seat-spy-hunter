<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewSuppressionAndLookupTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seat_spy_hunter_character_reports')) {
            Schema::table('seat_spy_hunter_character_reports', function (Blueprint $table) {
                if (!Schema::hasColumn('seat_spy_hunter_character_reports', 'review_status')) {
                    $table->string('review_status', 32)->default('new')->after('last_analyzed_at');
                }
                if (!Schema::hasColumn('seat_spy_hunter_character_reports', 'review_notes')) {
                    $table->text('review_notes')->nullable()->after('review_status');
                }
                if (!Schema::hasColumn('seat_spy_hunter_character_reports', 'reviewed_by')) {
                    $table->unsignedBigInteger('reviewed_by')->nullable()->after('review_notes');
                }
                if (!Schema::hasColumn('seat_spy_hunter_character_reports', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                }
            });
        }

        if (!Schema::hasTable('seat_spy_hunter_false_positive_suppressions')) {
            Schema::create('seat_spy_hunter_false_positive_suppressions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_user_id');
                $table->string('category', 64);
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(['account_user_id', 'category']);
                $table->index(['account_user_id', 'expires_at']);
            });
        }

        if (!Schema::hasTable('seat_spy_hunter_vpn_lookup_queue')) {
            Schema::create('seat_spy_hunter_vpn_lookup_queue', function (Blueprint $table) {
                $table->id();
                $table->string('ip', 45)->unique();
                $table->string('status', 32)->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('available_at')->nullable();
                $table->timestamp('looked_up_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'available_at']);
            });
        }

        if (!Schema::hasTable('seat_spy_hunter_evewho_queue')) {
            Schema::create('seat_spy_hunter_evewho_queue', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 32);
                $table->unsignedBigInteger('entity_id');
                $table->unsignedInteger('page')->default(1);
                $table->string('status', 32)->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('available_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique(['entity_type', 'entity_id', 'page']);
                $table->index(['status', 'available_at']);
            });
        }

        if (!Schema::hasTable('seat_spy_hunter_evewho_employments')) {
            Schema::create('seat_spy_hunter_evewho_employments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('character_id');
                $table->string('character_name')->nullable();
                $table->unsignedBigInteger('corporation_id');
                $table->string('corporation_name')->nullable();
                $table->dateTime('start_date')->nullable();
                $table->dateTime('end_date')->nullable();
                $table->string('source_entity_type', 32)->nullable();
                $table->unsignedBigInteger('source_entity_id')->nullable();
                $table->json('raw')->nullable();
                $table->timestamps();

                $table->unique(['character_id', 'corporation_id', 'start_date']);
                $table->index(['corporation_id', 'start_date', 'end_date']);
                $table->index(['source_entity_type', 'source_entity_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('seat_spy_hunter_evewho_employments');
        Schema::dropIfExists('seat_spy_hunter_evewho_queue');
        Schema::dropIfExists('seat_spy_hunter_vpn_lookup_queue');
        Schema::dropIfExists('seat_spy_hunter_false_positive_suppressions');

        if (Schema::hasTable('seat_spy_hunter_character_reports')) {
            Schema::table('seat_spy_hunter_character_reports', function (Blueprint $table) {
                foreach (['review_status', 'review_notes', 'reviewed_by', 'reviewed_at'] as $column) {
                    if (Schema::hasColumn('seat_spy_hunter_character_reports', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}
