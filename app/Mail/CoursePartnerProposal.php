<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CoursePartnerProposal extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $providerName,
        public string $courseType,
        public string $trackingUrl,
        public int $commissionPercent,
        public int $commissionMonths,
    ) {}

    public function build(): static
    {
        $provider = trim(preg_replace('/[\r\n]+/', ' ', $this->providerName) ?? $this->providerName);

        return $this
            ->from((string) config('course_partner_outreach.sender_address'), (string) config('course_partner_outreach.sender_name'))
            ->replyTo((string) config('course_partner_outreach.reply_to'))
            ->subject("Partnership idea for {$provider} graduates")
            ->view('emails.course-partner-proposal')
            ->text('emails.course-partner-proposal-text')
            ->with(['logoUrl' => (string) config('course_partner_outreach.logo_url')]);
    }
}
