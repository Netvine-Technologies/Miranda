<?php

namespace App\Services;

use App\Mail\CoursePartnerProposal;
use App\Models\CoursePartnerOutreachEmail;
use App\Models\CoursePartnerProspect;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class CoursePartnerOutreachService
{
    public function sendPreview(string $address): CoursePartnerOutreachEmail
    {
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new LogicException('A valid review email address is required.');
        }
        if (strcasecmp($address, (string) config('course_partner_outreach.review_address')) !== 0) {
            throw new LogicException('Preview sending is restricted to the configured review inbox.');
        }

        return $this->send(null, strtolower($address), 'Glow Beauty Academy', 'nail technician');
    }

    public function sendProspect(CoursePartnerProspect $prospect): CoursePartnerOutreachEmail
    {
        if (! config('course_partner_outreach.enabled')) {
            throw new LogicException('Course partner outreach is disabled.');
        }
        if ($prospect->review_status !== CoursePartnerProspect::STATUS_REVIEWED) {
            throw new LogicException('The prospect must be reviewed before sending.');
        }
        if (! $prospect->primary_email || filter_var($prospect->primary_email, FILTER_VALIDATE_EMAIL) === false) {
            throw new LogicException('The prospect has no valid public email.');
        }
        if ((array) $prospect->course_evidence === []) {
            throw new LogicException('The prospect has no verified course page.');
        }
        if ($prospect->outreachEmails()->where('campaign_key', config('course_partner_outreach.campaign_key'))->exists()) {
            throw new LogicException('This prospect already has an email record for this campaign.');
        }

        $focuses = (array) $prospect->course_focuses;
        $courseType = in_array('nail', $focuses, true) ? 'nail technician' : 'lash technician';

        return $this->send($prospect, strtolower($prospect->primary_email), $prospect->name, $courseType);
    }

    private function send(?CoursePartnerProspect $prospect, string $address, string $provider, string $courseType): CoursePartnerOutreachEmail
    {
        $baseUrl = rtrim((string) config('course_partner_outreach.tracking_base_url'), '/');
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL) || parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new LogicException('Set COURSE_PARTNER_TRACKING_BASE_URL to a public HTTPS address before sending.');
        }
        if (! config('services.mailgun.domain') || ! config('services.mailgun.secret')) {
            throw new LogicException('The Mailgun domain and API key must be configured in Miranda before sending.');
        }
        if (config('services.mailgun.endpoint') !== 'api.eu.mailgun.net') {
            throw new LogicException('Use the Booking Tree Mailgun EU endpoint before sending.');
        }
        $percent = (int) config('course_partner_outreach.commission_percent');
        $months = (int) config('course_partner_outreach.commission_months');
        if ($percent < 1 || $percent > 100 || $months < 1) {
            throw new LogicException('Course partner commission terms are invalid.');
        }

        $provider = Str::limit(trim(preg_replace('/[\r\n\x00-\x1f]+/', ' ', $provider) ?? $provider), 120, '');
        $token = bin2hex(random_bytes(32));
        $subject = "Partnership idea for {$provider} graduates";
        $record = CoursePartnerOutreachEmail::query()->create([
            'course_partner_prospect_id' => $prospect?->id,
            'campaign_key' => (string) config('course_partner_outreach.campaign_key'),
            'recipient_email' => $address,
            'subject' => $subject,
            'tracking_token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'commission_percent' => $percent,
            'commission_months' => $months,
        ]);
        $trackingUrl = $baseUrl.'/course-partner-email/open/'.$token.'.gif';

        try {
            Mail::mailer('mailgun')->to($address)->send(new CoursePartnerProposal($provider, $courseType, $trackingUrl, $percent, $months));
            $record->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $record->update(['status' => 'failed', 'error_message' => class_basename($exception)]);
            throw $exception;
        }

        return $record;
    }
}
