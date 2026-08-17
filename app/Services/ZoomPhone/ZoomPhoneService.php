<?php

namespace App\Services\ZoomPhone;

use App\Models\ZoomCallLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ZoomPhoneService
{
    public function __construct(protected LeadPhoneMatcher $leadPhoneMatcher)
    {
    }

    public function configured(): bool
    {
        return filled(config('zoom-phone.account_id'))
            && filled(config('zoom-phone.client_id'))
            && filled(config('zoom-phone.client_secret'));
    }

    /**
     * @return array{received: int, saved: int, matched: int}
     */
    public function sync(int $days = 7): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Zoom Phone server-to-server OAuth is not configured.');
        }

        $from = now()->subDays(max(1, min($days, 30)))->toDateString();
        $to = now()->toDateString();
        $nextPageToken = null;
        $received = 0;
        $saved = 0;
        $matched = 0;

        do {
            $query = [
                'from' => $from,
                'to' => $to,
                'page_size' => 300,
            ];

            if ($nextPageToken) {
                $query['next_page_token'] = $nextPageToken;
            }

            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout(30)
                ->retry(2, 500)
                ->get(rtrim((string) config('zoom-phone.api_url'), '/').'/v2/phone/call_history', $query)
                ->throw()
                ->json();

            $records = (array) ($response['call_history'] ?? $response['call_logs'] ?? []);
            $received += count($records);

            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $callLog = $this->storeApiRecord($record);
                $saved++;
                $matched += $callLog->business_lead_id ? 1 : 0;
            }

            $nextPageToken = filled($response['next_page_token'] ?? null)
                ? (string) $response['next_page_token']
                : null;
        } while ($nextPageToken);

        return compact('received', 'saved', 'matched');
    }

    public function storeSmartEmbedEvent(array $event, ?int $userId = null): ZoomCallLog
    {
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        $direction = strtolower((string) ($data['direction'] ?? ''));
        $callerNumber = $this->partyNumber($data['caller'] ?? null);
        $calleeNumber = $this->partyNumber($data['callee'] ?? null);
        $externalNumber = $direction === 'inbound' ? $callerNumber : $calleeNumber;
        $identity = (string) ($data['callId'] ?? $data['callLogId'] ?? $event['id'] ?? Str::uuid());
        $lead = $this->leadPhoneMatcher->match($externalNumber);

        return ZoomCallLog::query()->updateOrCreate(
            ['external_key' => 'zoom-call:'.$identity],
            [
                'business_lead_id' => $lead?->id,
                'user_id' => $userId,
                'zoom_call_id' => $data['callId'] ?? null,
                'zoom_call_log_id' => $data['callLogId'] ?? null,
                'source' => 'smart_embed',
                'direction' => $direction ?: null,
                'result' => $data['result'] ?? null,
                'duration_seconds' => isset($data['duration']) ? max(0, (int) $data['duration']) : null,
                'caller_number' => $callerNumber,
                'callee_number' => $calleeNumber,
                'external_number' => $externalNumber,
                'occurred_at' => $this->parseDate($data['dateTime'] ?? null),
                'payload' => $event,
            ]
        );
    }

    protected function storeApiRecord(array $record): ZoomCallLog
    {
        $direction = strtolower((string) ($record['direction'] ?? ''));
        $callerNumber = $record['caller_did_number'] ?? $record['caller_number'] ?? null;
        $calleeNumber = $record['callee_did_number'] ?? $record['callee_number'] ?? null;
        $externalNumber = $direction === 'inbound' ? $callerNumber : $calleeNumber;
        $identity = (string) ($record['call_id'] ?? $record['call_history_uuid'] ?? $record['id'] ?? hash('sha256', json_encode($record)));
        $lead = $this->leadPhoneMatcher->match(is_string($externalNumber) ? $externalNumber : null);
        $duration = $record['duration'] ?? null;

        if ($duration === null && filled($record['start_time'] ?? null) && filled($record['end_time'] ?? null)) {
            $duration = Carbon::parse($record['start_time'])->diffInSeconds(Carbon::parse($record['end_time']));
        }

        return ZoomCallLog::query()->updateOrCreate(
            ['external_key' => 'zoom-call:'.$identity],
            [
                'business_lead_id' => $lead?->id,
                'zoom_call_id' => $record['call_id'] ?? null,
                'zoom_call_log_id' => $record['call_history_uuid'] ?? $record['id'] ?? null,
                'source' => 'api',
                'direction' => $direction ?: null,
                'result' => $record['call_result'] ?? $record['result'] ?? null,
                'duration_seconds' => $duration === null ? null : max(0, (int) $duration),
                'caller_number' => is_string($callerNumber) ? $callerNumber : null,
                'callee_number' => is_string($calleeNumber) ? $calleeNumber : null,
                'external_number' => is_string($externalNumber) ? $externalNumber : null,
                'occurred_at' => $this->parseDate($record['start_time'] ?? $record['date_time'] ?? null),
                'payload' => $record,
            ]
        );
    }

    protected function accessToken(): string
    {
        return Cache::remember('zoom-phone.access-token', now()->addMinutes(50), function (): string {
            $response = Http::withBasicAuth(
                (string) config('zoom-phone.client_id'),
                (string) config('zoom-phone.client_secret')
            )->asForm()->timeout(20)->post((string) config('zoom-phone.oauth_url'), [
                'grant_type' => 'account_credentials',
                'account_id' => (string) config('zoom-phone.account_id'),
            ])->throw()->json();

            $token = $response['access_token'] ?? null;

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Zoom did not return an access token.');
            }

            return $token;
        });
    }

    protected function partyNumber(mixed $party): ?string
    {
        if (! is_array($party)) {
            return null;
        }

        $number = $party['number'] ?? $party['phoneNumber'] ?? null;

        return is_string($number) && $number !== '' ? $number : null;
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
