<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEvewhoMembersTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seat_spy_hunter_evewho_members')) {
            return;
        }

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
            $table->index('character_id');
            $table->index('last_seen_at');
            $table->index('esi_queued_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seat_spy_hunter_evewho_members');
    }
}
