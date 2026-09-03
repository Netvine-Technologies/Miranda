<?php

namespace App\Console\Commands;

use App\Jobs\LeadDiscovery\QualifyNewWebsiteCandidate;
use App\Models\NewWebsiteCandidate;
use App\Services\LeadDiscovery\NewDomainFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportNewWebsiteCandidates extends Command
{
    protected $signature = 'leads:import-new-websites
        {--date= : UTC feed date in YYYY-MM-DD format; defaults to yesterday}
        {--limit= : Maximum sample rows to consider}
        {--queue= : Maximum qualification jobs to queue}';

    protected $description = 'Import and queue high-intent candidates from the public newly registered domains sample';

    public function handle(NewDomainFeed $feed): int
    {
        if (! config('leads.new_websites.enabled', false)) {
            $this->line('New website discovery is disabled.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('new_website_candidates')) {
            $this->error('The new website candidates table is not installed.');

            return self::FAILURE;
        }

        if (DB::table('failed_jobs')->where('queue', 'lead-new-websites')->exists()) {
            $this->error('A lead-new-websites job has failed. New website dispatch is paused for review.');

            return self::FAILURE;
        }

        $queueBusy = DB::table('jobs')->where('queue', 'lead-new-websites')->exists();

        try {
            $date = $this->feedDate();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null
            ? min(max((int) $this->option('limit'), 1), 5000)
            : min(max((int) config('leads.new_websites.daily_import_limit', 1000), 1), 5000);
        $queueLimit = $this->option('queue') !== null
            ? min(max((int) $this->option('queue'), 1), 100)
            : min(max((int) config('leads.new_websites.daily_qualification_limit', 25), 1), 100);

        try {
            $rows = $feed->publicSample($date, $limit);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $created = 0;

        foreach ($rows as $row) {
            $candidate = NewWebsiteCandidate::query()->firstOrCreate(
                ['domain' => $row['domain']],
                [
                    'source' => 'whoisxml_nrd_public_sample',
                    'source_date' => $date,
                    'status' => NewWebsiteCandidate::STATUS_PENDING,
                    'priority_score' => $row['priority_score'],
                    'matched_terms' => $row['matched_terms'],
                ],
            );
            $created += $candidate->wasRecentlyCreated ? 1 : 0;
        }

        $candidates = $queueBusy
            ? collect()
            : NewWebsiteCandidate::query()
                ->where('status', NewWebsiteCandidate::STATUS_PENDING)
                ->orderByDesc('priority_score')
                ->orderByDesc('source_date')
                ->orderBy('id')
                ->limit($queueLimit)
                ->get();

        foreach ($candidates as $candidate) {
            $candidate->update(['status' => NewWebsiteCandidate::STATUS_QUEUED]);
            QualifyNewWebsiteCandidate::dispatch($candidate->id);
        }

        $this->info("Imported {$created} new candidate(s); queued {$candidates->count()} qualification job(s).");
        $this->line("Feed date: {$date->toDateString()}");
        $this->line("High-intent rows in sample: ".count($rows));

        return self::SUCCESS;
    }

    protected function feedDate(): Carbon
    {
        $requested = trim((string) $this->option('date'));

        if ($requested === '') {
            return now('UTC')->subDay()->startOfDay();
        }

        $date = Carbon::createFromFormat('Y-m-d', $requested, 'UTC')->startOfDay();

        if ($date->toDateString() !== $requested || $date->isFuture()) {
            throw new \InvalidArgumentException('The feed date must be a valid non-future YYYY-MM-DD date.');
        }

        return $date;
    }
}
