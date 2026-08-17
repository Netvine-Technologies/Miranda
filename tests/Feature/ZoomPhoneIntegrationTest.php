<?php

namespace Tests\Feature;

use App\Models\BusinessLead;
use App\Models\User;
use App\Services\ZoomPhone\ZoomPhoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    public function test_cloud_sync_stores_zooms_call_result_field(): void
    {
        config([
            'zoom-phone.account_id' => 'account-id',
            'zoom-phone.client_id' => 'client-id',
            'zoom-phone.client_secret' => 'client-secret',
        ]);
        Cache::forget('zoom-phone.access-token');
        Http::fake([
            'https://zoom.us/oauth/token' => Http::response(['access_token' => 'test-token']),
            'https://api.zoom.us/v2/phone/call_history*' => Http::response([
                'call_history' => [[
                    'call_id' => 'cloud-call-1',
                    'call_history_uuid' => 'cloud-history-1',
                    'direction' => 'outbound',
                    'caller_did_number' => '+44 20 1234 5678',
                    'callee_did_number' => '+61 400 000 001',
                    'call_result' => 'connected',
                    'duration' => 42,
                    'start_time' => '2026-08-18T09:00:00Z',
                    'end_time' => '2026-08-18T09:00:42Z',
                ]],
            ]),
        ]);

        app(ZoomPhoneService::class)->sync(2);

        $this->assertDatabaseHas('zoom_call_logs', [
            'zoom_call_id' => 'cloud-call-1',
            'result' => 'connected',
        ]);
    }
}
