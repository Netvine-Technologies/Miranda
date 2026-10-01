<?php

namespace App\Console\Commands;

use App\Models\CoursePartnerProspect;
use App\Services\CoursePartnerOutreachService;
use Illuminate\Console\Command;
use Throwable;

class SendCoursePartnerEmail extends Command
{
    protected $signature = 'course-partners:email {--review-to= : Send the fictional template to a review inbox} {--prospect= : Send to one reviewed prospect by ID}';

    protected $description = 'Send one tracked Course Partner Programme email; prospect sending requires the campaign switch';

    public function handle(CoursePartnerOutreachService $outreach): int
    {
        $reviewTo = trim((string) $this->option('review-to'));
        $prospectId = trim((string) $this->option('prospect'));
        if (($reviewTo === '') === ($prospectId === '')) {
            $this->error('Provide exactly one of --review-to or --prospect.');

            return self::FAILURE;
        }
        if ($prospectId !== '' && (! ctype_digit($prospectId) || (int) $prospectId < 1)) {
            $this->error('The prospect ID must be a positive integer.');

            return self::FAILURE;
        }

        try {
            $record = $reviewTo !== ''
                ? $outreach->sendPreview($reviewTo)
                : $outreach->sendProspect(CoursePartnerProspect::query()->findOrFail((int) $prospectId));
        } catch (Throwable $exception) {
            $this->error('Email was not confirmed sent. Check configuration and the email record before retrying.');

            return self::FAILURE;
        }

        $this->info("Email {$record->id} sent. Open status will appear in Course Partner Prospects.");

        return self::SUCCESS;
    }
}
