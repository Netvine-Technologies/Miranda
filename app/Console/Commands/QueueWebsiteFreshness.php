<?php

namespace App\Console\Commands;

use App\Jobs\LeadDiscovery\AssessWebsiteFreshness;
use App\Models\BusinessLead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueWebsiteFreshness extends Command
{
    protected $signature = 'leads:queue-website-freshness
        {--limit=20 : Maximum website freshness jobs to queue}
        {--recheck-days= : Recheck assessments older than this many days}';

    protected $description = 'Queue bounded evidence-based website freshness assessments';

    public function handle(): int
    {
        if (! config('leads.website_freshness.enabled', true)) {
            $this->line('Website freshness assessment is disabled.');

            return self::SUCCESS;
        }

        if (! Schema::hasColumn('business_leads', 'website_freshness_checked_at')) {
            $this->error('Website freshness columns are not installed.');

            return self::FAILURE;
        }

        if (DB::table('failed_jobs')->where('queue', 'lead-freshness')->exists()) {
            $this->error('A lead-freshness job has failed. Freshness dispatch is paused for review.');

            return self::FAILURE;
        }

        if (DB::table('jobs')->where('queue', 'lead-freshness')->exists()) {
            $this->line('Lead freshness queue is still active.');

            return self::SUCCESS;
        }

        $limit = min(max((int) $this->option('limit'), 1), 100);
        $recheckDays = $this->option('recheck-days') !== null
            ? max((int) $this->option('recheck-days'), 1)
            : max((int) config('leads.website_freshness.recheck_days', 30), 1);
        $staleBefore = now()->subDays($recheckDays);

        $leads = BusinessLead::query()
            ->whereNotNull('website')
            ->where('website', '!=', '')
            ->where(function ($query) use ($staleBefore): void {
                $query->whereNull('website_freshness_checked_at')
                    ->orWhere('website_freshness_checked_at', '<=', $staleBefore);
            })
            ->orderByRaw('CASE WHEN website_freshness_checked_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id']);

        foreach ($leads as $lead) {
            AssessWebsiteFreshness::dispatch($lead->id);
        }

        $this->info("Queued {$leads->count()} website freshness job(s).");

        return self::SUCCESS;
    }
}
