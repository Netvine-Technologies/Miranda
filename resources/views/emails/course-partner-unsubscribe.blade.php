<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Booking Tree email preferences</title></head>
<body style="max-width:540px;margin:48px auto;padding:0 20px;font:16px/1.5 Arial,sans-serif;color:#1e293b;">
    <h1 style="color:#0b7a50;">Booking Tree email preferences</h1>
    @if (! $valid)
        <p>This unsubscribe link is invalid.</p>
    @elseif ($done ?? false)
        <p>You have been removed from Course Partner Programme outreach.</p>
    @else
        <p>Stop receiving Course Partner Programme outreach from Booking Tree?</p>
        <form method="POST" action="{{ route('course-partners.email.unsubscribe.store', ['token' => $token]) }}">
            @csrf
            <button type="submit" style="border:0;border-radius:6px;background:#0b7a50;color:white;padding:10px 16px;font:inherit;cursor:pointer;">Unsubscribe</button>
        </form>
    @endif
</body>
</html>
