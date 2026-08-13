<?php

namespace Tests\Feature;

use App\Models\SearchDiscoveryLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchDiscoveryLeadReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_saved_search_discovery_lead(): void
    {
        $user = User::factory()->create();
        $lead = SearchDiscoveryLead::create([
            'source' => 'search_discovery',
            'city' => 'Leeds',
            'niche' => 'nails',
            'result_title' => 'Glow Studio (@glowstudioleeds) Instagram photos and videos',
            'result_url' => 'https://www.instagram.com/glowstudioleeds/',
            'instagram_handle' => 'glowstudioleeds',
            'instagram_profile_url' => 'https://www.instagram.com/glowstudioleeds/',
            'lead_score' => 10,
            'lead_classification' => 'strong_lead',
            'status' => 'new',
        ]);

        $response = $this->actingAs($user)->get(route('search-discovery.show', $lead));

        $response->assertOk();
        $response->assertSee('glowstudioleeds');
        $response->assertSee('Save Review');
    }

    public function test_authenticated_user_can_update_saved_search_discovery_lead(): void
    {
        $user = User::factory()->create();
        $lead = SearchDiscoveryLead::create([
            'source' => 'search_discovery',
            'city' => 'Leeds',
            'niche' => 'nails',
            'result_title' => 'Glow Studio (@glowstudioleeds) Instagram photos and videos',
            'result_url' => 'https://www.instagram.com/glowstudioleeds/',
            'instagram_handle' => 'glowstudioleeds',
            'instagram_profile_url' => 'https://www.instagram.com/glowstudioleeds/',
            'lead_score' => 10,
            'lead_classification' => 'strong_lead',
            'status' => 'new',
        ]);

        $response = $this->actingAs($user)->patch(route('search-discovery.update', $lead), [
            'status' => 'reviewed',
            'lead_classification' => 'medium_lead',
        ]);

        $response->assertRedirect(route('search-discovery.show', $lead));
        $this->assertSame('reviewed', $lead->fresh()->status);
        $this->assertSame('medium_lead', $lead->fresh()->lead_classification);
    }
}
