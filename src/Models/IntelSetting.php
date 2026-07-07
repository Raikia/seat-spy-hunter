<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class IntelSetting extends Model
{
    protected $table = 'seat_spy_hunter_settings';

    protected $primaryKey = 'setting';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'setting',
        'value',
    ];
}
