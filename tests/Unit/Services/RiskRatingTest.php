<?php

namespace Raikia\SeatSpyHunter\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Raikia\SeatSpyHunter\Services\RiskRating;

class RiskRatingTest extends TestCase
{
    public function test_it_classifies_score_boundaries(): void
    {
        $this->assertSame('clear', RiskRating::fromScore(24));
        $this->assertSame('watch', RiskRating::fromScore(25));
        $this->assertSame('high', RiskRating::fromScore(50));
        $this->assertSame('critical', RiskRating::fromScore(80));
    }
}
