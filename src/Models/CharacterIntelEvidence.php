<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterIntelEvidence extends Model
{
    protected $table = 'seat_spy_hunter_character_report_evidence';

    protected $fillable = [
        'report_id',
        'category',
        'score',
        'title',
        'details',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function report()
    {
        return $this->belongsTo(CharacterIntelReport::class, 'report_id');
    }
}
