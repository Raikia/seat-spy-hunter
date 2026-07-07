<?php

namespace Raikia\SeatSpyHunter\Console\Commands;

use Illuminate\Console\Command;
use Raikia\SeatSpyHunter\Services\IpIntelligenceService;

class ProcessVpnLookupQueue extends Command
{
    protected $signature = 'seat-spy-hunter:vpn-lookup {--limit=1000 : Maximum VPNAPI.io lookups to process.}';

    protected $description = 'Process queued VPNAPI.io lookups for uncached public IPs.';

    public function handle(IpIntelligenceService $ipIntelligence): int
    {
        $processed = $ipIntelligence->processQueue((int) $this->option('limit'));

        $this->info($processed . ' VPN lookup' . ($processed === 1 ? '' : 's') . ' processed.');

        return self::SUCCESS;
    }
}
