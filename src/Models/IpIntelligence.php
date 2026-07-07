<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class IpIntelligence extends Model
{
    protected $table = 'seat_spy_hunter_ip_intelligence';

    protected $fillable = [
        'ip',
        'is_vpn',
        'is_proxy',
        'is_tor',
        'is_hosting',
        'risk_score',
        'provider',
        'raw',
        'checked_at',
    ];

    protected $casts = [
        'is_vpn' => 'boolean',
        'is_proxy' => 'boolean',
        'is_tor' => 'boolean',
        'is_hosting' => 'boolean',
        'risk_score' => 'integer',
        'raw' => 'array',
        'checked_at' => 'datetime',
    ];

    public function isSuspicious(): bool
    {
        return $this->is_vpn || $this->is_proxy || $this->is_tor || $this->is_hosting || $this->risk_score >= 50;
    }
}
