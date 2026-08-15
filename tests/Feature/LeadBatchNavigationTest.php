<?php

namespace Tests\Feature;

use App\Models\BusinessLead;
use App\Models\LeadScanRun;
use App\Models\User;
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
}
