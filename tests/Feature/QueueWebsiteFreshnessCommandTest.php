<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\AssessWebsiteFreshness;
use App\Models\BusinessLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueWebsiteFreshnessCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_queues_unchecked_websites_but_skips_recently_assessed_leads(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-09-03 12:00:00 UTC');

        $unchecked = BusinessLead::create([
            'name' => 'Unchecked Business',
            'place_id' => 'web:unchecked-freshness',
            'website' => 'https://unchecked.example',
        ]);

        BusinessLead::create([
            'name' => 'Recently Checked Business',
            'place_id' => 'web:checked-freshness',
            'website' => 'https://checked.example',
            'website_freshness_checked_at' => now()->subDay(),
        ]);

        BusinessLead::create([
            'name' => 'No Website Business',
            'place_id' => 'web:no-site-freshness',
        ]);

        $this->artisan('leads:queue-website-freshness', ['--limit' => 20])
            ->expectsOutput('Queued 1 website freshness job(s).')
            ->assertSuccessful();

        Queue::assertPushed(AssessWebsiteFreshness::class, function (AssessWebsiteFreshness $job) use ($unchecked): bool {
            return $job->businessLeadId === $unchecked->id && $job->queue === 'lead-freshness';
        });
        Queue::assertPushed(AssessWebsiteFreshness::class, 1);
    }
}
