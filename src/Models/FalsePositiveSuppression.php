<?php

namespace Raikia\SeatSpyHunter\Models;

use Illuminate\Database\Eloquent\Model;

class FalsePositiveSuppression extends Model
{
    protected $table = 'seat_spy_hunter_false_positive_suppressions';

    protected $fillable = [
        'account_user_id',
        'category',
        'reason',
        'created_by',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where(function ($inner) {
            $inner->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
