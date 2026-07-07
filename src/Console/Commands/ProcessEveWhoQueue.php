<?php

namespace Raikia\SeatSpyHunter\Console\Commands;

use Illuminate\Console\Command;
use Raikia\SeatSpyHunter\Services\EveWhoService;

class ProcessEveWhoQueue extends Command
{
    protected $signature = 'seat-spy-hunter:evewho-sync {--limit=10 : Maximum EveWho list pages to process.}';

    protected $description = 'Process queued EveWho hostile membership lookups and queue monthly SeAT ESI character history refreshes.';

    public function handle(EveWhoService $eveWho): int
    {
        $eveWho->queueConfiguredHostiles();
        $processed = $eveWho->processQueue((int) $this->option('limit'));

        $this->info($processed . ' EveWho page' . ($processed === 1 ? '' : 's') . ' processed.');

        return self::SUCCESS;
    }
}
