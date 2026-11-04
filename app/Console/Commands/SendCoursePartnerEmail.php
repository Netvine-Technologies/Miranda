<?php

namespace App\Console\Commands;

use App\Models\CoursePartnerProspect;
use App\Services\CoursePartnerOutreachService;
use Illuminate\Console\Command;
use Throwable;

class SendCoursePartnerEmail extends Command
{
    protected $signature = 'course-partners:email {--review-to= : Send the fictional template to a review inbox} {--review-focus=nail : Review copy focus: nail, lash, or both} {--prospect= : Send to one reviewed prospect by ID}';

    protected $description = 'Send one tracked Course Partner Programme review copy; provider sends use the controlled queue';

    public function handle(CoursePartnerOutreachService $outreach): int
    {
        $reviewTo = trim((string) $this->option('review-to'));
        $reviewFocus = trim((string) $this->option('review-focus'));
        $prospectId = trim((string) $this->option('prospect'));
        if (($reviewTo === '') === ($prospectId === '')) {
            $this->error('Provide exactly one of --review-to or --prospect.');

            return self::FAILURE;
        }
        if ($prospectId !== '' && (! ctype_digit($prospectId) || (int) $prospectId < 1)) {
            $this->error('The prospect ID must be a positive integer.');

            return self::FAILURE;
        }
        if ($prospectId !== '') {
            $this->error('Stage reviewed prospects with course-partners:queue stage; direct provider sending is disabled.');

            return self::FAILURE;
        }
        if ($reviewTo === '' && $reviewFocus !== 'nail') {
            $this->error('--review-focus can only be used with --review-to.');

            return self::FAILURE;
        }
        if ($reviewTo !== '' && ! in_array($reviewFocus, ['nail', 'lash', 'both'], true)) {
            $this->error('Review focus must be nail, lash, or both.');

            return self::FAILURE;
        }

        try {
            $record = $reviewTo !== ''
                ? $outreach->sendPreview($reviewTo, $reviewFocus)
                : $outreach->sendProspect(CoursePartnerProspect::query()->findOrFail((int) $prospectId));
        } catch (Throwable $exception) {
            $this->error('Email was not confirmed sent. Check configuration and the email record before retrying.');

            return self::FAILURE;
        }

        $this->info("Email {$record->id} sent. Open status will appear in Course Partner Prospects.");

        return self::SUCCESS;
    }
}
