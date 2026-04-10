<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LeadScanRun extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'query',
        'location',
        'status',
        'total_places_found',
        'details_processed',
        'websites_queued',
        'websites_crawled',
        'emails_found',
        'phone_numbers_found',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'total_places_found' => 'integer',
            'details_processed' => 'integer',
            'websites_queued' => 'integer',
            'websites_crawled' => 'integer',
            'emails_found' => 'integer',
            'phone_numbers_found' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function incrementCounters(int $emailsAdded = 0, int $phonesAdded = 0, bool $crawled = false): void
    {
        $updates = [];

        if ($emailsAdded > 0) {
            $updates['emails_found'] = DB::raw('emails_found + '.$emailsAdded);
        }

        if ($phonesAdded > 0) {
            $updates['phone_numbers_found'] = DB::raw('phone_numbers_found + '.$phonesAdded);
        }

        if ($crawled) {
            $updates['websites_crawled'] = DB::raw('websites_crawled + 1');
        }

        if ($updates === []) {
            return;
        }

        static::query()->whereKey($this->id)->update($updates);
    }
}
