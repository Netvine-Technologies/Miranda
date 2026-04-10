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
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>{{ $lead->name }}</h1>
        <p class="muted">Lead ID #{{ $lead->id }} | Place ID {{ $lead->place_id }}</p>
        <p>
            <a class="button-link" href="{{ route('leads.index') }}">Back to Leads</a>
            <a class="button-link" href="{{ route('leads.discovery.index') }}">Lead Discovery</a>
        </p>
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
            <p><strong>Main Phone:</strong> <span class="mono">{{ $lead->phone ?: '-' }}</span></p>
            <p><strong>Mobile Phone:</strong> <span class="mono">{{ $lead->mobile_phone ?: '-' }}</span></p>
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

    <div class="card">
        <h2>Phone Numbers</h2>
        @if ($lead->phoneNumbers->isEmpty())
            <p class="muted">No phone numbers extracted.</p>
        @else
            <ul>
                @foreach ($lead->phoneNumbers as $phone)
                    <li>
                        <span class="mono">{{ $phone->phone_number }}</span>
                        @if ($phone->source_page)
                            <div class="muted">Source: {{ $phone->source_page }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
</body>
</html>
