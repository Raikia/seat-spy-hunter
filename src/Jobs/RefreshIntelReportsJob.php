<?php

namespace Raikia\SeatSpyHunter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Raikia\SeatSpyHunter\Services\IntelReportRefresher;

class RefreshIntelReportsJob implements ShouldQueue, ShouldBeUnique
{
    const UNIQUE_ID = 'seat-spy-hunter:refresh-reports';

    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;
    public int $uniqueFor = 3600;

    public function handle(IntelReportRefresher $refresher): void
    {
        $count = $refresher->refresh();

        logger()->info('Spy Hunter report refresh job completed.', [
            'reports' => $count,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('Spy Hunter report refresh job failed.', [
            'attempts' => $this->job && method_exists($this->job, 'attempts') ? $this->job->attempts() : null,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
        ]);
    }

    public function uniqueId(): string
    {
        return self::UNIQUE_ID;
    }

    public function tags(): array
    {
        return ['seat-spy-hunter', 'refresh'];
    }
}
