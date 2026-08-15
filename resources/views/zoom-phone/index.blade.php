<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zoom Phone</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f9fafb; color: #111827; }
        .wrap { max-width: 1200px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: minmax(350px, 420px) 1fr; gap: 18px; align-items: start; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .button-link, button { display: inline-block; border: 0; border-radius: 8px; padding: 10px 14px; background: #111827; color: #fff; text-decoration: none; cursor: pointer; }
        .secondary { background: #334155; }
        .muted { color: #6b7280; font-size: 13px; }
        .status { padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .warning { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
        iframe { width: 100%; min-width: 350px; height: 650px; border: 1px solid #d1d5db; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 9px 7px; border-bottom: 1px solid #e5e7eb; font-size: 13px; vertical-align: top; }
        .badge { display: inline-block; border-radius: 999px; padding: 3px 7px; background: #e0e7ff; color: #3730a3; font-size: 11px; text-transform: uppercase; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Zoom Phone</h1>
        <p>
            <a class="button-link secondary" href="{{ route('leads.index') }}">Leads</a>
            <a class="button-link secondary" href="{{ route('dashboard') }}">Dashboard</a>
        </p>
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="status warning">{{ $errors->first() }}</div>
        @endif
    </div>

    <div class="grid">
        <div class="card">
            <h2>Desktop dialler and history</h2>
            @if ($smartEmbedEnabled)
                <iframe
                    id="zoom-embeddable-phone-iframe"
                    src="{{ $smartEmbedUrl }}"
                    allow="clipboard-read; clipboard-write https://applications.zoom.us"
                ></iframe>
            @else
                <div class="status warning">
                    Smart Embed is ready in Miranda but disabled until this site is added to Zoom Phone Smart Embed’s approved domains.
                </div>
                <p class="muted">After the domain is approved, set <code>ZOOM_PHONE_SMART_EMBED_ENABLED=true</code>.</p>
            @endif
        </div>

        <div class="card">
            <h2>Miranda call history</h2>
            <p class="muted">Completed calls are matched to leads by phone number.</p>
            <form method="POST" action="{{ route('zoom-phone.sync') }}" style="margin-bottom:14px;">
                @csrf
                <button type="submit" {{ $apiConfigured ? '' : 'disabled' }}>Sync from Zoom</button>
                @unless ($apiConfigured)
                    <span class="muted">OAuth credentials are not configured yet.</span>
                @endunless
            </form>

            <table>
                <thead>
                    <tr><th>Time</th><th>Lead / Number</th><th>Call</th><th>Duration</th></tr>
                </thead>
                <tbody>
                    @forelse ($callLogs as $call)
                        <tr>
                            <td>{{ optional($call->occurred_at)->format('d M Y H:i') ?: '-' }}</td>
                            <td>
                                @if ($call->businessLead)
                                    <a href="{{ route('leads.show', $call->businessLead) }}">{{ $call->businessLead->name }}</a><br>
                                @endif
                                <span class="muted">{{ $call->external_number ?: '-' }}</span>
                            </td>
                            <td><span class="badge">{{ $call->direction ?: 'unknown' }}</span><br>{{ $call->result ?: '-' }}</td>
                            <td>{{ $call->duration_seconds === null ? '-' : gmdate('i:s', $call->duration_seconds) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No Zoom calls have been synced yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div style="margin-top:14px;">{{ $callLogs->links() }}</div>
        </div>
    </div>
</div>

@if ($smartEmbedEnabled)
<script>
    const zoomFrame = document.getElementById('zoom-embeddable-phone-iframe');
    const zoomOrigin = 'https://applications.zoom.us';
    const eventUrl = @json(route('zoom-phone.events'));
    const csrfToken = @json(csrf_token());
    const dialNumber = @json($dialNumber);

    function initializeZoomPhone() {
        zoomFrame?.contentWindow?.postMessage({
            type: 'zp-init-config',
            data: {
                enableSavingLog: true,
                enableAutoLog: true,
                enableContactSearching: false,
                enableContactMatching: false,
                enableAISummary: true,
                disableInactiveTabCallEvent: true
            }
        }, zoomOrigin);

        if (dialNumber) {
            zoomFrame?.contentWindow?.postMessage({
                type: 'zp-make-call',
                data: { number: dialNumber, autoDial: true }
            }, zoomOrigin);
        }
    }

    window.onZoomPhoneIframeApiReady = initializeZoomPhone;
    zoomFrame?.addEventListener('load', initializeZoomPhone);

    window.addEventListener('message', async (event) => {
        if (event.origin !== zoomOrigin || !event.data) return;
        if (!['zp-call-log-completed-event', 'zp-save-log-event'].includes(event.data.type)) return;

        await fetch(eventUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ event: event.data })
        });
    });
</script>
@endif
</body>
</html>
