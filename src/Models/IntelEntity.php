<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class IntelEntity extends Model
{
    const CATEGORY_MONITORED = 'monitored';
    const CATEGORY_HOSTILE = 'hostile';

    protected $table = 'seat_spy_hunter_entities';

    protected $fillable = [
        'entity_id',
        'entity_type',
        'name',
        'category',
        'notes',
    ];
}
