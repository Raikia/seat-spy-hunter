<?php

namespace Raikia\SeatSpyHunter\Services;

class RiskRating
{
    public static function fromScore(int $score): string
    {
        if ($score >= 80) {
            return 'critical';
        }

        if ($score >= 50) {
            return 'high';
        }

        if ($score >= 25) {
            return 'watch';
        }

        return 'clear';
    }
}
