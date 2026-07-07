<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeatSpyHunterTables extends Migration
{
    public function up()
    {
        Schema::create('seat_spy_hunter_settings', function (Blueprint $table) {
            $table->string('setting')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_spy_hunter_entities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_type', 32);
            $table->string('name')->nullable();
            $table->string('category', 32);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'category'], 'spy_hunter_entities_unique');
            $table->index(['category', 'entity_type'], 'spy_hunter_entities_category_type_index');
        });

        Schema::create('seat_spy_hunter_ignored_characters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id')->unique('spy_hunter_ignored_character_unique');
            $table->string('name')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_spy_hunter_ip_intelligence', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique('spy_hunter_ip_intel_ip_unique');
            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_tor')->default(false);
            $table->boolean('is_hosting')->default(false);
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('provider')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_spy_hunter_vpn_lookup_queue', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique('spy_hunter_vpn_queue_ip_unique');
            $table->string('status', 32)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('looked_up_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at'], 'spy_hunter_vpn_queue_status_index');
        });

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

            $table->unique(['entity_type', 'entity_id', 'page'], 'spy_hunter_evewho_queue_unique');
            $table->index(['status', 'available_at'], 'spy_hunter_evewho_queue_status_index');
        });

        Schema::create('seat_spy_hunter_evewho_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->string('character_name')->nullable();
            $table->string('source_entity_type', 32);
            $table->unsignedBigInteger('source_entity_id');
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->string('corporation_name')->nullable();
            $table->unsignedBigInteger('alliance_id')->nullable();
            $table->string('alliance_name')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('esi_queued_at')->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'source_entity_type', 'source_entity_id'], 'spy_hunter_evewho_members_unique');
            $table->index(['source_entity_type', 'source_entity_id'], 'spy_hunter_evewho_members_source_index');
            $table->index('character_id', 'spy_hunter_evewho_members_character_index');
            $table->index('last_seen_at', 'spy_hunter_evewho_members_seen_index');
            $table->index('esi_queued_at', 'spy_hunter_evewho_members_esi_index');
        });

        Schema::create('seat_spy_hunter_character_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_user_id')->unique('spy_hunter_reports_account_unique');
            $table->unsignedBigInteger('character_id')->nullable();
            $table->string('character_name')->nullable();
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->string('corporation_name')->nullable();
            $table->unsignedBigInteger('alliance_id')->nullable();
            $table->string('alliance_name')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('score')->default(0);
            $table->string('rating', 32)->default('clear');
            $table->unsignedInteger('evidence_count')->default(0);
            $table->unsignedInteger('hostile_contact_count')->default(0);
            $table->unsignedInteger('hostile_mail_count')->default(0);
            $table->unsignedInteger('hostile_wallet_count')->default(0);
            $table->unsignedInteger('shared_ip_user_count')->default(0);
            $table->unsignedInteger('vpn_ip_count')->default(0);
            $table->unsignedBigInteger('skillpoints')->nullable();
            $table->date('birthday')->nullable();
            $table->timestamp('last_analyzed_at')->nullable();
            $table->string('review_status', 32)->default('new');
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['score', 'rating'], 'spy_hunter_reports_score_index');
            $table->index(['corporation_id', 'alliance_id'], 'spy_hunter_reports_corp_alliance_index');
            $table->index('character_id', 'spy_hunter_reports_character_index');
            $table->index('user_id', 'spy_hunter_reports_user_index');
        });

        Schema::create('seat_spy_hunter_character_report_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->string('category', 64);
            $table->unsignedInteger('score')->default(0);
            $table->string('title');
            $table->text('details')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('report_id', 'spy_hunter_evidence_report_fk')
                ->references('id')
                ->on('seat_spy_hunter_character_reports')
                ->cascadeOnDelete();
            $table->index(['report_id', 'category'], 'spy_hunter_evidence_report_category_index');
        });

        Schema::create('seat_spy_hunter_false_positive_suppressions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_user_id');
            $table->string('category', 64);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['account_user_id', 'category'], 'spy_hunter_suppressions_unique');
            $table->index(['account_user_id', 'expires_at'], 'spy_hunter_suppressions_expiry_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seat_spy_hunter_false_positive_suppressions');
        Schema::dropIfExists('seat_spy_hunter_character_report_evidence');
        Schema::dropIfExists('seat_spy_hunter_character_reports');
        Schema::dropIfExists('seat_spy_hunter_evewho_members');
        Schema::dropIfExists('seat_spy_hunter_evewho_queue');
        Schema::dropIfExists('seat_spy_hunter_vpn_lookup_queue');
        Schema::dropIfExists('seat_spy_hunter_ip_intelligence');
        Schema::dropIfExists('seat_spy_hunter_ignored_characters');
        Schema::dropIfExists('seat_spy_hunter_entities');
        Schema::dropIfExists('seat_spy_hunter_settings');
    }
}
