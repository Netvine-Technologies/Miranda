<?php

namespace App\Console\Commands;

use App\Services\CoursePartnerOutreachService;
use App\Services\CoursePartnerSendingQueue;
use Illuminate\Console\Command;

class DispatchCoursePartnerQueue extends Command
{
    protected $signature = 'course-partners:dispatch-one';

    protected $description = 'Attempt at most one staged educator email within queue limits';

    public function handle(CoursePartnerSendingQueue $queue, CoursePartnerOutreachService $outreach): int
    {
        $result = $queue->dispatchOne($outreach);
        $this->line($result ?? 'No send due.');

        return $result === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
