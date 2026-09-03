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
        .intent-chip { background:#dbeafe; color:#1d4ed8; }
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
        .fresh-website { background: #ecfdf5; color: #047857; }
        .actions a { margin-right: 6px; }
        .batch-summary {
            margin-top: 18px;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .07);
        }
        .batch-summary-toggle {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            padding: 18px 20px;
            cursor: pointer;
            list-style: none;
            background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 70%);
        }
        .batch-summary-toggle::-webkit-details-marker { display: none; }
        .batch-summary h2 { margin: 0 0 6px; }
        .batch-summary-body { padding: 4px 20px 20px; border-top: 1px solid #dbeafe; }
        .batch-summary-table { overflow-x: auto; }
        .summary-actions { display: flex; gap: 10px; align-items: center; }
        .summary-total { padding: 9px 13px; border-radius: 10px; background: #dbeafe; text-align: center; font-size: 22px; font-weight: 700; color: #1d4ed8; white-space: nowrap; }
        .summary-total span { display: block; font-size: 10px; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .toggle-label { min-width: 54px; color: #1d4ed8; font-size: 13px; font-weight: 700; }
        .toggle-label::after { content: 'Show'; }
        .batch-summary[open] .toggle-label::after { content: 'Hide'; }
        .call-date { display: block; margin-bottom: 7px; }
        .call-date:last-child { margin-bottom: 0; }
        .outcome-badge { display: inline-block; padding: 4px 9px; border-radius: 999px; background: #e2e8f0; color: #334155; font-size: 12px; font-weight: 700; }
        .outcome-keen { background: #dcfce7; color: #166534; }
        .outcome-follow_up { background: #dbeafe; color: #1d4ed8; }
        .outcome-not_interested { background: #fee2e2; color: #991b1b; }
        .outcome-no_answer { background: #fef3c7; color: #92400e; }
        .note-copy { max-width: 340px; line-height: 1.45; }
        .daily-summary { margin-top: 18px; padding: 20px; border: 1px solid #c7d2fe; border-radius: 14px; background: linear-gradient(135deg, #eef2ff 0%, #fff 65%); }
        .daily-summary-header { display: flex; justify-content: space-between; gap: 16px; align-items: start; flex-wrap: wrap; }
        .daily-summary h2 { margin: 0 0 5px; }
        .date-filter { display: flex; gap: 8px; align-items: end; flex-wrap: wrap; }
        .date-filter input { min-width: 155px; }
        .daily-metrics { display: grid; grid-template-columns: repeat(4, minmax(140px, 1fr)); gap: 12px; margin-top: 18px; }
        .daily-metric { padding: 15px; border: 1px solid #e0e7ff; border-radius: 11px; background: rgba(255,255,255,.9); }
        .daily-metric strong { display: block; color: #312e81; font-size: 27px; }
        .daily-metric span { color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .outcome-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(125px, 1fr)); gap: 9px; margin-top: 12px; }
        .outcome-stat { padding: 11px 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
        .outcome-stat strong { display: block; font-size: 20px; }
        .outcome-stat span { color: #64748b; font-size: 12px; }
        .conversion-rate { margin-top: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0; color: #475569; font-size: 12px; line-height: 1.5; }
        .conversion-rate b { color: #1e293b; }
        .market-summary { background: #111827; color: #f9fafb; }
        .market-summary h2 { margin: 0; }
        .market-summary .muted { color: #cbd5e1; }
        .market-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; margin-top: 16px; }
        .market-card { border: 1px solid #334155; border-radius: 10px; padding: 13px; background: #1e293b; }
        .market-card.opening-soon { border-color: #a16207; background: #422006; }
        .market-card.selected-market { box-shadow: 0 0 0 2px #60a5fa; }
        .market-action { width: 100%; box-sizing: border-box; margin-top: 10px; padding: 7px 9px; background: #2563eb; font-size: 12px; text-align: center; }
        .market-time { margin: 7px 0 2px; font-size: 22px; font-weight: 700; }
        .market-count { color: #86efac; font-weight: 700; }
        .market-opening-soon { color: #fde68a; font-weight: 700; }
        .market-empty { margin: 16px 0 0; color: #cbd5e1; }
        .market-filter-notice { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; padding: 13px 15px; border: 1px solid #bfdbfe; border-radius: 10px; background: #eff6ff; }
        .empty-leads { padding: 28px 12px; text-align: center; color: #475569; }
        @media (max-width: 700px) { .daily-metrics { grid-template-columns: 1fr; } }
    </style>
    @include('components.market-local-time-assets')
</head>
<body>
<div class="wrap">
    <section class="card market-summary" aria-live="polite">
        <h2>English-speaking markets: open now &amp; opening soon</h2>
        <p class="muted">Places within local hours of 09:00–17:00, plus markets opening in the next three hours. Select a market to view its leads.</p>
        <p id="market-count" class="market-count">Checking local times…</p>
        <div id="market-grid" class="market-grid"></div>
        <p id="market-empty" class="market-empty" hidden>No listed markets are currently within business hours. The overview updates automatically.</p>
    </section>

    <div class="card">
        <h1>Leads</h1>
        <p class="muted">Browse discovered businesses and open each lead for full contact details.</p>
        <p>
            <a class="button-link" href="{{ route('leads.discovery.index') }}">Lead Discovery</a>
            <a class="button-link" href="{{ route('zoom-phone.index') }}" style="background:#2563eb;">Zoom Phone</a>
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
            @if (($selectedMarket ?? null) !== null)
                <div class="market-filter-notice">
                    <div>
                        <strong>Viewing leads in {{ $selectedMarket['name'] }}, {{ $selectedMarket['country'] }}</strong>
                        <div class="muted">These results include leads discovered for this market and leads whose city or address matches the region.</div>
                    </div>
                    <a class="button-link" href="{{ route('leads.index') }}" style="background:#334155;">View all leads</a>
                </div>
            @endif

            <form method="GET" action="{{ route('leads.index') }}">
                @if (filled($marketFilter ?? ''))
                    <input type="hidden" name="market" value="{{ $marketFilter }}">
                @endif
                @if (($dailyCallSummary ?? null) !== null)
                    <input type="hidden" name="activity_date" value="{{ $dailyCallSummary['date'] }}">
                @endif
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
                        <label for="intent">Lead Intent</label>
                        <select id="intent" name="intent">
                            <option value="">All</option>
                            @foreach (($intentTagOptions ?? []) as $intentValue => $intentLabel)
                                <option value="{{ $intentValue }}" {{ ($intentFilter ?? '') === $intentValue ? 'selected' : '' }}>{{ $intentLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="website_age">Website Age</label>
                        <select id="website_age" name="website_age">
                            <option value="">All</option>
                            <option value="new_30d" {{ ($websiteAgeFilter ?? '') === 'new_30d' ? 'selected' : '' }}>New within 30 days (high confidence)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="scan_run">Discovery Batch</label>
                        <select id="scan_run" name="scan_run">
                            <option value="">All batches</option>
                            @foreach (($scanRuns ?? collect()) as $run)
                                <option value="{{ $run->id }}" {{ (int) ($scanRunId ?? 0) === $run->id ? 'selected' : '' }}>
                                    #{{ $run->id }} - {{ $run->query }} - {{ $run->location }} -
                                    @if ($run->business_leads_count > 0)
                                        {{ $run->business_leads_count }} linked leads
                                    @elseif ($run->total_places_found > 0)
                                        {{ $run->total_places_found }} discovered (not linked)
                                    @else
                                        0 leads
                                    @endif
                                    @if (count((array) $run->intent_tags) > 0)
                                        - {{ collect((array) $run->intent_tags)->map(fn ($intent) => $intentTagOptions[$intent] ?? ucwords(str_replace('_', ' ', $intent)))->join(' + ') }}
                                    @endif
                                    - {{ optional($run->created_at)->format('d M Y H:i') }}
                                </option>
                                @continue
                                <option value="{{ $run->id }}" {{ (int) ($scanRunId ?? 0) === $run->id ? 'selected' : '' }}>
                                    #{{ $run->id }} · {{ $run->query }} · {{ $run->location }} · {{ $run->business_leads_count }} leads · {{ optional($run->created_at)->format('d M Y H:i') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button type="submit">Apply</button>
                    </div>
                </div>
            </form>

            @if (($dailyCallSummary ?? null) !== null)
                <section class="daily-summary">
                    <div class="daily-summary-header">
                        <div>
                            <h2>Daily Call Activity</h2>
                            <div class="muted">{{ $dailyCallSummary['date_label'] }} | All discovery batches | {{ $dailyCallSummary['timezone'] }}</div>
                        </div>
                        <form class="date-filter" method="GET" action="{{ route('leads.index') }}">
                            @if (filled($marketFilter ?? ''))
                                <input type="hidden" name="market" value="{{ $marketFilter }}">
                            @endif
                            @if (filled($leadSearch ?? ''))
                                <input type="hidden" name="lead_search" value="{{ $leadSearch }}">
                            @endif
                            @if (filled($contactFilter ?? ''))
                                <input type="hidden" name="contact" value="{{ $contactFilter }}">
                            @endif
                            @if (filled($scrapedFilter ?? ''))
                                <input type="hidden" name="scraped" value="{{ $scrapedFilter }}">
                            @endif
                            @if (filled($intentFilter ?? ''))
                                <input type="hidden" name="intent" value="{{ $intentFilter }}">
                            @endif
                            @if (filled($websiteAgeFilter ?? ''))
                                <input type="hidden" name="website_age" value="{{ $websiteAgeFilter }}">
                            @endif
                            <div>
                                <label for="activity_date">Activity date</label>
                                <input id="activity_date" type="date" name="activity_date" value="{{ $dailyCallSummary['date'] }}">
                            </div>
                            <button type="submit">View day</button>
                        </form>
                    </div>

                    <div class="daily-metrics">
                        <div class="daily-metric">
                            <strong>{{ number_format($dailyCallSummary['unique_numbers']) }}</strong>
                            <span>Unique numbers called</span>
                        </div>
                        <div class="daily-metric">
                            <strong>{{ number_format($dailyCallSummary['call_attempts']) }}</strong>
                            <span>Total call attempts</span>
                        </div>
                        <div class="daily-metric">
                            <strong>{{ number_format($dailyCallSummary['answered_numbers']) }}</strong>
                            <span>Answered contacts · {{ number_format($dailyCallSummary['answered_rate'], 1) }}%</span>
                        </div>
                        <div class="daily-metric">
                            <strong>{{ number_format($dailyCallSummary['outcomes_saved']) }}</strong>
                            <span>Contacts with outcomes</span>
                        </div>
                    </div>

                    <h3 style="margin:20px 0 0;">Saved outcome breakdown</h3>
                    <p class="muted" style="margin:5px 0 0;">One outcome per unique called number. Answered contacts are human-confirmed outcomes: Contacted, Keen, Follow Up, or Not Interested.</p>
                    <div class="outcome-grid">
                        @foreach ($dailyCallSummary['outcome_breakdown'] as $outcome => $breakdown)
                            <div class="outcome-stat">
                                <strong>{{ number_format($breakdown['count']) }}</strong>
                                <span>{{ $outcome === 'not_set' ? 'Not set' : ucwords(str_replace('_', ' ', $outcome)) }}</span>
                                <div class="conversion-rate">
                                    <div><b>{{ number_format($breakdown['all_rate'], 1) }}%</b> of all numbers</div>
                                    <div><b>{{ number_format($breakdown['answered_rate'], 1) }}%</b> of answered contacts</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (($selectedScanRun ?? null) !== null)
                <details class="batch-summary" open>
                    <summary class="batch-summary-toggle">
                        <div>
                            <h2>Batch Call Summary</h2>
                            <div class="muted">
                                #{{ $selectedScanRun->id }} - {{ $selectedScanRun->query }} - {{ $selectedScanRun->location }}
                                @foreach ((array) $selectedScanRun->intent_tags as $intent)
                                    <span class="chip intent-chip">{{ $intentTagOptions[$intent] ?? ucwords(str_replace('_', ' ', $intent)) }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="summary-actions">
                            <x-market-local-time :location="$selectedScanRun->location" :timezone="$batchTimezone" />
                            <div class="summary-total">
                                {{ number_format(($batchCallSummary ?? collect())->count()) }}
                                <span>Unique numbers called</span>
                            </div>
                            <span class="toggle-label" aria-hidden="true"></span>
                        </div>
                    </summary>

                    <div class="batch-summary-body">
                        <p class="muted">Each phone number appears once. Repeat calls are grouped together, with the lead's latest saved outcome and note.</p>
                        <div class="batch-summary-table">
                            <table>
                                <thead>
                                <tr>
                                    <th>Contact</th>
                                    <th>Call history</th>
                                    <th>Saved outcome</th>
                                    <th>Latest note</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse (($batchCallSummary ?? collect()) as $row)
                                    @php($latestNote = $row['latest_note'])
                                    <tr>
                                        <td>
                                            @if ($row['business_lead'])
                                                <a href="{{ route('leads.show', ['businessLead' => $row['business_lead'], 'scan_run' => $selectedScanRun->id]) }}">
                                                    <strong>{{ $row['business_lead']->name }}</strong>
                                                </a>
                                            @else
                                                <span class="muted">Unknown lead</span>
                                            @endif
                                            <div style="margin-top:6px;">
                                                <a href="tel:{{ \App\Support\PhoneNumberFormatter::telUri($row['number']) }}">{{ $row['number'] }}</a>
                                            </div>
                                        </td>
                                        <td>
                                            @foreach ($row['calls'] as $call)
                                                <span class="call-date">
                                                    {{ $call['occurred_at']?->format('d M Y, H:i') ?? 'Time unavailable' }}
                                                    @if ($call['direction'] || $call['result'])
                                                        <span class="muted">
                                                            — {{ collect([$call['direction'] ? ucfirst($call['direction']) : null, $call['result']])->filter()->join(' · ') }}
                                                        </span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </td>
                                        <td>
                                            @if ($latestNote)
                                                <span class="outcome-badge outcome-{{ $latestNote->outcome }}">
                                                    {{ ucwords(str_replace('_', ' ', $latestNote->outcome)) }}
                                                </span>
                                            @else
                                                <span class="muted">Not set</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($latestNote && filled($latestNote->body))
                                                <div class="note-copy">{{ \Illuminate\Support\Str::limit($latestNote->body, 180) }}</div>
                                                <div class="muted" style="margin-top:5px;">Saved {{ $latestNote->created_at?->format('d M Y, H:i') }}</div>
                                            @elseif ($latestNote)
                                                <span class="muted">Outcome saved without a note.</span>
                                            @else
                                                <span class="muted">No saved note.</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="muted">No Zoom calls have been matched to leads in this batch yet.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            @endif

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
                            @foreach ((array) $lead->intent_tags as $intent)
                                <span class="chip intent-chip">{{ $intentTagOptions[$intent] ?? ucwords(str_replace('_', ' ', $intent)) }}</span>
                            @endforeach
                            @if ($lead->website)
                                <div><a href="{{ $lead->website }}" target="_blank" rel="noopener">{{ $lead->website }}</a></div>
                            @endif
                            @if ($lead->booking_url)
                                <div style="margin-top:6px;"><strong>Booking system:</strong> <a href="{{ $lead->booking_url }}" target="_blank" rel="noopener">Open booking link</a></div>
                            @endif
                        </td>
                        <td class="muted">
                            Main:
                            @if ($lead->phone)
                                <a href="tel:{{ \App\Support\PhoneNumberFormatter::telUri($lead->phone) }}">{{ $lead->phone }}</a>
                            @else
                                -
                            @endif
                            <br>
                            Mobile:
                            @if ($lead->mobile_phone)
                                <a href="tel:{{ \App\Support\PhoneNumberFormatter::telUri($lead->mobile_phone) }}">{{ $lead->mobile_phone }}</a>
                            @else
                                -
                            @endif
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
                            <div class="muted" style="margin-top:6px;">
                                Outcome: {{ $lead->latest_outcome ? ucwords(str_replace('_', ' ', $lead->latest_outcome)) : 'Not set' }}
                            </div>
                            @if ($lead->website_freshness_confidence === 'high' && $lead->website_estimated_launched_at)
                                <span class="chip fresh-website">New website · {{ $lead->website_freshness_score }}/100</span>
                            @elseif ($lead->website_freshness_checked_at)
                                <div class="muted" style="margin-top:6px;">Website freshness: {{ ucfirst($lead->website_freshness_confidence ?? 'unknown') }}</div>
                            @endif
                        </td>
                        <td class="actions">
                            <a class="button-link" href="{{ route('leads.show', ['businessLead' => $lead, 'scan_run' => $scanRunId, 'market' => $marketFilter ?: null, 'website_age' => $websiteAgeFilter ?: null]) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-leads">
                            @if (($selectedMarket ?? null) !== null)
                                <strong>No leads found in {{ $selectedMarket['name'] }}, {{ $selectedMarket['country'] }}.</strong>
                                <div style="margin-top:6px;">Try another open market or <a href="{{ route('leads.index') }}">view all leads</a>.</div>
                            @else
                                No leads found for the current filters.
                            @endif
                        </td>
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
<script>
    (() => {
        const markets = @json($englishSpeakingMarkets ?? []);
        const leadsUrl = @json(route('leads.index'));
        const selectedLocation = @json($marketFilter ?? '');
        const marketGrid = document.getElementById('market-grid');
        const marketCount = document.getElementById('market-count');
        const marketEmpty = document.getElementById('market-empty');

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

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
                hour: Number(value('hour')),
                minute: Number(value('minute')),
                label: `${value('weekday')} ${value('hour')}:${value('minute')}`,
            };
        }

        function availabilityFor(local) {
            const currentMinute = (local.hour * 60) + local.minute;
            const openingMinute = 9 * 60;
            const closingMinute = 17 * 60;

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
            const availableMarkets = markets.map((market) => {
                try {
                    const local = localMarketTime(market.timezone);
                    const availability = availabilityFor(local);
                    return availability ? { ...market, local, ...availability } : null;
                } catch (error) {
                    return null;
                }
            }).filter(Boolean);
            const openCount = availableMarkets.filter((market) => market.state === 'open').length;
            const openingSoonCount = availableMarkets.length - openCount;

            marketCount.textContent = `${openCount} market${openCount === 1 ? '' : 's'} open now${openingSoonCount ? ` · ${openingSoonCount} opening within 3 hours` : ''}`;
            marketEmpty.hidden = availableMarkets.length > 0;
            marketGrid.innerHTML = availableMarkets.map((market) => {
                const url = new URL(leadsUrl, window.location.origin);
                url.searchParams.set('market', market.location);
                const isSelected = market.location === selectedLocation;

                return `
                    <article class="market-card ${market.state === 'opening_soon' ? 'opening-soon' : ''} ${isSelected ? 'selected-market' : ''}">
                        <strong>${escapeHtml(market.name)}</strong><br>
                        <span class="muted">${escapeHtml(market.country)}</span>
                        <div class="market-time">${escapeHtml(market.local.label)}</div>
                        <span class="${market.state === 'opening_soon' ? 'market-opening-soon' : 'muted'}">${escapeHtml(market.message)}</span>
                        <a class="button-link market-action" href="${escapeHtml(url.toString())}">View leads</a>
                    </article>
                `;
            }).join('');
        }

        renderOpenMarkets();
        window.setInterval(renderOpenMarkets, 60000);
    })();
</script>
</body>
</html>
