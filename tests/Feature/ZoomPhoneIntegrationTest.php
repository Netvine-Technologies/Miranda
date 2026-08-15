<?php

namespace Tests\Feature;

use App\Models\BusinessLead;
use App\Models\User;
use App\Services\ZoomPhone\ZoomPhoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoomPhoneIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_smart_embed_call_is_matched_to_a_lead(): void
    {
        $user = User::factory()->create();
        $lead = BusinessLead::create([
            'name' => 'Global Pilates',
            'place_id' => 'zoom-place-1',
            'phone' => '+61 469 741 282',
        ]);

        $call = app(ZoomPhoneService::class)->storeSmartEmbedEvent([
            'id' => 'event-1',
            'type' => 'zp-call-log-completed-event',
            'data' => [
                'callId' => 'call-1',
                'callLogId' => 'log-1',
                'direction' => 'outbound',
                'caller' => ['number' => '+44 20 1234 5678'],
                'callee' => ['number' => '+61 469 741 282'],
                'result' => 'Call Connected',
                'duration' => 75,
                'dateTime' => '2026-08-15T21:00:00Z',
            ],
        ], $user->id);

        $this->assertSame($lead->id, $call->business_lead_id);
        $this->assertSame('+61 469 741 282', $call->external_number);
    }

    public function test_authenticated_user_can_open_zoom_phone_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('zoom-phone.index'))
            ->assertOk()
            ->assertSee('Zoom Phone')
            ->assertSee('Miranda call history');
    }
}
