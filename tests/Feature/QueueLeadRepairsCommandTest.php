<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\CrawlBusinessWebsite;
use App\Models\BusinessLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueLeadRepairsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_only_older_completed_leads_with_missing_contact_details(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-09-01 20:00:00');

        $eligible = BusinessLead::create([
            'name' => 'Eligible Business',
            'place_id' => 'web:eligible',
            'website' => 'https://eligible.example',
            'scraped' => true,
        ]);
        $eligible->timestamps = false;
        $eligible->updated_at = Carbon::parse('2026-08-31 12:00:00');
        $eligible->save();

        BusinessLead::create([
            'name' => 'Recent Business',
            'place_id' => 'web:recent',
            'website' => 'https://recent.example',
            'scraped' => true,
        ]);

        $this->artisan('leads:queue-repairs', [
            '--before' => '2026-09-01T19:52:58Z',
            '--limit' => 100,
        ])->assertSuccessful();

        Queue::assertPushed(CrawlBusinessWebsite::class, function (CrawlBusinessWebsite $job) use ($eligible): bool {
            return $job->businessLeadId === $eligible->id && $job->queue === 'lead-repair';
        });
        Queue::assertPushed(CrawlBusinessWebsite::class, 1);
    }
}
