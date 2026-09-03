<?php

namespace App\Console\Commands;

use App\Jobs\LeadDiscovery\QualifyNewWebsiteCandidate;
use App\Models\NewWebsiteCandidate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueNewWebsiteCandidates extends Command
{
    protected $signature = 'leads:queue-new-websites {--limit=10 : Maximum qualification jobs to queue}';

    protected $description = 'Feed pending newly registered website candidates into their isolated queue';

    public function handle(): int
    {
        if (! config('leads.new_websites.enabled', false)) {
            return self::SUCCESS;
        }

        if (! Schema::hasTable('new_website_candidates')) {
            $this->error('The new website candidates table is not installed.');

            return self::FAILURE;
        }

        if (DB::table('failed_jobs')->where('queue', 'lead-new-websites')->exists()) {
            $this->error('A lead-new-websites job has failed. Candidate dispatch is paused for review.');

            return self::FAILURE;
        }

        if (DB::table('jobs')->where('queue', 'lead-new-websites')->exists()) {
            $this->line('The new website qualification queue is still active.');

            return self::SUCCESS;
        }

        $limit = min(max((int) $this->option('limit'), 1), 50);
        $candidates = NewWebsiteCandidate::query()
            ->where('status', NewWebsiteCandidate::STATUS_PENDING)
            ->orderByDesc('priority_score')
            ->orderByDesc('source_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($candidates as $candidate) {
            $candidate->update(['status' => NewWebsiteCandidate::STATUS_QUEUED]);
            QualifyNewWebsiteCandidate::dispatch($candidate->id);
        }

        $this->info("Queued {$candidates->count()} new website qualification job(s).");

        return self::SUCCESS;
    }
}
