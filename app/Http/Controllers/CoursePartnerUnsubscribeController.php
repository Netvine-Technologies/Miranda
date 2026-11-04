<?php

namespace App\Http\Controllers;

use App\Models\CoursePartnerOutreachEmail;
use App\Models\CoursePartnerSuppression;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class CoursePartnerUnsubscribeController extends Controller
{
    public function show(string $token): Response
    {
        $valid = Schema::hasColumn('course_partner_outreach_emails', 'unsubscribe_token_hash')
            && CoursePartnerOutreachEmail::query()
            ->where('unsubscribe_token_hash', hash('sha256', $token))
            ->exists();

        return response()->view('emails.course-partner-unsubscribe', [
            'valid' => $valid,
            'token' => $token,
        ], $valid ? 200 : 404);
    }

    public function store(string $token): Response
    {
        $email = Schema::hasColumn('course_partner_outreach_emails', 'unsubscribe_token_hash')
            && Schema::hasTable('course_partner_suppressions')
            ? CoursePartnerOutreachEmail::query()
            ->where('unsubscribe_token_hash', hash('sha256', $token))
            ->value('recipient_email') : null;

        if ($email) {
            CoursePartnerSuppression::query()->firstOrCreate(
                ['email' => strtolower($email)],
                ['reason' => 'unsubscribed'],
            );
        }

        return response()->view('emails.course-partner-unsubscribe', [
            'valid' => (bool) $email,
            'done' => (bool) $email,
            'token' => $token,
        ], $email ? 200 : 404);
    }
}
