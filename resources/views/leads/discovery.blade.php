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
        .intent-options { display:flex; gap:10px; flex-wrap:wrap; padding:9px 0; }
        .intent-option { display:flex; align-items:center; gap:6px; margin:0; padding:8px 10px; border:1px solid #cbd5e1; border-radius:9px; background:#f8fafc; color:#1e293b; }
        .intent-option input { width:auto; margin:0; }
        .intent-chip { background:#dbeafe; color:#1d4ed8; }
        .pagination-wrap {
            margin-top: 14px;
        }
        .mono {
            font-family: Consolas, Monaco, 'Courier New', monospace;
        }
        .market-summary {
            background: #111827;
            color: #f9fafb;
        }
        .market-summary h2 { margin: 0; }
        .market-summary .muted { color: #cbd5e1; }
        .market-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 10px;
            margin-top: 16px;
        }
        .market-card {
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 13px;
            background: #1e293b;
        }
        .market-card.opening-soon { border-color: #a16207; background: #422006; }
        .market-card button {
            width: 100%;
            margin-top: 10px;
            padding: 7px 9px;
            font-size: 12px;
            background: #2563eb;
        }
        .market-time { font-size: 22px; font-weight: 700; margin: 7px 0 2px; }
        .market-count { color: #86efac; font-weight: 700; }
        .market-opening-soon { color: #fde68a; font-weight: 700; }
        .market-empty { margin: 16px 0 0; color: #cbd5e1; }
    </style>
</head>
<body>
    <div class="wrap">
        <section class="card market-summary" aria-live="polite">
            <h2>English-speaking markets: open now &amp; opening soon</h2>
            <p class="muted">Places within local hours of 09:00–17:00, plus markets opening in the next three hours. Select a market to prefill a lead scan.</p>
            <p id="market-count" class="market-count">Checking local times…</p>
            <div id="market-grid" class="market-grid"></div>
            <p id="market-empty" class="market-empty" hidden>No listed markets are currently within business hours. The overview updates automatically.</p>
        </section>

        <div class="card">
            <h1>Lead Discovery</h1>
            <p class="muted">Choose Google Maps for place data or fast Web Search for official business websites. Both feed the same phone, email and booking-link crawler.</p>
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

            @if (!($webSearchStatus['configured'] ?? false))
                <div class="status" style="background:#fff7ed;border-color:#fed7aa;color:#9a3412;">
                    Fast Web Search needs a {{ ucfirst((string) ($webSearchStatus['provider'] ?? 'search')) }} API key. Google Maps remains available until it is configured.
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
                        <label for="discovery_source">Source</label>
                        <select id="discovery_source" name="discovery_source" {{ !($migrationReady ?? false) ? 'disabled' : '' }}>
                            <option value="web_search" {{ old('discovery_source', $defaultDiscoverySource ?? 'google_places') === 'web_search' ? 'selected' : '' }} {{ !($webSearchStatus['configured'] ?? false) ? 'disabled' : '' }}>
                                Web Search (fast)
                            </option>
                            <option value="google_places" {{ old('discovery_source', $defaultDiscoverySource ?? 'google_places') === 'google_places' ? 'selected' : '' }}>
                                Google Maps
                            </option>
                        </select>
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
                    <div class="field">
                        <label>Lead intent</label>
                        <div class="intent-options">
                            @foreach (($intentTagOptions ?? []) as $intentValue => $intentLabel)
                                <label class="intent-option">
                                    <input type="checkbox" name="intent_tags[]" value="{{ $intentValue }}" {{ in_array($intentValue, old('intent_tags', []), true) ? 'checked' : '' }}>
                                    {{ $intentLabel }}
                                </label>
                            @endforeach
                        </div>
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
                            <td>#{{ $run->id }}<br><span class="muted">{{ $run->query }} | {{ $run->location }}</span><br><span class="chip">{{ ($run->discovery_source ?? 'google_places') === 'web_search' ? 'Web Search' : 'Google Maps' }}</span>@foreach ((array) $run->intent_tags as $intent)<span class="chip intent-chip">{{ $intentTagOptions[$intent] ?? ucwords(str_replace('_', ' ', $intent)) }}</span>@endforeach</td>
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
                                Results: {{ $run->total_places_found }}<br>
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
        const englishSpeakingMarkets = @json($englishSpeakingMarkets ?? []);
        const intentTagLabels = @json($intentTagOptions ?? []);
        const marketGrid = document.getElementById('market-grid');
        const marketCount = document.getElementById('market-count');
        const marketEmpty = document.getElementById('market-empty');

        function localMarketTime(timezone) {
            const parts = new Intl.DateTimeFormat('en-GB', {
                timeZone: timezone,
                weekday: 'short',
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23',
            }).formatToParts(new Date());
            const value = (type) => parts.find((part) => part.type === type)?.value || '';
            return {
                weekday: value('weekday'),
                hour: Number(value('hour')),
                minute: Number(value('minute')),
                label: `${value('weekday')} ${value('hour')}:${value('minute')}`,
            };
        }

        function marketAvailability(local) {
            const openingMinute = 9 * 60;
            const closingMinute = 17 * 60;
            const currentMinute = (local.hour * 60) + local.minute;

            if (currentMinute >= openingMinute && currentMinute < closingMinute) {
                return { state: 'open', message: 'Open now · closes at 17:00' };
            }

            const minutesUntilOpen = openingMinute - currentMinute;

            if (minutesUntilOpen > 0 && minutesUntilOpen <= 180) {
                const hours = Math.floor(minutesUntilOpen / 60);
                const minutes = minutesUntilOpen % 60;
                const duration = [hours ? `${hours}h` : '', minutes ? `${minutes}m` : ''].filter(Boolean).join(' ');

                return { state: 'opening_soon', message: `Opens in ${duration || 'under a minute'}` };
            }

            return null;
        }

        function renderOpenMarkets() {
            const availableMarkets = englishSpeakingMarkets.map((market) => {
                const local = localMarketTime(market.timezone);
                const availability = marketAvailability(local);

                return availability ? { ...market, local, ...availability } : null;
            }).filter(Boolean);
            const openCount = availableMarkets.filter((market) => market.state === 'open').length;
            const openingSoonCount = availableMarkets.length - openCount;

            marketCount.textContent = `${openCount} market${openCount === 1 ? '' : 's'} open now${openingSoonCount ? ` · ${openingSoonCount} opening within 3 hours` : ''}`;
            marketEmpty.hidden = availableMarkets.length > 0;
            marketGrid.innerHTML = availableMarkets.map((market) => {
                return `
                    <article class="market-card ${market.state === 'opening_soon' ? 'opening-soon' : ''}">
                        <strong>${escapeHtml(market.name)}</strong><br>
                        <span class="muted">${escapeHtml(market.country)}</span>
                        <div class="market-time">${escapeHtml(market.local.label)}</div>
                        <span class="${market.state === 'opening_soon' ? 'market-opening-soon' : 'muted'}">${escapeHtml(market.message)}</span>
                        <button type="button" data-market-location="${escapeHtml(market.location)}">Start a scan here</button>
                    </article>
                `;
            }).join('');
        }

        marketGrid.addEventListener('click', (event) => {
            const button = event.target.closest('[data-market-location]');
            if (!button) return;
            document.getElementById('location').value = button.dataset.marketLocation;
            document.getElementById('query').focus();
            document.querySelector('form[action="{{ route('leads.discovery.start') }}"]').scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        function renderBadge(status) {
            const safe = ['queued', 'running', 'completed', 'failed'].includes(status) ? status : 'queued';
            return `<span class="badge ${safe}">${safe}</span>`;
        }

        function renderIntentTags(tags) {
            return (Array.isArray(tags) ? tags : []).map((tag) => {
                const label = intentTagLabels[tag] || String(tag).replace(/_/g, ' ');
                return `<span class="chip intent-chip">${escapeHtml(label)}</span>`;
            }).join('');
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
                        <td>#${run.id}<br><span class="muted">${query} | ${location}</span><br><span class="chip">${run.discovery_source === 'web_search' ? 'Web Search' : 'Google Maps'}</span>${renderIntentTags(run.intent_tags)}</td>
                        <td>${renderBadge(run.status)}</td>
                        <td>
                            ${progress}%
                            <div class="progress">
                                <div class="progress-bar" style="width:${progress}%;"></div>
                            </div>
                        </td>
                        <td class="muted">
                            Results: ${run.total_places_found}<br>
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

        renderOpenMarkets();
        setInterval(renderOpenMarkets, 60000);
    </script>
</body>
</html>
