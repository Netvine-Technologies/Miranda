<?php

namespace App\Console\Commands;

use App\Services\ZoomPhone\ZoomPhoneService;
use Illuminate\Console\Command;

class SyncZoomPhoneCallHistory extends Command
{
    protected $signature = 'zoom-phone:sync {--days=7 : Number of recent days to retrieve}';

    protected $description = 'Sync Zoom Phone call history and match calls to business leads';

    public function handle(ZoomPhoneService $zoomPhoneService): int
    {
        if (! $zoomPhoneService->configured()) {
            $this->warn('Zoom Phone API credentials are not configured.');

            return self::SUCCESS;
        }

        $result = $zoomPhoneService->sync((int) $this->option('days'));
        $this->info("Received {$result['received']}; saved {$result['saved']}; matched {$result['matched']}.");

        return self::SUCCESS;
    }
}
