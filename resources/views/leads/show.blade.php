<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead #{{ $lead->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f9fafb; color: #111827; }
        .wrap { max-width: 1000px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
        .muted { color: #6b7280; font-size: 13px; }
        .button-link {
            border-radius: 8px;
            padding: 10px 14px;
            background: #334155;
            color: #fff;
            text-decoration: none;
            display: inline-block;
            margin-right: 8px;
        }
        ul { margin: 0; padding-left: 18px; }
        li { margin-bottom: 8px; }
        .mono { font-family: Consolas, Monaco, 'Courier New', monospace; }
        label { display: block; margin-bottom: 6px; font-size: 14px; color: #374151; }
        select, textarea { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; font: inherit; }
        textarea { min-height: 110px; resize: vertical; }
        button { border: 0; border-radius: 8px; padding: 10px 14px; background: #111827; color: #fff; cursor: pointer; }
        .status { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 10px 12px; border-radius: 8px; margin-bottom: 14px; }
        .note { border-top: 1px solid #e5e7eb; padding: 14px 0; }
        .note:first-child { border-top: 0; padding-top: 0; }
        .outcome { display: inline-block; border-radius: 999px; padding: 3px 8px; background: #e0e7ff; color: #3730a3; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .contact-number { border-top: 1px solid #e5e7eb; padding: 11px 0; }
        .contact-number:first-child { border-top: 0; padding-top: 0; }
        .phone-link { color: #1d4ed8; font-family: Consolas, Monaco, 'Courier New', monospace; font-size: 16px; font-weight: 700; text-decoration: none; }
        .phone-link:hover { text-decoration: underline; }
        .source-badge { display: inline-block; margin-left: 6px; padding: 2px 6px; border-radius: 999px; background: #f3f4f6; color: #4b5563; font-size: 11px; }
        .lead-nav { display: flex; justify-content: space-between; gap: 10px; margin-top: 16px; }
        .lead-nav .button-link.disabled { background: #94a3b8; cursor: default; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>{{ $lead->name }}</h1>
        <p class="muted">Lead ID #{{ $lead->id }} | Place ID {{ $lead->place_id }}</p>
        <p>
            <a class="button-link" href="{{ route('leads.index', ['scan_run' => $scanRunId]) }}">Back to Leads</a>
            <a class="button-link" href="{{ route('leads.discovery.index') }}">Lead Discovery</a>
        </p>

        <div class="lead-nav">
            @if ($previousLead)
                <a class="button-link" href="{{ route('leads.show', ['businessLead' => $previousLead, 'scan_run' => $scanRunId]) }}">← Previous lead</a>
            @else
                <span class="button-link disabled">← Previous lead</span>
            @endif

            @if ($nextLead)
                <a class="button-link" href="{{ route('leads.show', ['businessLead' => $nextLead, 'scan_run' => $scanRunId]) }}">Next lead →</a>
            @else
                <span class="button-link disabled">Next lead →</span>
            @endif
        </div>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="status" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $errors->first() }}</div>
        @endif
    </div>

    <div class="grid">
        <div class="card">
            <h2>Business Info</h2>
            <p><strong>City:</strong> {{ $lead->city ?: '-' }}</p>
            <p><strong>Address:</strong> {{ $lead->address ?: '-' }}</p>
            <p><strong>Website:</strong>
                @if ($lead->website)
                    <a href="{{ $lead->website }}" target="_blank" rel="noopener">{{ $lead->website }}</a>
                @else
                    -
                @endif
            </p>
            <p><strong>Booking system:</strong>
                @if ($lead->booking_url)
                    <a href="{{ $lead->booking_url }}" target="_blank" rel="noopener">{{ $lead->booking_url }}</a>
                @else
                    Not detected
                @endif
            </p>
            <p><strong>Rating:</strong> {{ $lead->rating ?? '-' }}</p>
            <p><strong>Reviews:</strong> {{ $lead->review_count ?? '-' }}</p>
            <p><strong>Scraped:</strong> {{ $lead->scraped ? 'Yes' : 'No' }}</p>
            <p class="muted">Updated {{ optional($lead->updated_at)->toDateTimeString() }}</p>
        </div>

        <div class="card">
            <h2>Emails</h2>
            @if ($lead->emails->isEmpty())
                <p class="muted">No emails extracted.</p>
            @else
                <ul>
                    @foreach ($lead->emails as $email)
                        <li>
                            <span class="mono">{{ $email->email }}</span>
                            @if ($email->source_page)
                                <div class="muted">Source: {{ $email->source_page }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @php
        $primaryNumbers = collect([
            ['label' => 'Main phone', 'number' => $lead->phone, 'source' => 'Google Places'],
            ['label' => 'Saved mobile', 'number' => $lead->mobile_phone, 'source' => 'Lead record'],
        ])->filter(fn (array $phone) => filled($phone['number']));
        $normalizedPrimaryNumbers = $primaryNumbers
            ->map(fn (array $phone) => preg_replace('/\D+/', '', (string) $phone['number']))
            ->filter()
            ->all();
        $additionalNumbers = $lead->phoneNumbers->reject(function ($phone) use ($normalizedPrimaryNumbers) {
            $normalized = preg_replace('/\D+/', '', (string) $phone->phone_number);

            return $normalized !== '' && in_array($normalized, $normalizedPrimaryNumbers, true);
        });
    @endphp

    <div class="card">
        <h2>Contact Numbers</h2>
        <p class="muted">The main number comes from Google Places. Other numbers were detected while crawling and retain their source for review.</p>

        @if ($primaryNumbers->isNotEmpty())
            <h3 style="margin:18px 0 10px;">Primary</h3>
            @foreach ($primaryNumbers as $phone)
                <div class="contact-number">
                    <strong>{{ $phone['label'] }}</strong>
                    <span class="source-badge">{{ $phone['source'] }}</span><br>
                    <a class="phone-link" href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $phone['number']) }}">{{ $phone['number'] }}</a>
                </div>
            @endforeach
        @endif

        @if ($additionalNumbers->isNotEmpty())
            <h3 style="margin:22px 0 10px;">Additional numbers found</h3>
            @foreach ($additionalNumbers as $phone)
                <div class="contact-number">
                    <a class="phone-link" href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $phone->phone_number) }}">{{ $phone->phone_number }}</a>
                    <span class="source-badge">Crawled</span>
                    @if ($phone->source_page)
                        <div class="muted" style="margin-top:5px;">Source: {{ $phone->source_page }}</div>
                    @endif
                </div>
            @endforeach
        @elseif ($primaryNumbers->isEmpty())
            <p class="muted">No phone numbers found.</p>
        @endif
    </div>

    <div class="card">
        <h2>Contact Notes</h2>
        <p class="muted">Save an outcome on its own, or add a note alongside it.</p>
        <form method="POST" action="{{ route('leads.notes.store', $lead) }}">
            @csrf
            @if ($scanRunId)
                <input type="hidden" name="scan_run" value="{{ $scanRunId }}">
            @endif
            <div class="grid">
                <div>
                    <label for="outcome">Outcome</label>
                    <select id="outcome" name="outcome">
                        @foreach (\App\Models\LeadNote::OUTCOMES as $outcome)
                            <option value="{{ $outcome }}" {{ old('outcome', 'contacted') === $outcome ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $outcome)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="body">Note</label>
                    <textarea id="body" name="body" placeholder="Optional — e.g. Spoke to the owner. Interested in a trial; follow up Tuesday morning.">{{ old('body') }}</textarea>
                </div>
            </div>
            <p style="margin-bottom:0;"><button type="submit">Save Note</button></p>
        </form>

        <div style="margin-top:24px;">
            @forelse ($lead->notes as $note)
                <article class="note">
                    <span class="outcome">{{ str_replace('_', ' ', $note->outcome) }}</span>
                    @if (filled($note->body))
                        <div style="white-space:pre-wrap;margin-top:8px;">{{ $note->body }}</div>
                    @endif
                    <div class="muted" style="margin-top:7px;">
                        {{ optional($note->created_at)->toDateTimeString() }}
                        @if ($note->user)
                            · {{ $note->user->name ?: $note->user->email }}
                        @endif
                    </div>
                </article>
            @empty
                <p class="muted">No contact notes yet.</p>
            @endforelse
        </div>
    </div>
</div>
</body>
</html>
