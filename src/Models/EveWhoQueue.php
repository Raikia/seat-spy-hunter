<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class EveWhoQueue extends Model
{
    protected $table = 'seat_spy_hunter_evewho_queue';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'page',
        'status',
        'attempts',
        'last_error',
        'available_at',
        'processed_at',
    ];

    protected $casts = [
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
