<?php

namespace Raikia\SeatSpyHunter\Console\Commands;

use Illuminate\Console\Command;
use Raikia\SeatSpyHunter\Services\EveWhoService;

class QueueEveWhoMemberEsiRefresh extends Command
{
    protected $signature = 'seat-spy-hunter:evewho-esi-refresh {--limit=5 : Maximum stale EveWho members to queue.}';

    protected $description = 'Queue a small batch of stale EveWho member corporation-history ESI refreshes.';

    public function handle(EveWhoService $eveWho): int
    {
        $result = $eveWho->queueCachedMemberEsiRefresh(false, 0, (int) $this->option('limit'));

        $this->info(sprintf(
            'Queued %d stale EveWho member corporation-history refresh%s.',
            $result['queued'],
            $result['queued'] === 1 ? '' : 'es'
        ));

        return self::SUCCESS;
    }
}
