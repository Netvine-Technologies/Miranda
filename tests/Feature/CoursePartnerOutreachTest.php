<?php

namespace Tests\Feature;

use App\Models\CoursePartnerOutreachEmail;
use App\Models\CoursePartnerProspect;
use App\Services\CoursePartnerOutreachService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CoursePartnerOutreachTest extends TestCase
{
    use RefreshDatabase;

    public function test_pixel_records_observed_loads_for_only_the_matching_email(): void
    {
        $token = str_repeat('a', 64);
        $email = CoursePartnerOutreachEmail::query()->create([
            'campaign_key' => 'educator-partnership-v1',
            'recipient_email' => 'review@example.com',
            'subject' => 'Partnership review',
            'tracking_token_hash' => hash('sha256', $token),
            'status' => 'sent',
            'commission_percent' => 20,
            'commission_months' => 12,
        ]);

        $this->get('/course-partner-email/open/'.$token.'.gif')->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get('/course-partner-email/open/'.$token.'.gif')->assertOk();
        $this->get('/course-partner-email/open/'.str_repeat('b', 64).'.gif')->assertOk();

        $this->assertSame(2, $email->fresh()->open_count);
        $this->assertNotNull($email->fresh()->first_opened_at);
        $this->assertNotNull($email->fresh()->last_opened_at);
    }

    public function test_provider_send_is_disabled_until_the_campaign_is_explicitly_enabled(): void
    {
        config(['course_partner_outreach.enabled' => false]);
        $prospect = CoursePartnerProspect::query()->create([
            'country_code' => 'GB',
            'domain' => 'academy.example',
            'name' => 'Nail Academy',
            'website' => 'https://academy.example',
            'review_status' => CoursePartnerProspect::STATUS_REVIEWED,
            'primary_email' => 'hello@academy.example',
            'course_focuses' => ['nail'],
            'course_evidence' => [['focus' => 'nail', 'source_page' => 'https://academy.example/course']],
        ]);

        $this->expectException(LogicException::class);
        try {
            app(CoursePartnerOutreachService::class)->sendProspect($prospect);
        } finally {
            $this->assertDatabaseCount('course_partner_outreach_emails', 0);
        }
    }

    public function test_review_send_is_restricted_to_the_configured_inbox(): void
    {
        config(['course_partner_outreach.review_address' => 'shane.johnson386@gmail.com']);

        $this->expectException(LogicException::class);
        try {
            app(CoursePartnerOutreachService::class)->sendPreview('another@example.com');
        } finally {
            $this->assertDatabaseCount('course_partner_outreach_emails', 0);
        }
    }

}
