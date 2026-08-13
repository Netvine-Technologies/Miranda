<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchDiscoveryUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_search_discovery_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/search-discovery');

        $response->assertOk();
        $response->assertSee('Search Discovery');
        $response->assertSee('Run Search Discovery');
    }

    public function test_authenticated_user_can_run_search_discovery_in_dry_run_mode(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/search-discovery', [
            'city' => 'Leeds',
            'niche' => 'nails',
            'phrases' => 'DM to book,book via DM',
            'provider' => 'null',
            'limit' => 10,
            'dry_run' => '1',
        ]);

        $response->assertRedirect('/search-discovery?city=Leeds&niche=nails&provider=null');
        $response->assertSessionHas('status', 'Search Discovery completed.');
    }
}
