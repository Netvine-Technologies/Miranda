<?php

namespace App\Console\Commands;

use App\Models\CoursePartnerProspect;
use App\Services\CoursePartnerSendingQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ManageCoursePartnerQueue extends Command
{
    protected $signature = 'course-partners:queue
        {action : status, stage, pause, or resume}
        {ids?* : Reviewed prospect IDs for stage}
        {--daily-limit= : Maximum attempts per UTC day, 1 to 100}
        {--spacing-minutes= : Minimum minutes between attempts, 1 to 1440}';

    protected $description = 'Stage and control the educator email sending queue';

    public function handle(CoursePartnerSendingQueue $queue): int
    {
        $action = (string) $this->argument('action');
        $ids = $this->argument('ids');
        if (! in_array($action, ['status', 'stage', 'pause', 'resume'], true)
            || ($action !== 'stage' && $ids !== [])
            || ($action === 'stage' && $ids === [])) {
            $this->error('Use status, pause, resume, or stage with one or more prospect IDs.');

            return self::FAILURE;
        }

        try {
            $control = $queue->control();
            if ($action === 'stage') {
                foreach ($ids as $id) {
                    if (! ctype_digit((string) $id) || (int) $id < 1) {
                        throw new \LogicException('Prospect IDs must be positive integers.');
                    }
                    $entryId = $queue->stage(CoursePartnerProspect::query()->findOrFail((int) $id));
                    $this->line("Prospect {$id} staged as queue entry {$entryId}.");
                }
            } elseif ($action === 'pause' || $action === 'resume') {
                $daily = $this->option('daily-limit') ?? $control->daily_limit;
                $spacing = $this->option('spacing-minutes') ?? $control->spacing_minutes;
                if (! ctype_digit((string) $daily) || ! ctype_digit((string) $spacing)) {
                    throw new \LogicException('Limits must be positive integers.');
                }
                $queue->configure($action === 'pause', (int) $daily, (int) $spacing);
                $this->info('Queue '.($action === 'pause' ? 'paused' : 'resumed').'.');
            }

            $control = $queue->control();
            $this->line('Queue: '.($control->paused ? 'paused' : 'running'));
            $this->line('Prospect sending: '.(config('course_partner_outreach.enabled') ? 'enabled' : 'disabled'));
            $this->line("Daily attempt limit: {$control->daily_limit}; spacing: {$control->spacing_minutes} minutes");
            $counts = DB::table('course_partner_sending_queue')->where('campaign_key', config('course_partner_outreach.campaign_key'))
                ->selectRaw('status, count(*) as total')->groupBy('status')->orderBy('status')->get();
            foreach ($counts as $row) {
                $this->line("{$row->status}: {$row->total}");
            }
            $this->line('Attempts today (UTC): '.DB::table('course_partner_sending_queue')
                ->where('campaign_key', config('course_partner_outreach.campaign_key'))
                ->where('claimed_at', '>=', now()->startOfDay())->count());
        } catch (Throwable $exception) {
            $this->error('Queue operation failed: '.class_basename($exception));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
