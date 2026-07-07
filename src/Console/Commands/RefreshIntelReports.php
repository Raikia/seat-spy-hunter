<?php

namespace Raikia\SeatSpyHunter\Console\Commands;

use Illuminate\Console\Command;
use Raikia\SeatSpyHunter\Services\IntelReportRefresher;

class RefreshIntelReports extends Command
{
    protected $signature = 'seat-spy-hunter:refresh';

    protected $description = 'Refresh read-only SeAT Spy Hunter account risk reports.';

    public function handle(IntelReportRefresher $refresher): int
    {
        $count = $refresher->refresh();

        $this->info($count . ' account spy hunter report' . ($count === 1 ? '' : 's') . ' refreshed.');

        return self::SUCCESS;
    }
}
