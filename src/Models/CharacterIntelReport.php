<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterIntelReport extends Model
{
    protected $table = 'seat_spy_hunter_character_reports';

    protected $fillable = [
        'account_user_id',
        'character_id',
        'character_name',
        'corporation_id',
        'corporation_name',
        'alliance_id',
        'alliance_name',
        'user_id',
        'score',
        'rating',
        'evidence_count',
        'hostile_contact_count',
        'hostile_mail_count',
        'hostile_wallet_count',
        'shared_ip_user_count',
        'vpn_ip_count',
        'skillpoints',
        'birthday',
        'last_analyzed_at',
        'review_status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'last_analyzed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'birthday' => 'date',
    ];

    public function evidence()
    {
        return $this->hasMany(CharacterIntelEvidence::class, 'report_id')->orderByDesc('score');
    }

    public function suppressions()
    {
        return $this->hasMany(FalsePositiveSuppression::class, 'account_user_id', 'account_user_id')->active();
    }
}
