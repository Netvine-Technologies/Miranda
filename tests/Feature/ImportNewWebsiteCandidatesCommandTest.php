<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\QualifyNewWebsiteCandidate;
use App\Models\NewWebsiteCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportNewWebsiteCandidatesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_imports_and_queues_only_high_intent_new_domains(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00 UTC');
        Queue::fake();
        Http::fake([
            'https://newly-registered-domains.whoisxmlapi.com/sample/*' => Http::response(implode("\n", [
                'reason,domainName',
                'added,newcitydentalclinic.com',
                'added,unrelatedletters.com',
            ]), 200, ['Content-Type' => 'text/csv']),
        ]);

        $this->artisan('leads:import-new-websites', ['--date' => '2026-09-02'])
            ->expectsOutput('Imported 1 new candidate(s); queued 1 qualification job(s).')
            ->assertSuccessful();

        $candidate = NewWebsiteCandidate::query()->sole();
        $this->assertSame('newcitydentalclinic.com', $candidate->domain);
        $this->assertSame(NewWebsiteCandidate::STATUS_QUEUED, $candidate->status);
        Queue::assertPushed(QualifyNewWebsiteCandidate::class, fn (QualifyNewWebsiteCandidate $job): bool => $job->candidateId === $candidate->id);
    }
}
