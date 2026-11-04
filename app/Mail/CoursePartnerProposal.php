<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class CoursePartnerProposal extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $providerName,
        public string $courseType,
        public string $trackingUrl,
        public string $unsubscribeUrl,
        public int $commissionPercent,
        public int $commissionMonths,
    ) {}

    public function build(): static
    {
        $provider = trim(preg_replace('/[\r\n]+/', ' ', $this->providerName) ?? $this->providerName);

        return $this
            ->from((string) config('course_partner_outreach.sender_address'), (string) config('course_partner_outreach.sender_name'))
            ->replyTo((string) config('course_partner_outreach.reply_to'))
            ->subject("Partnership idea for {$provider}")
            ->view('emails.course-partner-proposal')
            ->text('emails.course-partner-proposal-text')
            ->withSymfonyMessage(function (Email $message): void {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$this->unsubscribeUrl.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            })
            ->with([
                'logoUrl' => (string) config('course_partner_outreach.logo_url'),
                'partnerEnquiryUrl' => (string) config('course_partner_outreach.partner_enquiry_url'),
            ]);
    }
}
