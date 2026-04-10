<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f9fafb; color: #111827; }
        .wrap { max-width: 1200px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 18px; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; }
        .field { flex: 1 1 280px; }
        label { display: block; margin-bottom: 6px; font-size: 14px; color: #374151; }
        input, select {
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
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; border-bottom: 1px solid #e5e7eb; padding: 10px 8px; vertical-align: top; font-size: 14px; }
        .muted { color: #6b7280; font-size: 13px; }
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
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .completed { background: #dcfce7; color: #166534; }
        .queued { background: #fef3c7; color: #92400e; }
        .actions a { margin-right: 6px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Leads</h1>
        <p class="muted">Browse discovered businesses and open each lead for full contact details.</p>
        <p>
            <a class="button-link" href="{{ route('leads.discovery.index') }}">Lead Discovery</a>
            <a class="button-link" href="{{ route('dashboard') }}" style="background:#334155;">Dashboard</a>
        </p>
    </div>

    @if ($migrationReady)
        <div class="card">
            <h2>Monthly API Cost Estimate</h2>
            <p class="muted">
                Based on recorded scans: 1 Text Search call per scan + 1 Place Details call per discovered place.
                Configured rates: Text Search Pro ${{ number_format(($pricing['text_search_pro_per_1000'] ?? 0), 2) }}/1,000,
                Place Details Pro ${{ number_format(($pricing['place_details_pro_per_1000'] ?? 0), 2) }}/1,000,
                Free calls per SKU/month: {{ $pricing['free_calls_per_sku_per_month'] ?? 0 }}.
            </p>

            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Text Search Calls</th>
                        <th>Place Details Calls</th>
                        <th>Gross Estimate (USD)</th>
                        <th>After Free Tier (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($monthlyCostRows ?? collect()) as $row)
                        <tr>
                            <td>{{ $row['month'] }}</td>
                            <td>{{ number_format((int) $row['text_search_calls']) }}</td>
                            <td>{{ number_format((int) $row['place_details_calls']) }}</td>
                            <td>${{ number_format((float) $row['gross_estimate_usd'], 2) }}</td>
                            <td>${{ number_format((float) $row['paid_estimate_usd'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">No scan history yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="card">
        @if (!($migrationReady ?? false))
            <p class="muted">Lead Discovery tables are missing. Run <code>php artisan migrate</code>.</p>
        @else
            <form method="GET" action="{{ route('leads.index') }}">
                <div class="row">
                    <div class="field">
                        <label for="lead_search">Search</label>
                        <input id="lead_search" name="lead_search" value="{{ $leadSearch ?? '' }}" placeholder="Name, city, address, website, phone">
                    </div>
                    <div>
                        <label for="contact">Contact</label>
                        <select id="contact" name="contact">
                            <option value="">All</option>
                            <option value="with_contact" {{ ($contactFilter ?? '') === 'with_contact' ? 'selected' : '' }}>With Contact</option>
                        </select>
                    </div>
                    <div>
                        <label for="scraped">Scrape State</label>
                        <select id="scraped" name="scraped">
                            <option value="">All</option>
                            <option value="scraped" {{ ($scrapedFilter ?? '') === 'scraped' ? 'selected' : '' }}>Scraped</option>
                            <option value="pending" {{ ($scrapedFilter ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit">Apply</button>
                    </div>
                </div>
            </form>

            <table>
                <thead>
                <tr>
                    <th>Business</th>
                    <th>Contact</th>
                    <th>Extracted</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td>
                            <div><strong>{{ $lead->name }}</strong></div>
                            <div class="muted">{{ $lead->city ?: '-' }}</div>
                            <div class="muted">{{ $lead->address ?: '-' }}</div>
                            @if ($lead->website)
                                <div><a href="{{ $lead->website }}" target="_blank" rel="noopener">{{ $lead->website }}</a></div>
                            @endif
                        </td>
                        <td class="muted">
                            Main: {{ $lead->phone ?: '-' }}<br>
                            Mobile: {{ $lead->mobile_phone ?: '-' }}
                        </td>
                        <td>
                            <span class="chip">Emails {{ $lead->emails_count }}</span>
                            <span class="chip">Phones {{ $lead->phone_numbers_count }}</span>
                        </td>
                        <td>
                            @if ($lead->scraped)
                                <span class="badge completed">scraped</span>
                            @else
                                <span class="badge queued">pending</span>
                            @endif
                            <div class="muted" style="margin-top:6px;">
                                Rating: {{ $lead->rating ?? '-' }} | Reviews: {{ $lead->review_count ?? '-' }}
                            </div>
                        </td>
                        <td class="actions">
                            <a class="button-link" href="{{ route('leads.show', $lead) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No leads found for the current filters.</td>
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
