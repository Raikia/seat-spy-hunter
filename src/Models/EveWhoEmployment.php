<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class EveWhoEmployment extends Model
{
    protected $table = 'seat_spy_hunter_evewho_employments';

    protected $fillable = [
        'character_id',
        'character_name',
        'corporation_id',
        'corporation_name',
        'start_date',
        'end_date',
        'source_entity_type',
        'source_entity_id',
        'raw',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'raw' => 'array',
    ];
}
