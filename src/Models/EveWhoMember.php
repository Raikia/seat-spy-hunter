<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class EveWhoMember extends Model
{
    protected $table = 'seat_spy_hunter_evewho_members';

    protected $fillable = [
        'character_id',
        'character_name',
        'source_entity_type',
        'source_entity_id',
        'corporation_id',
        'corporation_name',
        'alliance_id',
        'alliance_name',
        'raw',
        'last_seen_at',
        'esi_queued_at',
    ];

    protected $casts = [
        'raw' => 'array',
        'last_seen_at' => 'datetime',
        'esi_queued_at' => 'datetime',
    ];
}
