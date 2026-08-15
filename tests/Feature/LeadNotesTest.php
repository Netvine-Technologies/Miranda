<?php

namespace Tests\Feature;

use App\Models\BusinessLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_add_a_lead_note_with_an_outcome(): void
    {
        $user = User::factory()->create();
        $lead = BusinessLead::create([
            'name' => 'Everybody Pilates',
            'place_id' => 'place-123',
        ]);

        $response = $this->actingAs($user)->post(route('leads.notes.store', $lead), [
            'outcome' => 'keen',
            'body' => 'Asked for a follow-up call next Tuesday.',
        ]);

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertDatabaseHas('lead_notes', [
            'business_lead_id' => $lead->id,
            'user_id' => $user->id,
            'outcome' => 'keen',
            'body' => 'Asked for a follow-up call next Tuesday.',
        ]);
    }

    public function test_authenticated_user_can_save_an_outcome_without_a_note(): void
    {
        $user = User::factory()->create();
        $lead = BusinessLead::create([
            'name' => 'Outcome Only Studio',
            'place_id' => 'place-outcome-only',
        ]);

        $response = $this->actingAs($user)->post(route('leads.notes.store', $lead), [
            'outcome' => 'no_answer',
        ]);

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertDatabaseHas('lead_notes', [
            'business_lead_id' => $lead->id,
            'outcome' => 'no_answer',
            'body' => '',
        ]);
    }

    public function test_lead_list_shows_the_latest_outcome(): void
    {
        $user = User::factory()->create();
        $lead = BusinessLead::create([
            'name' => 'Listed Studio',
            'place_id' => 'place-listed',
        ]);
        $lead->notes()->create(['outcome' => 'keen', 'body' => '']);

        $this->actingAs($user)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('Outcome: Keen');
    }
}
