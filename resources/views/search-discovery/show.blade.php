<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Discovery Lead</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f9fafb; color: #111827; }
        .wrap { max-width: 1050px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .muted { color: #6b7280; font-size: 13px; }
        .mono { font-family: Consolas, Monaco, 'Courier New', monospace; word-break: break-all; }
        .button-link, button {
            border: 0; border-radius: 8px; padding: 10px 14px; background: #111827; color: #fff;
            text-decoration: none; display: inline-block; cursor: pointer;
        }
        label { display: block; margin-bottom: 6px; font-size: 14px; color: #374151; }
        input, select { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; font-size: 14px; background: #fff; }
        .chip { display: inline-block; padding: 2px 7px; border-radius: 999px; background: #f3f4f6; color: #374151; font-size: 12px; margin-right: 4px; margin-top: 4px; }
        .status { background: #eef2ff; color: #312e81; border: 1px solid #c7d2fe; padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>{{ $lead->instagram_handle ?: 'Search Discovery Lead' }}</h1>
        <p class="muted">Lead #{{ $lead->id }} | {{ $lead->city }} | {{ $lead->niche }}</p>
        <p>
            <a class="button-link" href="{{ route('search-discovery.index') }}">Back to Search Discovery</a>
        </p>
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
    </div>

    <div class="grid">
        <div class="card">
            <h2>Lead Details</h2>
            <p><strong>Instagram Handle:</strong> {{ $lead->instagram_handle ?: '-' }}</p>
            <p><strong>Profile URL:</strong> <span class="mono">{{ $lead->instagram_profile_url ?: '-' }}</span></p>
            <p><strong>Phrase:</strong> {{ $lead->phrase ?: '-' }}</p>
            <p><strong>Source Query:</strong> <span class="mono">{{ $lead->source_query ?: '-' }}</span></p>
            <p><strong>Score:</strong> {{ $lead->lead_score }}</p>
            <p><strong>Classification:</strong> {{ $lead->lead_classification }}</p>
            <p><strong>Status:</strong> {{ $lead->status }}</p>
            <p><strong>Discovered:</strong> {{ optional($lead->discovered_at)->toDateTimeString() ?: '-' }}</p>
            <p><strong>Last Seen:</strong> {{ optional($lead->last_seen_at)->toDateTimeString() ?: '-' }}</p>
            <div>
                @foreach (($lead->matched_terms ?? []) as $term)
                    <span class="chip">{{ $term }}</span>
                @endforeach
            </div>
        </div>

        <div class="card">
            <h2>Review</h2>
            <form method="POST" action="{{ route('search-discovery.update', $lead) }}">
                @csrf
                @method('PATCH')
                <div style="margin-bottom:12px;">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" {{ $lead->status === $status ? 'selected' : '' }}>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label for="lead_classification">Classification</label>
                    <select id="lead_classification" name="lead_classification">
                        @foreach ($classifications as $classification)
                            <option value="{{ $classification }}" {{ $lead->lead_classification === $classification ? 'selected' : '' }}>{{ $classification }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit">Save Review</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2>Search Result</h2>
        <p><strong>Title:</strong> {{ $lead->result_title }}</p>
        <p><strong>URL:</strong> <span class="mono">{{ $lead->result_url }}</span></p>
        <p><strong>Snippet:</strong> {{ $lead->result_snippet ?: '-' }}</p>
        <p><strong>Position:</strong> {{ $lead->result_position ?: '-' }}</p>
    </div>
</div>
</body>
</html>
