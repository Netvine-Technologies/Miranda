<?php

namespace Tests\Feature;

use App\Models\BusinessLead;
use App\Models\LeadNote;
use App\Models\LeadScanRun;
use App\Models\User;
use App\Models\ZoomCallLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadBatchNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_leads_can_be_filtered_and_navigated_within_a_discovery_batch(): void
    {
        $user = User::factory()->create();
        $run = LeadScanRun::create([
            'query' => 'pilates',
            'location' => 'Sydney',
            'status' => LeadScanRun::STATUS_COMPLETED,
        ]);
        $first = BusinessLead::create(['name' => 'First Studio', 'place_id' => 'place-first']);
        $second = BusinessLead::create(['name' => 'Second Studio', 'place_id' => 'place-second']);
        $other = BusinessLead::create(['name' => 'Other Batch Studio', 'place_id' => 'place-other']);
        $run->businessLeads()->attach([$first->id, $second->id]);

        $this->actingAs($user)
            ->get(route('leads.index', ['scan_run' => $run->id]))
            ->assertOk()
            ->assertSee('First Studio')
            ->assertSee('Second Studio')
            ->assertDontSee('Other Batch Studio');

        $this->actingAs($user)
            ->get(route('leads.show', ['businessLead' => $second, 'scan_run' => $run->id]))
            ->assertOk()
            ->assertSee('Previous lead')
            ->assertSee('Next lead');
    }

    public function test_batch_call_summary_groups_repeat_calls_by_phone_number(): void
    {
        $user = User::factory()->create();
        $run = LeadScanRun::create([
            'query' => 'pilates',
            'location' => 'Sydney',
            'status' => LeadScanRun::STATUS_COMPLETED,
        ]);
        $lead = BusinessLead::create([
            'name' => 'Called Studio',
            'place_id' => 'called-studio',
            'phone' => '+61 469 741 282',
        ]);
        $run->businessLeads()->attach($lead);
        LeadNote::create([
            'business_lead_id' => $lead->id,
            'user_id' => $user->id,
            'outcome' => 'follow_up',
            'body' => 'Owner asked for a call back on Friday morning.',
        ]);

        foreach ([
            ['first-call', '2026-08-16 10:00:00'],
            ['second-call', '2026-08-17 11:30:00'],
        ] as [$externalKey, $occurredAt]) {
            ZoomCallLog::create([
                'business_lead_id' => $lead->id,
                'external_key' => $externalKey,
                'source' => 'api',
                'direction' => 'outbound',
                'result' => 'Call Connected',
                'external_number' => '+61 469 741 282',
                'occurred_at' => $occurredAt,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('leads.index', ['scan_run' => $run->id]));

        $response->assertOk()
            ->assertSee('Batch Call Summary')
            ->assertSee('1')
            ->assertSee('Unique numbers called')
            ->assertSee('16 Aug 2026, 10:00')
            ->assertSee('17 Aug 2026, 11:30')
            ->assertSee('Follow Up')
            ->assertSee('Owner asked for a call back on Friday morning.')
            ->assertSee('<details class="batch-summary" open>', false);

        $this->assertSame(2, substr_count($response->getContent(), '+61 469 741 282'));
    }
}
