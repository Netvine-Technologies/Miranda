<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\QualifyNewWebsiteCandidate;
use App\Models\NewWebsiteCandidate;
use App\Services\LeadDiscovery\NewWebsiteQualifier;
use App\Services\LeadDiscovery\WebsiteFreshnessAssessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class QualifyNewWebsiteCandidateJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_promotes_a_callable_new_business_into_the_leads_table(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00 UTC');
        $candidate = NewWebsiteCandidate::create([
            'domain' => 'newcitydentalclinic.com',
            'source' => 'whoisxml_nrd_public_sample',
            'source_date' => '2026-09-02',
            'status' => NewWebsiteCandidate::STATUS_QUEUED,
            'priority_score' => 70,
            'matched_terms' => ['dental', 'clinic'],
        ]);
        $qualifier = Mockery::mock(NewWebsiteQualifier::class);
        $qualifier->shouldReceive('qualify')->once()->andReturn([
            'qualified' => true,
            'website' => 'https://newcitydentalclinic.com',
            'name' => 'New City Dental Clinic',
            'location' => 'Austin, TX, United States',
            'category' => 'healthcare',
            'intent_tags' => ['booking_system', 'ai_receptionist'],
            'emails' => [['email' => 'hello@newcitydentalclinic.com', 'source_page' => 'https://newcitydentalclinic.com/contact']],
            'phone_numbers' => [['phone_number' => '+15125552671', 'source_page' => 'https://newcitydentalclinic.com/contact']],
            'booking_url' => 'https://newcitydentalclinic.com/book',
        ]);
        $assessor = Mockery::mock(WebsiteFreshnessAssessor::class);
        $assessor->shouldReceive('assess')->once()->andReturn([
            'domain_registered_at' => Carbon::parse('2026-09-02'),
            'earliest_certificate_at' => Carbon::parse('2026-09-02'),
            'earliest_archive_at' => null,
            'website_launch_evidence_at' => null,
            'website_estimated_launched_at' => Carbon::parse('2026-09-02'),
            'website_freshness_score' => 80,
            'website_freshness_confidence' => 'high',
            'website_freshness_evidence' => ['reasons' => ['Recent registration and certificate.']],
            'website_freshness_checked_at' => now(),
        ]);

        (new QualifyNewWebsiteCandidate($candidate->id))->handle($qualifier, $assessor);

        $candidate->refresh();
        $lead = $candidate->businessLead;

        $this->assertSame(NewWebsiteCandidate::STATUS_QUALIFIED, $candidate->status);
        $this->assertSame('+15125552671', $lead?->phone);
        $this->assertSame('high', $lead?->website_freshness_confidence);
        $this->assertContains('booking_system', $lead?->intent_tags ?? []);
        $this->assertDatabaseHas('lead_phone_numbers', ['business_lead_id' => $lead?->id, 'phone_number' => '+15125552671']);
    }
}
