<?php

namespace App\Http\Controllers;

use App\Models\CoursePartnerProspect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoursePartnerProspectController extends Controller
{
    public function index(Request $request): View
    {
        $ready = Schema::hasTable('course_partner_prospects');
        $outreachReady = Schema::hasTable('course_partner_outreach_emails');
        $filters = $this->filters($request);

        return view('leads.course-partners', [
            'ready' => $ready,
            'prospects' => $ready ? $this->filteredQuery($filters)
                ->when($outreachReady, fn (Builder $query) => $query->with('latestOutreachEmail'))
                ->orderByDesc('last_discovered_at')->paginate(30)->withQueryString() : null,
            'filters' => $filters,
            'outreachReady' => $outreachReady,
            'countries' => (array) config('leads.course_partner_countries', []),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, CoursePartnerProspect $coursePartnerProspect): RedirectResponse
    {
        $data = $request->validate([
            'review_status' => ['required', Rule::in($this->statuses())],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $coursePartnerProspect->update([
            'review_status' => $data['review_status'],
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_at' => $data['review_status'] === CoursePartnerProspect::STATUS_NEW ? null : now(),
        ]);

        return back()->with('status', 'Course partner prospect updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(Schema::hasTable('course_partner_prospects'), 404);
        $filters = $this->filters($request);

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['id', 'country_code', 'name', 'domain', 'website', 'location', 'course_focuses', 'course_evidence_urls', 'email', 'email_source_url', 'phone', 'review_status', 'review_notes', 'last_discovered_at'], ',', '"', '');
            $this->filteredQuery($filters)->chunkById(100, function ($prospects) use ($handle): void {
                foreach ($prospects as $prospect) {
                    $evidenceUrls = collect((array) $prospect->course_evidence)->pluck('source_page')->filter()->unique()->implode('; ');
                    $contacts = (array) $prospect->contact_emails;
                    foreach ($contacts === [] ? [['email' => null, 'source_page' => null]] : $contacts as $contact) {
                        fputcsv($handle, array_map($this->safeCsvCell(...), [
                            $prospect->id,
                            $prospect->country_code,
                            $prospect->name,
                            $prospect->domain,
                            $prospect->website,
                            $prospect->location,
                            implode('; ', (array) $prospect->course_focuses),
                            $evidenceUrls,
                            $contact['email'] ?? null,
                            $contact['source_page'] ?? null,
                            $prospect->phone,
                            $prospect->review_status,
                            $prospect->review_notes,
                            optional($prospect->last_discovered_at)?->toDateTimeString(),
                        ]), ',', '"', '');
                    }
                }
            });

            fclose($handle);
        }, 'miranda-course-partner-prospects.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{country: string|null, focus: string|null, status: string|null, contact: string|null} */
    private function filters(Request $request): array
    {
        $country = (string) $request->query('country', '');
        $focus = (string) $request->query('focus', '');
        $status = (string) $request->query('status', '');
        $contact = (string) $request->query('contact', '');

        return [
            'country' => array_key_exists($country, (array) config('leads.course_partner_countries', [])) ? $country : null,
            'focus' => in_array($focus, ['nail', 'lash'], true) ? $focus : null,
            'status' => in_array($status, $this->statuses(), true) ? $status : null,
            'contact' => in_array($contact, ['yes', 'no'], true) ? $contact : null,
        ];
    }

    /** @param array{country: string|null, focus: string|null, status: string|null, contact: string|null} $filters */
    private function filteredQuery(array $filters): Builder
    {
        return CoursePartnerProspect::query()
            ->when($filters['country'], fn (Builder $query, string $country) => $query->where('country_code', $country))
            ->when($filters['focus'], fn (Builder $query, string $focus) => $query->whereJsonContains('course_focuses', $focus))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('review_status', $status))
            ->when($filters['contact'] === 'yes', fn (Builder $query) => $query->whereNotNull('primary_email'))
            ->when($filters['contact'] === 'no', fn (Builder $query) => $query->whereNull('primary_email'));
    }

    /** @return array<int, string> */
    private function statuses(): array
    {
        return [
            CoursePartnerProspect::STATUS_NEW,
            CoursePartnerProspect::STATUS_REVIEWED,
            CoursePartnerProspect::STATUS_REJECTED,
            CoursePartnerProspect::STATUS_CONTACTED,
            CoursePartnerProspect::STATUS_REPLIED,
            CoursePartnerProspect::STATUS_PARTNER,
        ];
    }

    private function safeCsvCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@-]/u', $text) === 1 ? "'".$text : $text;
    }
}
