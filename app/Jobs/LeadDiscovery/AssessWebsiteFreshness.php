<?php

namespace App\Jobs\LeadDiscovery;

use App\Models\BusinessLead;
use App\Services\LeadDiscovery\WebsiteFreshnessAssessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AssessWebsiteFreshness implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public int $businessLeadId)
    {
        $this->onQueue('lead-freshness');
    }

    public function uniqueId(): string
    {
        return (string) $this->businessLeadId;
    }

    public function handle(WebsiteFreshnessAssessor $assessor): void
    {
        $lead = BusinessLead::query()->find($this->businessLeadId);

        if (! $lead || ! filled($lead->website)) {
            return;
        }

        $lead->update($assessor->assess((string) $lead->website));
    }
}
