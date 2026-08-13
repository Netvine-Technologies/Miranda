<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Discovery</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f9fafb; color: #111827; }
        .wrap { max-width: 1250px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 18px; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; }
        .field { flex: 1 1 260px; }
        .field-wide { flex: 2 1 420px; }
        label { display: block; margin-bottom: 6px; font-size: 14px; color: #374151; }
        input, select, textarea {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            background: #fff;
        }
        textarea { min-height: 96px; resize: vertical; }
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
        .secondary { background: #334155; }
        .status {
            background: #eef2ff;
            color: #312e81;
            border: 1px solid #c7d2fe;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; border-bottom: 1px solid #e5e7eb; padding: 10px 8px; vertical-align: top; font-size: 14px; }
        .muted { color: #6b7280; font-size: 13px; }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .strong_lead { background: #dcfce7; color: #166534; }
        .medium_lead { background: #dbeafe; color: #1d4ed8; }
        .weak_lead { background: #fef3c7; color: #92400e; }
        .needs_manual_review { background: #e5e7eb; color: #374151; }
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
        .checkbox-row { display: flex; gap: 18px; flex-wrap: wrap; align-items: center; margin-top: 10px; }
        .checkbox-row label { display: flex; align-items: center; gap: 8px; margin: 0; }
        .checkbox-row input { width: auto; }
        .mono { font-family: Consolas, Monaco, 'Courier New', monospace; word-break: break-all; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Search Discovery</h1>
        <p class="muted">Find Instagram profile leads from search API results without visiting Instagram pages directly.</p>
        <p>
            <a class="button-link secondary" href="{{ route('dashboard') }}">Back to Dashboard</a>
            <a class="button-link secondary" href="{{ route('leads.discovery.index') }}">Lead Discovery</a>
            <a class="button-link secondary" href="{{ route('leads.index') }}">Leads</a>
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
                Search Discovery table is missing. Run <code>php artisan migrate</code>, then refresh this page.
            </div>
        @endif

        @if (!empty($providerStatus))
            <div class="status" style="{{ ($providerStatus['configured'] ?? false) ? '' : 'background:#fff7ed;border-color:#fed7aa;color:#9a3412;' }}">
                Provider <strong>{{ $providerStatus['provider'] ?? 'unknown' }}</strong>: {{ $providerStatus['message'] ?? '' }}
            </div>
        @endif

        <form method="POST" action="{{ route('search-discovery.run') }}">
            @csrf
            <div class="row">
                <div class="field">
                    <label for="city">City</label>
                    <input id="city" name="city" value="{{ old('city', $cityFilter ?: 'Leeds') }}" required {{ !($migrationReady ?? false) ? 'disabled' : '' }}>
                </div>
                <div class="field">
                    <label for="niche">Niche</label>
                    <input id="niche" name="niche" list="niche-options" value="{{ old('niche', $nicheFilter ?: 'nails') }}" required {{ !($migrationReady ?? false) ? 'disabled' : '' }}>
                    <datalist id="niche-options">
                        @foreach (($defaultNiches ?? []) as $nicheOption)
                            <option value="{{ $nicheOption }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label for="provider">Provider</label>
                    <select id="provider" name="provider" {{ !($migrationReady ?? false) ? 'disabled' : '' }}>
                        @foreach (($providers ?? ['null']) as $provider)
                            <option value="{{ $provider }}" {{ old('provider', $selectedProvider ?? $defaultProvider ?? 'null') === $provider ? 'selected' : '' }}>
                                {{ $provider }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="limit">Limit</label>
                    <input id="limit" name="limit" type="number" min="1" max="250" value="{{ old('limit', 50) }}" {{ !($migrationReady ?? false) ? 'disabled' : '' }}>
                </div>
            </div>

            <div class="row" style="margin-top:12px;">
                <div class="field field-wide">
                    <label for="phrases">Phrases</label>
                    <textarea id="phrases" name="phrases" {{ !($migrationReady ?? false) ? 'disabled' : '' }}>{{ old('phrases', $defaultPhrases ?? '') }}</textarea>
                    <div class="muted">Comma-separated. Default phrases are prefilled.</div>
                </div>
                <div class="field">
                    <label for="output">Optional CSV Path</label>
                    <input id="output" name="output" value="{{ old('output', 'storage/app/search-discovery.csv') }}" {{ !($migrationReady ?? false) ? 'disabled' : '' }}>
                    <div class="muted">Exports the current run preview to a local CSV path.</div>
                </div>
            </div>

            <div class="checkbox-row">
                <label><input type="checkbox" name="dry_run" value="1" {{ old('dry_run') ? 'checked' : '' }} {{ !($migrationReady ?? false) ? 'disabled' : '' }}> Dry run</label>
                <label><input type="checkbox" name="no_save" value="1" {{ old('no_save') ? 'checked' : '' }} {{ !($migrationReady ?? false) ? 'disabled' : '' }}> Run without saving</label>
            </div>

            <div style="margin-top:14px;">
                <button type="submit" {{ !($migrationReady ?? false) ? 'disabled' : '' }}>Run Search Discovery</button>
            </div>
        </form>
    </div>

    @if (!empty($runSummary))
        <div class="card">
            <h2>Latest Run</h2>
            <div class="row">
                <div class="chip">Queries {{ $runSummary['queries_generated'] ?? 0 }}</div>
                <div class="chip">Leads {{ $runSummary['deduplicated_leads'] ?? 0 }}</div>
                <div class="chip">Saved {{ $runSummary['saved_leads'] ?? 0 }}</div>
                <div class="chip">Mode {{ str_replace('_', ' ', (string) ($runSummary['save_mode'] ?? '')) }}</div>
            </div>
            @if (!empty($runSummary['export_path']))
                <p class="muted" style="margin-top:12px;">Exported preview CSV to <span class="mono">{{ $runSummary['export_path'] }}</span></p>
            @endif
            @if (!empty($runSummary['errors']))
                <div class="status" style="margin-top:12px;background:#fee2e2;border-color:#fecaca;color:#991b1b;">
                    {{ implode(' ', $runSummary['errors']) }}
                </div>
            @endif
        </div>
    @endif

    <div class="card">
        <h2>Preview Results</h2>
        <p class="muted">This shows the most recent run in your session, including dry runs.</p>

        <table>
            <thead>
            <tr>
                <th>Instagram</th>
                <th>Query Context</th>
                <th>Signals</th>
                <th>Result</th>
            </tr>
            </thead>
            <tbody>
            @forelse (($previewLeads ?? []) as $lead)
                <tr>
                    <td>
                        <div><strong>{{ $lead['instagram_handle'] ?? '-' }}</strong></div>
                        <div class="mono">{{ $lead['instagram_profile_url'] ?? '-' }}</div>
                    </td>
                    <td>
                        <div><strong>{{ $lead['city'] ?? '-' }}</strong> | {{ $lead['niche'] ?? '-' }}</div>
                        <div class="muted">{{ $lead['phrase'] ?? '-' }}</div>
                        <div class="mono">{{ $lead['source_query'] ?? '-' }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $lead['lead_classification'] ?? 'needs_manual_review' }}">{{ $lead['lead_classification'] ?? 'needs_manual_review' }}</span>
                        <div class="muted" style="margin-top:6px;">Score: {{ $lead['lead_score'] ?? 0 }}</div>
                        <div>
                            @foreach (($lead['matched_terms'] ?? []) as $term)
                                <span class="chip">{{ $term }}</span>
                            @endforeach
                        </div>
                    </td>
                    <td>
                        <div><strong>{{ $lead['result_title'] ?? '-' }}</strong></div>
                        <div class="mono">{{ $lead['result_url'] ?? '-' }}</div>
                        <div class="muted">{{ $lead['result_snippet'] ?? '-' }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">No preview results yet. Run a search above.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="row" style="justify-content:space-between;align-items:end;">
            <div>
                <h2>Saved Leads</h2>
                <p class="muted">Search Discovery records stored in the database.</p>
            </div>
            <div>
                <a class="button-link secondary" href="{{ route('search-discovery.export', ['city' => $cityFilter, 'niche' => $nicheFilter]) }}">Download Filtered CSV</a>
            </div>
        </div>

        <form method="GET" action="{{ route('search-discovery.index') }}">
            <div class="row">
                <div class="field">
                    <label for="filter_city">City Filter</label>
                    <input id="filter_city" name="city" value="{{ $cityFilter ?? '' }}">
                </div>
                <div class="field">
                    <label for="filter_niche">Niche Filter</label>
                    <input id="filter_niche" name="niche" value="{{ $nicheFilter ?? '' }}">
                </div>
                <div>
                    <label for="filter_status">Status</label>
                    <select id="filter_status" name="status">
                        <option value="">All</option>
                        @foreach (($statuses ?? []) as $status)
                            <option value="{{ $status }}" {{ ($statusFilter ?? '') === $status ? 'selected' : '' }}>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit">Apply Filters</button>
                </div>
            </div>
        </form>

        @if (!($migrationReady ?? false))
            <p class="muted" style="margin-top:12px;">Run migrations first to store Search Discovery leads.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Instagram</th>
                    <th>Location</th>
                    <th>Classification</th>
                    <th>Status</th>
                    <th>Seen</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td>
                            <div><strong>{{ $lead->instagram_handle ?: '-' }}</strong></div>
                            <div class="mono">{{ $lead->instagram_profile_url ?: '-' }}</div>
                            <div class="muted">{{ $lead->result_title }}</div>
                            <div style="margin-top:8px;"><a class="button-link secondary" href="{{ route('search-discovery.show', $lead) }}">Review</a></div>
                        </td>
                        <td>
                            <div>{{ $lead->city }} | {{ $lead->niche }}</div>
                            <div class="muted">{{ $lead->phrase ?: '-' }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $lead->lead_classification }}">{{ $lead->lead_classification }}</span>
                            <div class="muted" style="margin-top:6px;">Score: {{ $lead->lead_score }}</div>
                        </td>
                        <td>{{ $lead->status }}</td>
                        <td class="muted">
                            Discovered: {{ optional($lead->discovered_at)->toDateTimeString() ?: '-' }}<br>
                            Last seen: {{ optional($lead->last_seen_at)->toDateTimeString() ?: '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No saved Search Discovery leads found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            @if (method_exists($leads, 'links'))
                <div style="margin-top:14px;">
                    {{ $leads->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
</body>
</html>
