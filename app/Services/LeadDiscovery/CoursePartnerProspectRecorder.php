<?php

namespace App\Services\LeadDiscovery;

use App\Models\BusinessLead;
use App\Models\CoursePartnerProspect;
use App\Models\LeadScanRun;
use Illuminate\Support\Facades\DB;

class CoursePartnerProspectRecorder
{
    /** @param array<string, mixed> $crawlResult */
    public function record(BusinessLead $lead, LeadScanRun $scanRun, array $crawlResult): ?CoursePartnerProspect
    {
        $country = strtoupper((string) $scanRun->course_country);
        $focus = (string) $scanRun->course_focus;
        $host = strtolower((string) parse_url((string) $lead->website, PHP_URL_HOST));
        $domain = preg_replace('/^www\./', '', $host) ?? $host;
        $evidence = collect((array) ($crawlResult['course_evidence'] ?? []))
            ->filter(fn ($row): bool => is_array($row)
                && ($row['focus'] ?? null) === $focus
                && filter_var($row['source_page'] ?? null, FILTER_VALIDATE_URL) !== false)
            ->map(fn (array $row): array => ['focus' => $focus, 'source_page' => (string) $row['source_page']])
            ->values()->all();

        if (! array_key_exists($country, (array) config('leads.course_partner_countries', []))
            || ! in_array($focus, ['nail', 'lash'], true)
            || $domain === '' || strlen($domain) > 255 || $evidence === []) {
            return null;
        }

        $emails = collect((array) ($crawlResult['emails'] ?? []))
            ->filter(fn ($row): bool => is_array($row)
                && filter_var($row['email'] ?? null, FILTER_VALIDATE_EMAIL) !== false)
            ->map(fn (array $row): array => [
                'email' => strtolower(trim((string) $row['email'])),
                'source_page' => (string) ($row['source_page'] ?? ''),
            ])
            ->unique('email')->values()->all();

        return DB::transaction(function () use ($lead, $scanRun, $country, $focus, $domain, $evidence, $emails): CoursePartnerProspect {
            $now = now();
            DB::table('course_partner_prospects')->upsert([[
                'country_code' => $country,
                'domain' => $domain,
                'name' => (string) ($lead->name ?: $domain),
                'website' => (string) $lead->website,
                'business_lead_id' => $lead->id,
                'latest_scan_run_id' => $scanRun->id,
                'review_status' => CoursePartnerProspect::STATUS_NEW,
                'last_discovered_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['country_code', 'domain'], ['last_discovered_at', 'updated_at']);

            $prospect = CoursePartnerProspect::query()
                ->where('country_code', $country)
                ->where('domain', $domain)
                ->lockForUpdate()->firstOrFail();

            $mergedEvidence = collect([...(array) $prospect->course_evidence, ...$evidence])
                ->unique(fn (array $row): string => $row['focus'].'|'.$row['source_page'])
                ->values()->all();
            $mergedEmails = collect([...(array) $prospect->contact_emails, ...$emails])
                ->unique(fn (array $row): string => strtolower((string) $row['email']))
                ->values()->all();
            $preferredEmail = $this->preferredEmail($mergedEmails, $domain);

            $prospect->fill([
                'business_lead_id' => $prospect->business_lead_id ?: $lead->id,
                'latest_scan_run_id' => $scanRun->id,
                'name' => $prospect->name ?: (string) ($lead->name ?: $domain),
                'website' => $prospect->website ?: (string) $lead->website,
                'location' => $prospect->location ?: $scanRun->location,
                'course_focuses' => array_values(array_unique([...(array) $prospect->course_focuses, $focus])),
                'course_evidence' => $mergedEvidence,
                'contact_emails' => $mergedEmails,
                'primary_email' => $prospect->primary_email ?: ($preferredEmail['email'] ?? null),
                'email_source_url' => $prospect->email_source_url ?: ($preferredEmail['source_page'] ?? null),
                'phone' => $prospect->phone ?: $lead->phone,
                'last_discovered_at' => $now,
            ]);
            $prospect->save();
            $prospect->scanRuns()->syncWithoutDetaching([$scanRun->id]);

            return $prospect;
        });
    }

    /** @param array<int, array{email: string, source_page: string}> $emails */
    private function preferredEmail(array $emails, string $domain): ?array
    {
        usort($emails, static function (array $a, array $b) use ($domain): int {
            $score = static function (array $row) use ($domain): int {
                [$local, $mailDomain] = explode('@', $row['email'], 2);
                $role = preg_match('/^(?:training|academy|education|partnerships|info|hello|contact)(?:[._-]|$)/', $local) === 1 ? 2 : 0;
                $sameDomain = $mailDomain === $domain || str_ends_with($mailDomain, '.'.$domain) ? 1 : 0;

                return $role + $sameDomain;
            };

            return $score($b) <=> $score($a);
        });

        return $emails[0] ?? null;
    }
}
