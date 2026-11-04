<?php

namespace App\Http\Controllers;

use App\Models\CoursePartnerOutreachEmail;
use App\Services\CoursePartnerSendingQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CoursePartnerEmailActivityController extends Controller
{
    public function __invoke(): View
    {
        $ready = Schema::hasTable('course_partner_outreach_emails');
        $queueReady = Schema::hasTable('course_partner_sending_queue') && Schema::hasTable('course_partner_campaign_controls');
        $campaign = (string) config('course_partner_outreach.campaign_key');

        return view('leads.course-partner-email-activity', [
            'ready' => $ready,
            'emails' => $ready ? CoursePartnerOutreachEmail::query()
                ->with('prospect:id,name,country_code')
                ->orderByDesc('id')->paginate(50) : null,
            'queueReady' => $queueReady,
            'queueControl' => $queueReady ? DB::table('course_partner_campaign_controls')->where('campaign_key', $campaign)->first() : null,
            'queueCounts' => $queueReady ? DB::table('course_partner_sending_queue')->where('campaign_key', $campaign)
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') : collect(),
            'queueRows' => $queueReady ? DB::table('course_partner_sending_queue as q')
                ->join('course_partner_prospects as p', 'p.id', '=', 'q.course_partner_prospect_id')
                ->where('q.campaign_key', $campaign)
                ->orderByDesc('q.id')->limit(30)
                ->get(['q.id', 'q.recipient_email', 'q.status', 'q.error_code', 'q.claimed_at', 'q.sent_at', 'p.name']) : collect(),
        ]);
    }

    public function control(Request $request, CoursePartnerSendingQueue $queue): RedirectResponse
    {
        abort_unless(Schema::hasTable('course_partner_sending_queue'), 503);
        $data = $request->validate([
            'action' => ['required', 'in:pause,resume'],
            'daily_limit' => ['required', 'integer', 'between:1,100'],
            'spacing_minutes' => ['required', 'integer', 'between:1,1440'],
        ]);
        $queue->configure($data['action'] === 'pause', (int) $data['daily_limit'], (int) $data['spacing_minutes']);

        return redirect()->route('leads.course-partners.emails')->with('status', 'Sending queue updated.');
    }
}
