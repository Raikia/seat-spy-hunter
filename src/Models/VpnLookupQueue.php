<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class VpnLookupQueue extends Model
{
    protected $table = 'seat_spy_hunter_vpn_lookup_queue';

    protected $fillable = [
        'ip',
        'status',
        'attempts',
        'last_error',
        'available_at',
        'looked_up_at',
    ];

    protected $casts = [
        'available_at' => 'datetime',
        'looked_up_at' => 'datetime',
    ];
}
