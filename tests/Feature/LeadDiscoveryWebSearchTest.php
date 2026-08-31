<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\ScrapeWebSearch;
use App\Models\BusinessLead;
use App\Models\LeadScanRun;
use App\Models\User;
use App\Services\LeadDiscovery\WebSearchDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadDiscoveryWebSearchTest extends TestCase
{
    use RefreshDatabase;

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
