<?php

namespace App\Console\Commands;

use App\Models\BusinessLead;
use App\Services\LeadDiscovery\PlaceDetailsService;
use Illuminate\Console\Command;

class RefreshLeadPhoneNumbersCommand extends Command
{
    protected $signature = 'leads:refresh-phone-numbers {--lead= : Refresh one lead by ID instead of every lead}';

    protected $description = 'Refresh lead contact details from Google Places and prefer international phone formatting';

    public function handle(PlaceDetailsService $placeDetailsService): int
    {
        $leadId = $this->option('lead');
        $query = BusinessLead::query()->when($leadId, fn ($builder) => $builder->whereKey($leadId));
        $count = 0;

        $query->orderBy('id')->each(function (BusinessLead $lead) use ($placeDetailsService, &$count): void {
            $placeDetailsService->enrichBusinessLead($lead);
            $count++;
        });

        $this->info("Refreshed {$count} lead phone number(s).");

        return self::SUCCESS;
    }
}
