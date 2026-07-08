<?php

namespace Raikia\SeatSpyHunter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Raikia\SeatSpyHunter\Services\IpIntelligenceService;

class ProcessVpnLookupQueueJob implements ShouldQueue, ShouldBeUnique
{
    const UNIQUE_ID = 'seat-spy-hunter:vpn-lookup';

    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;
    public int $uniqueFor = 1800;

    public function __construct(private int $limit = 1000)
    {
    }

    public function handle(IpIntelligenceService $ipIntelligence): void
    {
        $processed = $ipIntelligence->processQueue($this->limit);

        logger()->info('Spy Hunter VPN lookup queue job completed.', [
            'processed' => $processed,
            'limit' => $this->limit,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('Spy Hunter VPN lookup queue job failed.', [
            'limit' => $this->limit,
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
        return ['seat-spy-hunter', 'vpn-lookup'];
    }
}
