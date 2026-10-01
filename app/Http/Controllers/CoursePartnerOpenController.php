<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoursePartnerOpenController extends Controller
{
    public function __invoke(string $token): Response
    {
        if (Schema::hasTable('course_partner_outreach_emails')) {
            DB::table('course_partner_outreach_emails')
                ->where('tracking_token_hash', hash('sha256', $token))
                ->update([
                    'first_opened_at' => DB::raw('COALESCE(first_opened_at, CURRENT_TIMESTAMP)'),
                    'last_opened_at' => now(),
                    'open_count' => DB::raw('open_count + 1'),
                    'updated_at' => now(),
                ]);
        }

        $pixel = base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', true);

        return response($pixel === false ? '' : $pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
