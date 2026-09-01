<?php

namespace App\Console\Commands;

use App\Jobs\LeadDiscovery\CrawlBusinessWebsite;
use App\Models\BusinessLead;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QueueLeadRepairs extends Command
{
    protected $signature = 'leads:queue-repairs
        {--limit=100 : Maximum repair jobs to queue}
        {--before= : Only repair leads last updated before this timestamp}';

    protected $description = 'Queue additive website recrawls for completed leads with missing contact details';

    public function handle(): int
    {
        if (DB::table('failed_jobs')->where('queue', 'lead-repair')->exists()) {
            $this->error('A lead-repair job has failed. Repair dispatch is paused for review.');

            return self::FAILURE;
        }

        if (DB::table('jobs')->where('queue', 'lead-repair')->exists()) {
            $this->line('Lead repair queue is still active.');

            return self::SUCCESS;
        }

        $limit = min(max((int) $this->option('limit'), 1), 500);
        try {
            $before = $this->parseCutoff((string) $this->option('before'));
        } catch (\Throwable) {
            $this->error('The --before value is not a valid timestamp.');

            return self::FAILURE;
        }

        $leads = BusinessLead::query()
            ->where('scraped', true)
            ->whereNotNull('website')
            ->where('website', '!=', '')
            ->when($before instanceof Carbon, fn ($query) => $query->where('updated_at', '<', $before))
            ->where(function ($query): void {
                $query->whereNull('phone')
                    ->orWhereDoesntHave('emails')
                    ->orWhereNull('booking_url');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);

        foreach ($leads as $lead) {
            CrawlBusinessWebsite::dispatch($lead->id)->onQueue('lead-repair');
        }

        $this->info("Queued {$leads->count()} lead repair job(s).");

        return self::SUCCESS;
    }

    protected function parseCutoff(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
