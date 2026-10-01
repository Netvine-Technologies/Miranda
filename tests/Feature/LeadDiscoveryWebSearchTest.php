<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\ScrapeWebSearch;
use App\Jobs\LeadDiscovery\CrawlBusinessWebsite;
use App\Models\BusinessLead;
use App\Models\LeadScanRun;
use App\Models\CoursePartnerProspect;
use App\Models\User;
use App\Services\LeadDiscovery\CoursePartnerProspectRecorder;
use App\Services\LeadDiscovery\WebSearchDiscoveryService;
use App\Services\LeadDiscovery\WebsiteCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadDiscoveryWebSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_scan_records_country_and_exports_only_evidenced_prospects(): void
    {
        config([
            'leads.web_search_provider' => 'brave',
            'search_discovery.providers.brave.api_key' => 'test-key',
        ]);
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('leads.discovery.start'), [
            'course_focus' => 'nail',
            'course_country' => 'GB',
            'location' => 'United Kingdom',
            'discovery_source' => 'web_search',
            'depth_mode' => 'quick',
        ])->assertRedirect(route('leads.discovery.index'));

        $run = LeadScanRun::query()->sole();
        $this->assertSame('nail', $run->course_focus);
        $this->assertSame('GB', $run->course_country);
        $this->assertSame(config('leads.course_campaigns.nail.query'), $run->query);
        Queue::assertPushed(ScrapeWebSearch::class);

        $qualified = BusinessLead::create([
            'name' => 'Nail Academy',
            'place_id' => 'nail-academy',
            'website' => 'https://academy.example',
        ]);
        $unqualified = BusinessLead::create([
            'name' => 'Nail Salon',
            'place_id' => 'nail-salon',
            'website' => 'https://salon.example',
        ]);
        $recorder = app(CoursePartnerProspectRecorder::class);
        $recorder->record($qualified, $run, [
            'course_evidence' => [['focus' => 'nail', 'source_page' => 'https://academy.example/nail-course']],
            'emails' => [['email' => 'hello@academy.example', 'source_page' => 'https://academy.example/contact']],
        ]);
        $recorder->record($unqualified, $run, [
            'course_evidence' => [],
            'emails' => [['email' => 'hello@salon.example', 'source_page' => 'https://salon.example/contact']],
        ]);

        $csv = $this->actingAs($user)->get(route('leads.discovery.course-campaign.export', $run))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('hello@academy.example', $csv);
        $this->assertStringContainsString('https://academy.example/nail-course', $csv);
        $this->assertStringNotContainsString('hello@salon.example', $csv);
        $this->assertDatabaseCount('course_partner_prospects', 1);
    }

    public function test_starter_market_scan_queues_four_country_specific_runs(): void
    {
        config([
            'leads.web_search_provider' => 'brave',
            'search_discovery.providers.brave.api_key' => 'test-key',
        ]);
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('leads.discovery.start'), [
            'course_focus' => 'lash',
            'course_country' => 'US',
            'course_scope' => 'starter_markets',
            'discovery_source' => 'web_search',
            'depth_mode' => 'quick',
        ])->assertRedirect(route('leads.discovery.index'));

        $this->assertDatabaseCount('lead_scan_runs', 4);
        $this->assertSame(4, LeadScanRun::query()->where('course_country', 'US')->count());
        $this->assertTrue(LeadScanRun::query()->where('location', 'New York, NY, United States')->exists());
        Queue::assertPushed(ScrapeWebSearch::class, 4);
    }

    public function test_course_partner_rescan_merges_evidence_without_resetting_review(): void
    {
        $lead = BusinessLead::create(['name' => 'Lash Academy', 'place_id' => 'lash-academy', 'website' => 'https://lash.example']);
        $first = LeadScanRun::create(['query' => 'lash courses', 'location' => 'Toronto, ON, Canada', 'course_focus' => 'lash', 'course_country' => 'CA', 'status' => LeadScanRun::STATUS_COMPLETED]);
        $second = LeadScanRun::create(['query' => 'lash courses', 'location' => 'Vancouver, BC, Canada', 'course_focus' => 'lash', 'course_country' => 'CA', 'status' => LeadScanRun::STATUS_COMPLETED]);
        $recorder = app(CoursePartnerProspectRecorder::class);

        $prospect = $recorder->record($lead, $first, [
            'course_evidence' => [['focus' => 'lash', 'source_page' => 'https://lash.example/courses']],
            'emails' => [['email' => 'info@lash.example', 'source_page' => 'https://lash.example/contact']],
        ]);
        $prospect->update(['review_status' => CoursePartnerProspect::STATUS_REVIEWED, 'review_notes' => 'Good fit']);
        $recorder->record($lead, $second, [
            'course_evidence' => [['focus' => 'lash', 'source_page' => 'https://lash.example/lash-training']],
            'emails' => [['email' => 'training@lash.example', 'source_page' => 'https://lash.example/contact']],
        ]);

        $saved = CoursePartnerProspect::query()->sole();
        $this->assertSame(CoursePartnerProspect::STATUS_REVIEWED, $saved->review_status);
        $this->assertSame('Good fit', $saved->review_notes);
        $this->assertCount(2, $saved->course_evidence);
        $this->assertCount(2, $saved->contact_emails);
        $this->assertCount(2, $saved->scanRuns);
    }

    public function test_course_scan_reuses_an_existing_contactable_prospect_without_crawling_again(): void
    {
        Queue::fake();
        $lead = BusinessLead::create(['name' => 'Nail Academy', 'place_id' => 'nail-academy', 'website' => 'https://academy.example']);
        $first = LeadScanRun::create(['query' => 'nail courses', 'location' => 'London, United Kingdom', 'course_focus' => 'nail', 'course_country' => 'GB', 'status' => LeadScanRun::STATUS_COMPLETED]);
        $second = LeadScanRun::create(['query' => 'nail training', 'location' => 'Manchester, United Kingdom', 'course_focus' => 'nail', 'course_country' => 'GB', 'status' => LeadScanRun::STATUS_QUEUED]);
        $prospect = app(CoursePartnerProspectRecorder::class)->record($lead, $first, [
            'course_evidence' => [['focus' => 'nail', 'source_page' => 'https://academy.example/nail-course']],
            'emails' => [['email' => 'hello@academy.example', 'source_page' => 'https://academy.example/contact']],
        ]);
        $service = $this->mock(WebSearchDiscoveryService::class);
        $service->shouldReceive('discover')->once()->andReturn([[
            'name' => 'Nail Academy',
            'website' => 'https://academy.example',
            'source_url' => 'https://academy.example/nail-course',
        ]]);

        (new ScrapeWebSearch($second->query, $second->location, $second->id, 'quick', 'brave'))->handle($service);

        Queue::assertNotPushed(CrawlBusinessWebsite::class);
        $this->assertSame(LeadScanRun::STATUS_COMPLETED, $second->fresh()->status);
        $this->assertSame(1, $second->fresh()->total_places_found);
        $this->assertSame(0, $second->fresh()->websites_queued);
        $this->assertTrue($prospect->scanRuns()->whereKey($second->id)->exists());
    }

    public function test_queued_duplicate_crawl_reuses_a_prospect_found_by_an_earlier_job(): void
    {
        $lead = BusinessLead::create(['name' => 'Nail Academy', 'place_id' => 'nail-academy', 'website' => 'https://academy.example']);
        $first = LeadScanRun::create(['query' => 'nail courses', 'location' => 'London, United Kingdom', 'course_focus' => 'nail', 'course_country' => 'GB', 'status' => LeadScanRun::STATUS_COMPLETED]);
        $second = LeadScanRun::create([
            'query' => 'gel nail training', 'location' => 'Manchester, United Kingdom', 'course_focus' => 'nail',
            'course_country' => 'GB', 'status' => LeadScanRun::STATUS_RUNNING,
            'total_places_found' => 1, 'details_processed' => 1, 'websites_queued' => 1,
        ]);
        $prospect = app(CoursePartnerProspectRecorder::class)->record($lead, $first, [
            'course_evidence' => [['focus' => 'nail', 'source_page' => 'https://academy.example/nail-course']],
            'emails' => [['email' => 'hello@academy.example', 'source_page' => 'https://academy.example/contact']],
        ]);
        $crawler = $this->mock(WebsiteCrawler::class);
        $crawler->shouldNotReceive('crawl');

        (new CrawlBusinessWebsite($lead->id, $second->id))->handle($crawler);

        $this->assertTrue($prospect->scanRuns()->whereKey($second->id)->exists());
        $this->assertSame(1, $second->fresh()->websites_crawled);
        $this->assertSame(LeadScanRun::STATUS_COMPLETED, $second->fresh()->status);
    }


    public function test_web_search_can_be_queued_as_a_normal_discovery_batch(): void
    {
        config([
            'leads.web_search_provider' => 'brave',
            'search_discovery.providers.brave.api_key' => 'test-key',
        ]);
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('leads.discovery.start'), [
            'query' => 'reformer pilates',
            'location' => 'Sydney, Australia',
            'discovery_source' => 'web_search',
            'depth_mode' => 'quick',
            'intent_tags' => ['booking_system', 'ai_receptionist'],
        ]);

        $response->assertRedirect(route('leads.discovery.index'));
        $this->assertDatabaseHas('lead_scan_runs', [
            'query' => 'reformer pilates',
            'location' => 'Sydney, Australia',
            'discovery_source' => 'web_search',
            'status' => LeadScanRun::STATUS_QUEUED,
        ]);
        Queue::assertPushed(ScrapeWebSearch::class, function (ScrapeWebSearch $job): bool {
            return $job->query === 'reformer pilates'
                && $job->location === 'Sydney, Australia'
                && $job->depthMode === 'quick'
                && $job->provider === 'brave';
        });

        $this->assertSame(
            ['booking_system', 'ai_receptionist'],
            LeadScanRun::query()->firstOrFail()->intent_tags,
        );
    }

    public function test_batch_intent_tags_are_merged_onto_discovered_leads(): void
    {
        Queue::fake();
        $run = LeadScanRun::create([
            'query' => 'dental clinic',
            'location' => 'Manchester, United Kingdom',
            'discovery_source' => 'web_search',
            'intent_tags' => ['ai_receptionist'],
            'status' => LeadScanRun::STATUS_QUEUED,
        ]);
        BusinessLead::create([
            'name' => 'Manchester Dental Care',
            'place_id' => 'existing-dental-lead',
            'website' => 'https://manchester-dental.example',
            'intent_tags' => ['booking_system'],
        ]);
        $service = $this->mock(WebSearchDiscoveryService::class);
        $service->shouldReceive('discover')->once()->andReturn([
            [
                'name' => 'Manchester Dental Care',
                'website' => 'https://manchester-dental.example',
                'source_url' => 'https://manchester-dental.example/contact',
            ],
        ]);

        (new ScrapeWebSearch(
            $run->query,
            $run->location,
            $run->id,
            'quick',
            'brave',
        ))->handle($service);

        $lead = BusinessLead::query()->sole();
        $this->assertSame(['booking_system', 'ai_receptionist'], $lead->intent_tags);
        $this->assertTrue($run->businessLeads()->whereKey($lead->id)->exists());
    }

    public function test_web_search_is_rejected_when_the_provider_is_not_configured(): void
    {
        config([
            'leads.web_search_provider' => 'brave',
            'search_discovery.providers.brave.api_key' => null,
        ]);
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('leads.discovery.index'))->post(route('leads.discovery.start'), [
            'query' => 'reformer pilates',
            'location' => 'Sydney, Australia',
            'discovery_source' => 'web_search',
            'depth_mode' => 'quick',
        ]);

        $response->assertRedirect(route('leads.discovery.index'));
        $response->assertSessionHasErrors('web_search');
        $this->assertDatabaseCount('lead_scan_runs', 0);
        Queue::assertNothingPushed();
    }
}
