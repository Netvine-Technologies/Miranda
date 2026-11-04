<?php

namespace App\Services;

use App\Mail\CoursePartnerProposal;
use App\Models\CoursePartnerOutreachEmail;
use App\Models\CoursePartnerProspect;
use App\Models\CoursePartnerSuppression;
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
        if (CoursePartnerSuppression::query()->where('email', strtolower($prospect->primary_email))->exists()) {
            throw new LogicException('This recipient has opted out or been rejected.');
        }
        if ($prospect->outreachEmails()->where('campaign_key', config('course_partner_outreach.campaign_key'))->exists()) {
            throw new LogicException('This prospect already has an email record for this campaign.');
        }
        if (CoursePartnerOutreachEmail::query()
            ->where('campaign_key', config('course_partner_outreach.campaign_key'))
            ->where('recipient_email', strtolower($prospect->primary_email))
            ->exists()) {
            throw new LogicException('This email address already has a record for this campaign.');
        }

        $focuses = (array) $prospect->course_focuses;
        $courseType = match (true) {
            in_array('nail', $focuses, true) && in_array('lash', $focuses, true) => 'nail and lash',
            in_array('nail', $focuses, true) => 'nail technician',
            in_array('lash', $focuses, true) => 'lash technician',
            default => 'general',
        };

        return $this->send($prospect, strtolower($prospect->primary_email), $prospect->name, $courseType);
    }

    private function send(?CoursePartnerProspect $prospect, string $address, string $provider, string $courseType): CoursePartnerOutreachEmail
    {
        $baseUrl = rtrim((string) config('course_partner_outreach.tracking_base_url'), '/');
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)
            || ! in_array($scheme, $prospect === null ? ['http', 'https'] : ['https'], true)) {
            throw new LogicException('Set COURSE_PARTNER_TRACKING_BASE_URL to a public HTTPS address for provider sending.');
        }
        $mailer = (string) config('course_partner_outreach.mailer');
        if ($mailer !== 'course_partner_smtp') {
            throw new LogicException('Course partner email requires the dedicated SMTP mailer.');
        }
        $smtp = (array) config('mail.mailers.course_partner_smtp');
        if (! in_array($smtp['scheme'] ?? null, ['smtps', 'smtp'], true)
            || ! in_array((int) ($smtp['port'] ?? 0), [465, 587], true)
            || ! is_string($smtp['host'] ?? null) || trim($smtp['host']) === ''
            || ! is_string($smtp['username'] ?? null) || trim($smtp['username']) === ''
            || ! is_string($smtp['password'] ?? null) || $smtp['password'] === '') {
            throw new LogicException('Configure the dedicated course partner SMTP mailbox before sending.');
        }
        if (($smtp['scheme'] === 'smtps' && (int) $smtp['port'] !== 465)
            || ($smtp['scheme'] === 'smtp' && (int) $smtp['port'] !== 587)) {
            throw new LogicException('Use port 465 for SMTPS, or port 587 for SMTP with STARTTLS.');
        }
        if (filter_var((string) config('course_partner_outreach.sender_address'), FILTER_VALIDATE_EMAIL) === false) {
            throw new LogicException('Configure a valid From address authorised for the SMTP mailbox.');
        }
        $percent = (int) config('course_partner_outreach.commission_percent');
        $months = (int) config('course_partner_outreach.commission_months');
        if ($percent < 1 || $percent > 100 || $months < 1) {
            throw new LogicException('Course partner commission terms are invalid.');
        }

        $provider = Str::limit(trim(preg_replace('/[\r\n\x00-\x1f]+/', ' ', $provider) ?? $provider), 120, '');
        $token = bin2hex(random_bytes(32));
        $unsubscribeToken = bin2hex(random_bytes(32));
        $subject = "Partnership idea for {$provider}";
        $record = CoursePartnerOutreachEmail::query()->create([
            'course_partner_prospect_id' => $prospect?->id,
            'campaign_key' => (string) config('course_partner_outreach.campaign_key'),
            'recipient_email' => $address,
            'subject' => $subject,
            'tracking_token_hash' => hash('sha256', $token),
            'unsubscribe_token_hash' => hash('sha256', $unsubscribeToken),
            'status' => 'pending',
            'commission_percent' => $percent,
            'commission_months' => $months,
        ]);
        $trackingUrl = $baseUrl.'/course-partner-email/open/'.$token.'.gif';
        $unsubscribeUrl = $baseUrl.'/course-partner-email/unsubscribe/'.$unsubscribeToken;

        try {
            Mail::mailer($mailer)->to($address)->send(new CoursePartnerProposal($provider, $courseType, $trackingUrl, $unsubscribeUrl, $percent, $months));
            $record->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $record->update(['status' => 'failed', 'error_message' => class_basename($exception)]);
            throw $exception;
        }

        return $record;
    }
}
