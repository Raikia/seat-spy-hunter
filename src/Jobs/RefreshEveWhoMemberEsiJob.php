<?php

namespace Raikia\SeatSpyHunter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Raikia\SeatSpyHunter\Services\EveWhoService;

class RefreshEveWhoMemberEsiJob implements ShouldQueue, ShouldBeUnique
{
    const UNIQUE_ID = 'seat-spy-hunter:evewho-member-esi-refresh';
    const DEFAULT_BATCH_SIZE = 25;
    const DEFAULT_DELAY_SECONDS = 120;

    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;
    public int $tries = 1;
    public int $uniqueFor = 900;

    public function __construct(
        private bool $force = false,
        private int $afterId = 0,
        private int $batchSize = self::DEFAULT_BATCH_SIZE
    ) {
    }

    public function handle(EveWhoService $eveWho): void
    {
        $result = $eveWho->queueCachedMemberEsiRefresh($this->force, $this->afterId, $this->batchSize);

        logger()->info('Spy Hunter EveWho member ESI refresh batch completed.', [
            'queued_members' => $result['queued'],
            'force' => $this->force,
            'after_id' => $this->afterId,
            'last_id' => $result['last_id'],
            'has_more' => $result['has_more'],
            'batch_size' => $this->batchSize,
        ]);

        if ($result['has_more'] && $result['last_id']) {
            self::dispatch($this->force, (int) $result['last_id'], $this->batchSize)
                ->delay(now()->addSeconds(self::DEFAULT_DELAY_SECONDS));
        }
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('Spy Hunter EveWho member ESI refresh job failed.', [
            'force' => $this->force,
            'after_id' => $this->afterId,
            'batch_size' => $this->batchSize,
            'attempts' => $this->job && method_exists($this->job, 'attempts') ? $this->job->attempts() : null,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
        ]);
    }

    public function uniqueId(): string
    {
        return self::UNIQUE_ID . ':' . ($this->force ? 'force' : 'stale') . ':' . $this->afterId;
    }

    public function tags(): array
    {
        return ['seat-spy-hunter', 'evewho', 'esi-refresh'];
    }
}
