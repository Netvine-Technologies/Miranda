<?php

namespace App\Services;

use App\Models\CoursePartnerOutreachEmail;
use App\Models\CoursePartnerProspect;
use App\Models\CoursePartnerSuppression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use LogicException;
use Throwable;

class CoursePartnerSendingQueue
{
    private function campaignKey(): string
    {
        return (string) config('course_partner_outreach.campaign_key');
    }

    public function control(): object
    {
        DB::table('course_partner_campaign_controls')->insertOrIgnore([
            'campaign_key' => $this->campaignKey(),
            'paused' => true,
            'daily_limit' => 20,
            'spacing_minutes' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('course_partner_campaign_controls')->where('campaign_key', $this->campaignKey())->first();
    }

    public function configure(bool $paused, int $dailyLimit, int $spacingMinutes): void
    {
        if ($dailyLimit < 1 || $dailyLimit > 100 || $spacingMinutes < 1 || $spacingMinutes > 1440) {
            throw new LogicException('Daily limit must be 1–100 and spacing must be 1–1440 minutes.');
        }

        $this->control();
        DB::transaction(function () use ($paused, $dailyLimit, $spacingMinutes): void {
            DB::table('course_partner_campaign_controls')->where('campaign_key', $this->campaignKey())
                ->lockForUpdate()->first();
            DB::table('course_partner_campaign_controls')->where('campaign_key', $this->campaignKey())->update([
                'paused' => $paused,
                'daily_limit' => $dailyLimit,
                'spacing_minutes' => $spacingMinutes,
                'updated_at' => now(),
            ]);
        });
    }

    public function stage(CoursePartnerProspect $prospect): int
    {
        $address = strtolower(trim((string) $prospect->primary_email));
        if ($prospect->review_status !== CoursePartnerProspect::STATUS_REVIEWED
            || ! $prospect->reviewed_at
            || filter_var($address, FILTER_VALIDATE_EMAIL) === false
            || (array) $prospect->course_evidence === []
            || ! array_intersect(['nail', 'lash'], (array) $prospect->course_focuses)) {
            throw new LogicException('Prospect needs a reviewed nail/lash course and valid contact email.');
        }
        if (CoursePartnerSuppression::query()->where('email', $address)->exists()
            || CoursePartnerOutreachEmail::query()->where('campaign_key', $this->campaignKey())
                ->where(fn ($query) => $query->where('course_partner_prospect_id', $prospect->id)
                    ->orWhere('recipient_email', $address))->exists()) {
            throw new LogicException('Prospect or address is suppressed or already emailed.');
        }

        $this->control();

        return DB::table('course_partner_sending_queue')->insertGetId([
            'course_partner_prospect_id' => $prospect->id,
            'campaign_key' => $this->campaignKey(),
            'recipient_email' => $address,
            'status' => 'queued',
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function dispatchOne(CoursePartnerOutreachService $outreach): ?string
    {
        $this->control();
        $claimed = DB::transaction(function (): ?object {
            $control = DB::table('course_partner_campaign_controls')
                ->where('campaign_key', $this->campaignKey())->lockForUpdate()->first();
            if (! $control || $control->paused || ! config('course_partner_outreach.enabled')) {
                return null;
            }
            if ($control->last_attempt_at && Carbon::parse($control->last_attempt_at)->addMinutes($control->spacing_minutes)->isFuture()) {
                return null;
            }
            $attemptsToday = DB::table('course_partner_sending_queue')->where('campaign_key', $this->campaignKey())
                ->where('claimed_at', '>=', now()->startOfDay())->count();
            if ($attemptsToday >= $control->daily_limit) {
                return null;
            }
            $entry = DB::table('course_partner_sending_queue')->where('campaign_key', $this->campaignKey())
                ->where('status', 'queued')->where('available_at', '<=', now())
                ->orderBy('id')->lockForUpdate()->first();
            if (! $entry) {
                return null;
            }
            DB::table('course_partner_sending_queue')->where('id', $entry->id)->update([
                'status' => 'claimed', 'claimed_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('course_partner_campaign_controls')->where('campaign_key', $this->campaignKey())->update([
                'last_attempt_at' => now(), 'updated_at' => now(),
            ]);

            return $entry;
        });

        if (! $claimed) {
            return null;
        }

        try {
            $prospect = CoursePartnerProspect::query()->findOrFail($claimed->course_partner_prospect_id);
            if (strtolower(trim((string) $prospect->primary_email)) !== $claimed->recipient_email) {
                throw new LogicException('Contact email changed after staging.');
            }
            if (! $prospect->reviewed_at || ! array_intersect(['nail', 'lash'], (array) $prospect->course_focuses)) {
                throw new LogicException('Reviewed course focus changed after staging.');
            }
            $email = $outreach->sendProspect($prospect);
            DB::table('course_partner_sending_queue')->where('id', $claimed->id)->update([
                'status' => 'sent', 'outreach_email_id' => $email->id, 'sent_at' => now(), 'updated_at' => now(),
            ]);

            return 'sent';
        } catch (Throwable $exception) {
            DB::table('course_partner_sending_queue')->where('id', $claimed->id)->update([
                'status' => 'failed', 'error_code' => class_basename($exception), 'updated_at' => now(),
            ]);

            return 'failed';
        }
    }
}
