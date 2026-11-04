<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Course Partner Prospects · Miranda</title>
    <style>
        body { margin:0; background:#f3f7f5; color:#1f2937; font:14px/1.5 Arial,Helvetica,sans-serif; }
        .wrap { max-width:1240px; margin:28px auto; padding:0 16px; }
        .card { background:white; border:1px solid #d8e5de; border-radius:12px; padding:20px; margin-bottom:18px; }
        h1 { margin:0 0 8px; color:#0b5138; font-size:26px; }
        h2 { margin:0 0 12px; color:#0b5138; font-size:18px; }
        p { margin:6px 0 14px; }
        .muted { color:#64748b; }
        a { color:#0b7a50; }
        .toolbar { display:flex; gap:12px; align-items:end; flex-wrap:wrap; }
        .toolbar label { display:grid; gap:4px; min-width:145px; }
        select,textarea,input,button { font:inherit; }
        select,textarea,input[type=date] { border:1px solid #baccc1; border-radius:7px; padding:7px; background:white; color:#1f2937; }
        button,.button-link { display:inline-block; border:0; border-radius:7px; padding:8px 13px; background:#0b7a50; color:white; text-decoration:none; cursor:pointer; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:900px; }
        th,td { text-align:left; vertical-align:top; padding:12px 10px; border-bottom:1px solid #e2e8f0; }
        th { color:#475569; background:#f8fbf9; }
        .chip { display:inline-block; background:#e8f4ed; color:#0b5138; border-radius:999px; padding:2px 8px; margin:3px 3px 0 0; }
        .review-form { display:grid; gap:7px; min-width:190px; }
        .review-form textarea { width:100%; box-sizing:border-box; min-height:55px; }
        .status { border:1px solid #a7dbc0; background:#e9f8f0; color:#0b5138; padding:10px 12px; border-radius:7px; }
        .pagination { margin-top:14px; }
    </style>
</head>
<body>
<main class="wrap">
    <div class="card">
        <h1>Course Partner Prospects</h1>
        <p class="muted">Nail and lash course providers discovered across the United Kingdom, United States, Canada, Australia and New Zealand. Course evidence and public contacts require human review. Email sending is manual; an observed open means the email image loaded and does not prove a person read it.</p>
        <a href="{{ route('leads.discovery.index') }}">Back to Lead Discovery</a>
        <span class="muted"> · </span><a href="{{ route('leads.course-partners.emails') }}">Email activity</a>
        @if (session('status')) <p class="status">{{ session('status') }}</p> @endif
        @if ($errors->any()) <p class="status">{{ $errors->first() }}</p> @endif
    </div>

    @if (! $ready)
        <div class="card"><p>The Course Partner Programme table is not installed yet. Apply the reviewed additive migration before using this page.</p></div>
    @else
        <div class="card">
            <form method="GET" action="{{ route('leads.course-partners.index') }}" class="toolbar">
                <label>Country
                    <select name="country"><option value="">All countries</option>
                        @foreach ($countries as $code => $country)
                            <option value="{{ $code }}" {{ $filters['country'] === $code ? 'selected' : '' }}>{{ $country['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Course
                    <select name="focus"><option value="">Nail and lash</option><option value="nail" {{ $filters['focus'] === 'nail' ? 'selected' : '' }}>Nail</option><option value="lash" {{ $filters['focus'] === 'lash' ? 'selected' : '' }}>Lash</option></select>
                </label>
                <label>Review
                    <select name="status"><option value="">Any status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Public email
                    <select name="contact"><option value="">Any</option><option value="yes" {{ $filters['contact'] === 'yes' ? 'selected' : '' }}>Found</option><option value="no" {{ $filters['contact'] === 'no' ? 'selected' : '' }}>Missing</option></select>
                </label>
                <button type="submit">Filter</button>
                <a class="button-link" href="{{ route('leads.course-partners.export', array_filter($filters)) }}">Export filtered CSV</a>
            </form>
        </div>

        <div class="card">
            <h2>{{ $prospects->total() }} prospects</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Provider</th><th>Market</th><th>Course evidence</th><th>Public contact</th><th>Email activity</th><th>Review</th></tr></thead>
                <tbody>
                    @forelse ($prospects as $prospect)
                        @php $firstEvidence = collect((array) $prospect->course_evidence)->first(); @endphp
                        <tr>
                            <td><strong>{{ $prospect->name }}</strong><br><a href="{{ $prospect->website }}" target="_blank" rel="noopener noreferrer">{{ $prospect->domain }}</a><br><span class="muted">Found {{ optional($prospect->last_discovered_at)->toDateString() }}</span></td>
                            <td>{{ $countries[$prospect->country_code]['label'] ?? $prospect->country_code }}<br><span class="muted">{{ $prospect->location }}</span></td>
                            <td>
                                @foreach ((array) $prospect->course_focuses as $focus)<span class="chip">{{ ucfirst($focus) }}</span>@endforeach
                                @if ($firstEvidence && ! empty($firstEvidence['source_page']))<br><a href="{{ $firstEvidence['source_page'] }}" target="_blank" rel="noopener noreferrer">Open course page</a>@endif
                                <br><span class="muted">{{ count((array) $prospect->course_evidence) }} evidence page(s)</span>
                            </td>
                            <td>
                                {{ $prospect->primary_email ?: 'No public email found' }}
                                @if ($prospect->primary_email && in_array(strtolower($prospect->primary_email), $suppressedAddresses, true))
                                    <br><strong>Suppressed</strong>
                                @endif
                                @if ($prospect->email_source_url)<br><a href="{{ $prospect->email_source_url }}" target="_blank" rel="noopener noreferrer">Email source</a>@endif
                                @if ($prospect->phone)<br><span class="muted">{{ $prospect->phone }}</span>@endif
                            </td>
                            <td>
                                @if (! $outreachReady)
                                    <span class="muted">Tracking not installed</span>
                                @elseif (! $prospect->latestOutreachEmail)
                                    <span class="muted">Not sent</span>
                                @else
                                    {{ ucfirst($prospect->latestOutreachEmail->status) }}
                                    @if ($prospect->latestOutreachEmail->sent_at)<br><span class="muted">Sent {{ $prospect->latestOutreachEmail->sent_at->format('Y-m-d H:i') }} UTC</span>@endif
                                    @if ($prospect->latestOutreachEmail->first_opened_at)
                                        <br><strong>Opened {{ $prospect->latestOutreachEmail->first_opened_at->format('Y-m-d H:i') }} UTC</strong>
                                        <br><span class="muted">{{ $prospect->latestOutreachEmail->open_count }} image load(s)</span>
                                    @elseif ($prospect->latestOutreachEmail->status === 'sent')
                                        <br><span class="muted">No open observed</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('leads.course-partners.update', $prospect) }}" class="review-form">
                                    @csrf @method('PATCH')
                                    <select name="review_status" aria-label="Review status for {{ $prospect->name }}">
                                        @foreach ($statuses as $status)<option value="{{ $status }}" {{ $prospect->review_status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>@endforeach
                                    </select>
                                    <textarea name="review_notes" aria-label="Review notes for {{ $prospect->name }}" placeholder="Why this is or is not a fit">{{ $prospect->review_notes }}</textarea>
                                    <button type="submit">Save review</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No prospects match these filters. Run a course scan, then review the evidence here.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <div class="pagination">{{ $prospects->links() }}</div>
        </div>
    @endif
</main>
</body>
</html>
