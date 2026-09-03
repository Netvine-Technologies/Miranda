<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\QualifyNewWebsiteCandidate;
use App\Models\NewWebsiteCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueNewWebsiteCandidatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_pending_candidates_in_priority_order(): void
    {
        Queue::fake();
        $lower = NewWebsiteCandidate::create([
            'domain' => 'newcafe.example',
            'source' => 'test',
            'status' => NewWebsiteCandidate::STATUS_PENDING,
            'priority_score' => 60,
        ]);
        $higher = NewWebsiteCandidate::create([
            'domain' => 'newdental.example',
            'source' => 'test',
            'status' => NewWebsiteCandidate::STATUS_PENDING,
            'priority_score' => 80,
        ]);

        $this->artisan('leads:queue-new-websites', ['--limit' => 1])
            ->expectsOutput('Queued 1 new website qualification job(s).')
            ->assertSuccessful();

        $this->assertSame(NewWebsiteCandidate::STATUS_PENDING, $lower->fresh()->status);
        $this->assertSame(NewWebsiteCandidate::STATUS_QUEUED, $higher->fresh()->status);
        Queue::assertPushed(QualifyNewWebsiteCandidate::class, fn (QualifyNewWebsiteCandidate $job): bool => $job->candidateId === $higher->id);
    }
}
