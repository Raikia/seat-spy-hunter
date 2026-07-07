<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class IgnoredCharacter extends Model
{
    protected $table = 'seat_spy_hunter_ignored_characters';

    protected $fillable = [
        'character_id',
        'name',
        'reason',
    ];
}
