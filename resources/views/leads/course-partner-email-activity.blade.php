<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Course Partner Email Activity · Miranda</title>
<style>
body{margin:0;background:#f3f7f5;color:#1f2937;font:14px/1.5 Arial,Helvetica,sans-serif}.wrap{max-width:1200px;margin:28px auto;padding:0 16px}.card{background:#fff;border:1px solid #d8e5de;border-radius:12px;padding:20px}h1{margin:0 0 8px;color:#0b5138;font-size:26px}p{margin:6px 0 14px}.muted{color:#64748b}a{color:#0b7a50}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:850px}th,td{text-align:left;vertical-align:top;padding:12px 10px;border-bottom:1px solid #e2e8f0}th{color:#475569;background:#f8fbf9}.pagination{margin-top:14px}
label{display:inline-block;margin:8px 12px 8px 0}input{width:72px;padding:6px}button{padding:7px 12px;margin-right:8px;cursor:pointer}h2{margin:28px 0 8px;color:#0b5138}
</style></head>
<body><main class="wrap"><div class="card">
<h1>Course Partner Email Activity</h1>
<p class="muted">An open means the tracking image loaded. Mail apps can block or prefetch images, so this is an estimate of engagement.</p>
<p><a href="{{ route('leads.course-partners.index') }}">Back to prospects</a></p>
@if (session('status'))<p role="status">{{ session('status') }}</p>@endif
@if ($queueReady)
    <h2>Sending queue</h2>
    <p><strong>{{ ($queueControl?->paused ?? true) ? 'Paused' : 'Running' }}</strong> · Prospect sending {{ config('course_partner_outreach.enabled') ? 'enabled' : 'disabled' }} · {{ $queueCounts->get('queued', 0) }} queued · {{ $queueCounts->get('claimed', 0) }} needs review · {{ $queueCounts->get('sent', 0) }} sent · {{ $queueCounts->get('failed', 0) }} failed</p>
    <form method="post" action="{{ route('leads.course-partners.queue-control') }}">
        @csrf
        <label>Daily attempt limit <input type="number" name="daily_limit" min="1" max="100" value="{{ old('daily_limit', $queueControl?->daily_limit ?? 20) }}" required></label>
        <label>Minutes between attempts <input type="number" name="spacing_minutes" min="1" max="1440" value="{{ old('spacing_minutes', $queueControl?->spacing_minutes ?? 5) }}" required></label>
        <button type="submit" name="action" value="pause">Pause</button>
        <button type="submit" name="action" value="resume">Resume</button>
    </form>
    @if ($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    <p class="muted">Only reviewed prospects can be staged. A claimed or failed entry stays for manual review; it will not resend itself.</p>
    <div class="table-wrap"><table><thead><tr><th>Queue ID</th><th>Provider</th><th>Recipient</th><th>Status</th><th>Attempted</th><th>Sent</th><th>Error</th></tr></thead><tbody>
    @forelse ($queueRows as $row)
        <tr><td>{{ $row->id }}</td><td>{{ $row->name }}</td><td>{{ $row->recipient_email }}</td><td>{{ ucfirst($row->status) }}</td><td>{{ $row->claimed_at ?? '—' }} UTC</td><td>{{ $row->sent_at ?? '—' }} UTC</td><td>{{ $row->error_code ?? '—' }}</td></tr>
    @empty
        <tr><td colspan="7" class="muted">No prospects staged.</td></tr>
    @endforelse
    </tbody></table></div>
@endif
<h2>Email activity</h2>
@if (! $ready)
    <p>The outreach tracking table is not installed.</p>
@else
    <div class="table-wrap"><table><thead><tr><th>Recipient</th><th>Provider</th><th>Status</th><th>Sent</th><th>First open</th><th>Last open</th><th>Image loads</th></tr></thead><tbody>
    @forelse ($emails as $email)
        <tr>
            <td>{{ $email->recipient_email }}</td>
            <td>{{ $email->prospect?->name ?? 'Review preview' }} @if ($email->prospect)<br><span class="muted">{{ $email->prospect->country_code }}</span>@endif</td>
            <td>{{ ucfirst($email->status) }}</td>
            <td>{{ $email->sent_at?->format('Y-m-d H:i') ?? '—' }} UTC</td>
            <td>{{ $email->first_opened_at?->format('Y-m-d H:i') ?? '—' }} UTC</td>
            <td>{{ $email->last_opened_at?->format('Y-m-d H:i') ?? '—' }} UTC</td>
            <td>{{ $email->open_count }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">No emails have been sent.</td></tr>
    @endforelse
    </tbody></table></div><div class="pagination">{{ $emails->links() }}</div>
@endif
</div></main></body></html>
