<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lead Discovery</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f9fafb;
            color: #111827;
        }
        .wrap {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 16px;
        }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 18px;
        }
        .row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: end;
        }
        .field {
            flex: 1 1 280px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: #374151;
        }
        input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
        }
        select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            background: #fff;
        }
        button, .button-link {
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            background: #111827;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .status {
            background: #eef2ff;
            color: #312e81;
            border: 1px solid #c7d2fe;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 8px;
            vertical-align: top;
            font-size: 14px;
        }
        .progress {
            width: 120px;
            height: 8px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
            margin-top: 6px;
        }
        .progress-bar {
            height: 100%;
            background: #16a34a;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .queued { background: #fef3c7; color: #92400e; }
        .running { background: #dbeafe; color: #1e40af; }
        .completed { background: #dcfce7; color: #166534; }
        .failed { background: #fee2e2; color: #991b1b; }
        .muted {
            color: #6b7280;
            font-size: 13px;
        }
        .stack {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .chip {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            margin-right: 4px;
            margin-top: 4px;
        }
        .pagination-wrap {
            margin-top: 14px;
        }
        .mono {
            font-family: Consolas, Monaco, 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Lead Discovery</h1>
            <p class="muted">Queue-based Google Places scan + website crawl for emails and UK phone numbers.</p>
            <p>
                <a class="button-link" href="{{ route('dashboard') }}">Back to Dashboard</a>
                <a class="button-link" href="{{ route('leads.index') }}" style="background:#334155;">View Leads</a>
            </p>

            @if (session('status'))
                <div class="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="status" style="background:#fee2e2;border-color:#fecaca;color:#991b1b;">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (!($migrationReady ?? false))
                <div class="status" style="background:#fff7ed;border-color:#fed7aa;color:#9a3412;">
                    Lead Discovery tables are missing. Run <code>php artisan migrate</code>, then refresh this page.
                </div>
            @endif

            <form method="POST" action="{{ route('leads.discovery.start') }}">
                @csrf
                <div class="row">
                    <div class="field">
                        <label for="query">Query</label>
                        <input id="query" name="query" value="{{ old('query', 'dog trainer') }}" required {{ !($migrationReady ?? false) ? 'disabled' : '' }}>
                    </div>
                    <div class="field">
                        <label for="location">Location</label>
                        <input id="location" name="location" value="{{ old('location', 'London') }}" required {{ !($migrationReady ?? false) ? 'disabled' : '' }}>
                    </div>
                    <div>
                        <label for="depth_mode">Depth</label>
                        <select id="depth_mode" name="depth_mode" {{ !($migrationReady ?? false) ? 'disabled' : '' }}>
                            @foreach (($depthModes ?? ['quick', 'standard', 'deep', 'max']) as $mode)
                                <option value="{{ $mode }}" {{ old('depth_mode', $defaultDepthMode ?? 'standard') === $mode ? 'selected' : '' }}>
                                    {{ ucfirst($mode) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button type="submit" {{ !($migrationReady ?? false) ? 'disabled' : '' }}>Start Scan</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Recent Runs</h2>
            <p class="muted">This table auto-refreshes every 3 seconds.</p>
            <table id="runs-table">
                <thead>
                    <tr>
                        <th>Run</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Counts</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody id="runs-body">
                    @foreach ($recentRuns as $run)
                        <tr>
                            <td>#{{ $run->id }}<br><span class="muted">{{ $run->query }} | {{ $run->location }}</span></td>
                            <td><span class="badge {{ $run->status }}">{{ $run->status }}</span></td>
                            <td>
                                @php
                                    $totalWork = max($run->total_places_found + $run->websites_queued, 1);
                                    $completedWork = min($run->details_processed + $run->websites_crawled, $totalWork);
                                    $progressPercent = (int) floor(($completedWork / $totalWork) * 100);
                                @endphp
                                {{ $progressPercent }}%
                                <div class="progress">
                                    <div class="progress-bar" style="width: {{ $progressPercent }}%;"></div>
                                </div>
                            </td>
                            <td class="muted">
                                Places: {{ $run->total_places_found }}<br>
                                Details: {{ $run->details_processed }}<br>
                                Crawled: {{ $run->websites_crawled }}/{{ $run->websites_queued }}<br>
                                Emails: {{ $run->emails_found }} | Phones: {{ $run->phone_numbers_found }}
                            </td>
                            <td class="muted">{{ optional($run->created_at)->toDateTimeString() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

    <script>
        const statusUrl = @json(route('leads.discovery.status'));
        const runsBody = document.getElementById('runs-body');
        const migrationReady = @json((bool) ($migrationReady ?? false));

        function renderBadge(status) {
            const safe = ['queued', 'running', 'completed', 'failed'].includes(status) ? status : 'queued';
            return `<span class="badge ${safe}">${safe}</span>`;
        }

        function renderRows(runs) {
            if (!Array.isArray(runs) || runs.length === 0) {
                runsBody.innerHTML = '<tr><td colspan="5" class="muted">No scan runs yet.</td></tr>';
                return;
            }

            runsBody.innerHTML = runs.map((run) => {
                const progress = Number(run.progress_percent || 0);
                const query = escapeHtml(String(run.query || ''));
                const location = escapeHtml(String(run.location || ''));
                const errorMessage = escapeHtml(String(run.error_message || ''));
                const error = errorMessage ? `<br><span style="color:#991b1b;">${errorMessage}</span>` : '';

                return `
                    <tr>
                        <td>#${run.id}<br><span class="muted">${query} | ${location}</span></td>
                        <td>${renderBadge(run.status)}</td>
                        <td>
                            ${progress}%
                            <div class="progress">
                                <div class="progress-bar" style="width:${progress}%;"></div>
                            </div>
                        </td>
                        <td class="muted">
                            Places: ${run.total_places_found}<br>
                            Details: ${run.details_processed}<br>
                            Crawled: ${run.websites_crawled}/${run.websites_queued}<br>
                            Emails: ${run.emails_found} | Phones: ${run.phone_numbers_found}
                            ${error}
                        </td>
                        <td class="muted">${run.created_at || ''}</td>
                    </tr>
                `;
            }).join('');
        }

        function escapeHtml(value) {
            return value
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        async function refreshRuns() {
            try {
                const response = await fetch(statusUrl, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                renderRows(payload.runs || []);
            } catch (e) {
                // Ignore transient polling failures.
            }
        }

        if (migrationReady) {
            refreshRuns();
            setInterval(refreshRuns, 3000);
        }
    </script>
</body>
</html>
