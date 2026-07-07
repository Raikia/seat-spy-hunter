<?php

namespace Raikia\SeatSpyHunter\Console\Commands;

use Illuminate\Console\Command;
use Raikia\SeatSpyHunter\Jobs\RefreshIntelReportsJob;

class RefreshIntelReports extends Command
{
    protected $signature = 'seat-spy-hunter:refresh';

    protected $description = 'Queue a refresh of read-only SeAT Spy Hunter account risk reports.';

    public function handle(): int
    {
        RefreshIntelReportsJob::dispatch();

        $this->info('Spy Hunter report refresh queued.');

        return self::SUCCESS;
    }
}
