<?php

namespace Tests\Feature;

use App\Mail\CoursePartnerProposal;
use App\Models\CoursePartnerProspect;
use App\Services\CoursePartnerOutreachService;
use App\Services\CoursePartnerSendingQueue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\TestCase;

class CoursePartnerSendingQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'course_partner_outreach.enabled' => true,
            'course_partner_outreach.tracking_base_url' => 'https://track.bookingtreeoutreach.com',
            'course_partner_outreach.sender_address' => 'shane@bookingtreeoutreach.com',
            'mail.mailers.course_partner_smtp' => [
                'scheme' => 'smtps', 'host' => 'smtp.gmail.com', 'port' => 465,
                'username' => 'shane@bookingtreeoutreach.com', 'password' => 'test-only-placeholder',
            ],
        ]);
    }

    private function prospect(string $key, bool $reviewed = true): CoursePartnerProspect
    {
        return CoursePartnerProspect::query()->create([
            'country_code' => 'GB', 'domain' => $key.'.example',
            'name' => ucfirst($key).' Academy', 'website' => 'https://'.$key.'.example',
            'primary_email' => 'hello@'.$key.'.example',
            'review_status' => $reviewed ? CoursePartnerProspect::STATUS_REVIEWED : CoursePartnerProspect::STATUS_NEW,
            'reviewed_at' => $reviewed ? now() : null,
            'course_focuses' => ['nail'],
            'course_evidence' => [['focus' => 'nail', 'source_page' => 'https://'.$key.'.example/course']],
        ]);
    }

    public function test_only_reviewed_prospects_can_be_staged_and_queue_starts_paused(): void
    {
        $queue = app(CoursePartnerSendingQueue::class);
        $this->assertTrue((bool) $queue->control()->paused);
        try {
            $queue->stage($this->prospect('unreviewed', false));
            $this->fail('Unreviewed prospect was staged.');
        } catch (LogicException) {
            $this->assertDatabaseCount('course_partner_sending_queue', 0);
        }

        $queue->stage($this->prospect('reviewed'));
        $this->assertNull($queue->dispatchOne(app(CoursePartnerOutreachService::class)));
        Mail::assertNothingSent();
    }

    public function test_queue_sends_one_per_attempt_and_obeys_daily_limit(): void
    {
        $queue = app(CoursePartnerSendingQueue::class);
        $queue->stage($this->prospect('first'));
        $queue->stage($this->prospect('second'));
        $queue->configure(false, 1, 1);

        $this->assertSame('sent', $queue->dispatchOne(app(CoursePartnerOutreachService::class)));
        $this->travel(2)->minutes();
        $this->assertNull($queue->dispatchOne(app(CoursePartnerOutreachService::class)));
        Mail::assertSentCount(1);
        Mail::assertSent(CoursePartnerProposal::class);
        $this->assertDatabaseHas('course_partner_sending_queue', ['recipient_email' => 'hello@first.example', 'status' => 'sent']);
        $this->assertDatabaseHas('course_partner_sending_queue', ['recipient_email' => 'hello@second.example', 'status' => 'queued']);
    }

    public function test_resumed_queue_still_respects_the_campaign_switch(): void
    {
        $queue = app(CoursePartnerSendingQueue::class);
        $queue->stage($this->prospect('gated'));
        $queue->configure(false, 20, 5);
        config(['course_partner_outreach.enabled' => false]);

        $this->assertNull($queue->dispatchOne(app(CoursePartnerOutreachService::class)));
        Mail::assertNothingSent();
        $this->assertDatabaseHas('course_partner_sending_queue', ['status' => 'queued']);
    }

    public function test_changed_address_fails_closed_without_retrying_or_sending(): void
    {
        $queue = app(CoursePartnerSendingQueue::class);
        $prospect = $this->prospect('changed');
        $queue->stage($prospect);
        $prospect->update(['primary_email' => 'new@changed.example']);
        $queue->configure(false, 20, 1);

        $this->assertSame('failed', $queue->dispatchOne(app(CoursePartnerOutreachService::class)));
        $this->travel(2)->minutes();
        $this->assertNull($queue->dispatchOne(app(CoursePartnerOutreachService::class)));
        Mail::assertNothingSent();
        $this->assertSame(1, DB::table('course_partner_sending_queue')->where('status', 'failed')->count());
    }

    public function test_owner_can_view_and_pause_the_queue_in_miranda(): void
    {
        $this->get('/leads/course-partners/emails')->assertRedirect('/login');
        $owner = User::query()->create([
            'name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'test-only-password',
        ]);
        $this->actingAs($owner)->get('/leads/course-partners/emails')
            ->assertOk()->assertSee('Sending queue');
        $this->post('/leads/course-partners/emails/queue-control', [
            'action' => 'resume', 'daily_limit' => 3, 'spacing_minutes' => 10,
        ])->assertRedirect('/leads/course-partners/emails');
        $this->assertDatabaseHas('course_partner_campaign_controls', [
            'paused' => false, 'daily_limit' => 3, 'spacing_minutes' => 10,
        ]);
    }
}
