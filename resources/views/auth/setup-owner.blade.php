<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restore Miranda owner account</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f3f5f8; color: #101828; font-family: Arial, sans-serif; }
        main { width: min(92vw, 520px); padding: 32px; border: 1px solid #dfe3e8; border-radius: 18px; background: white; box-shadow: 0 18px 50px rgba(16, 24, 40, .08); }
        h1 { margin: 0 0 10px; font-size: 30px; }
        p { margin: 0 0 24px; color: #596579; line-height: 1.5; }
        label { display: block; margin-top: 16px; font-weight: 700; }
        input { width: 100%; margin-top: 7px; padding: 13px 14px; border: 1px solid #cfd6df; border-radius: 10px; font: inherit; }
        input:focus { outline: 3px solid rgba(37, 99, 235, .15); border-color: #2563eb; }
        button { width: 100%; margin-top: 24px; padding: 14px; border: 0; border-radius: 10px; background: #111827; color: white; font: inherit; font-weight: 700; cursor: pointer; }
        .errors { padding: 12px 14px; border-radius: 10px; background: #fff1f2; color: #9f1239; }
        .hint { margin-top: 7px; font-size: 13px; color: #697586; }
    </style>
</head>
<body>
<main>
    <h1>Restore owner account</h1>
    <p>This one-time page disables itself immediately after your account is created.</p>

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ request()->fullUrl() }}">
        @csrf

        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name', $name) }}" required autocomplete="name">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">

        <label for="password">New password</label>
        <input id="password" name="password" type="password" minlength="12" required autocomplete="new-password">
        <div class="hint">Use at least 12 characters.</div>

        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" required autocomplete="new-password">

        <button type="submit">Create account and sign in</button>
    </form>
</main>
</body>
</html>
