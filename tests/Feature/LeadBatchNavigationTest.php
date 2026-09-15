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
            ->assertSee('data-timezone="Australia/Sydney"', false)
            ->assertDontSee('Other Batch Studio');

        $this->actingAs($user)
            ->get(route('leads.show', ['businessLead' => $second, 'scan_run' => $run->id]))
            ->assertOk()
            ->assertSee('Previous lead')
            ->assertSee('Next lead')
            ->assertSee('Sydney')
            ->assertSee('data-timezone="Australia/Sydney"', false)
            ->assertSee('Good time to call');
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

    public function test_leads_can_be_filtered_by_multi_value_intent_tags(): void
    {
        $user = User::factory()->create();
        BusinessLead::create([
            'name' => 'Dual Intent Clinic',
            'place_id' => 'dual-intent',
            'intent_tags' => ['booking_system', 'ai_receptionist'],
        ]);
        BusinessLead::create([
            'name' => 'Reception Only Plumber',
            'place_id' => 'reception-only',
            'intent_tags' => ['ai_receptionist'],
        ]);

        $this->actingAs($user)
            ->get(route('leads.index', ['intent' => 'booking_system']))
            ->assertOk()
            ->assertSee('Dual Intent Clinic')
            ->assertSee('Booking System')
            ->assertSee('AI Receptionist')
            ->assertDontSee('Reception Only Plumber');
    }

    public function test_country_and_region_filters_use_available_lead_locations_and_show_each_leads_local_time(): void
    {
        $user = User::factory()->create();
        $torontoRun = LeadScanRun::create([
            'query' => 'restaurant',
            'location' => 'Toronto, Canada',
            'status' => LeadScanRun::STATUS_COMPLETED,
        ]);
        $phoenixRun = LeadScanRun::create([
            'query' => 'plumber',
            'location' => 'Phoenix, AZ',
            'status' => LeadScanRun::STATUS_COMPLETED,
        ]);
        $londonCanadaRun = LeadScanRun::create([
            'query' => 'restaurant',
            'location' => 'London, Canada',
            'status' => LeadScanRun::STATUS_COMPLETED,
        ]);
        $torontoLead = BusinessLead::create([
            'name' => 'Toronto Table',
            'place_id' => 'toronto-table',
            'city' => 'Canada',
        ]);
        $phoenixLead = BusinessLead::create([
            'name' => 'Phoenix Pipes',
            'place_id' => 'phoenix-pipes',
            'city' => 'United States',
        ]);
        $londonCanadaLead = BusinessLead::create([
            'name' => 'London Ontario Table',
            'place_id' => 'london-ontario-table',
            'city' => 'London, Canada',
        ]);
        $torontoRun->businessLeads()->attach($torontoLead);
        $phoenixRun->businessLeads()->attach($phoenixLead);
        $londonCanadaRun->businessLeads()->attach($londonCanadaLead);

        $this->actingAs($user)
            ->get(route('leads.index', ['country' => 'Canada', 'region' => 'Toronto']))
            ->assertOk()
            ->assertSee('Toronto Table')
            ->assertDontSee('Phoenix Pipes')
            ->assertDontSee('London Ontario Table')
            ->assertSee('value="Canada" selected', false)
            ->assertSee('value="Toronto" selected', false)
            ->assertSee('data-lead-clock', false)
            ->assertSee('data-timezone="America/Toronto"', false);

        $this->actingAs($user)
            ->get(route('leads.index', ['country' => 'United States', 'region' => 'Phoenix']))
            ->assertOk()
            ->assertSee('Phoenix Pipes')
            ->assertDontSee('Toronto Table')
            ->assertSee('data-timezone="America/Phoenix"', false);
    }

    public function test_all_batches_view_summarizes_daily_calls_and_saved_outcomes(): void
    {
        $user = User::factory()->create();
        $keenLead = BusinessLead::create([
            'name' => 'Keen Studio',
            'place_id' => 'daily-keen',
        ]);
        $noAnswerLead = BusinessLead::create([
            'name' => 'No Answer Studio',
            'place_id' => 'daily-no-answer',
        ]);

        LeadNote::create([
            'business_lead_id' => $keenLead->id,
            'user_id' => $user->id,
            'outcome' => 'keen',
            'body' => 'Asked for pricing.',
        ]);
        LeadNote::create([
            'business_lead_id' => $noAnswerLead->id,
            'user_id' => $user->id,
            'outcome' => 'no_answer',
            'body' => '',
        ]);

        foreach ([
            ['daily-1', $keenLead, '+61 400 000 001', 'outbound', 'hang_up', '09:00:00'],
            ['daily-2', $keenLead, '+61 400 000 001', 'outbound', 'connected', '10:00:00'],
            ['daily-3', $noAnswerLead, '+61 400 000 002', 'outbound', 'hang_up', '11:00:00'],
            ['daily-inbound', $keenLead, '+61 400 000 001', 'inbound', 'connected', '12:00:00'],
        ] as [$externalKey, $lead, $number, $direction, $result, $time]) {
            ZoomCallLog::create([
                'business_lead_id' => $lead->id,
                'external_key' => $externalKey,
                'source' => 'api',
                'direction' => $direction,
                'result' => $result,
                'external_number' => $number,
                'occurred_at' => '2026-08-18 '.$time,
            ]);
        }

        $this->actingAs($user)
            ->get(route('leads.index', ['activity_date' => '2026-08-18']))
            ->assertOk()
            ->assertSee('Daily Call Activity')
            ->assertSee('Tuesday, 18 August 2026')
            ->assertSeeInOrder(['2', 'Unique numbers called', '3', 'Total call attempts', '1', 'Answered contacts', '50.0%', '2', 'Contacts with outcomes'])
            ->assertSee('Keen')
            ->assertSee('No Answer')
            ->assertSee('100.0%')
            ->assertSee('0.0%');
    }
}
