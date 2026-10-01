<?php

namespace App\Http\Controllers;

use App\Models\CoursePartnerOutreachEmail;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CoursePartnerEmailActivityController extends Controller
{
    public function __invoke(): View
    {
        $ready = Schema::hasTable('course_partner_outreach_emails');

        return view('leads.course-partner-email-activity', [
            'ready' => $ready,
            'emails' => $ready ? CoursePartnerOutreachEmail::query()
                ->with('prospect:id,name,country_code')
                ->orderByDesc('id')->paginate(50) : null,
        ]);
    }
}
