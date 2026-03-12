<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f5f7fb;
            color: #1f2937;
        }
        .container {
            max-width: 420px;
            margin: 80px auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 24px;
        }
        h1 {
            margin: 0 0 20px;
            font-size: 24px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            margin-bottom: 14px;
            box-sizing: border-box;
        }
        .row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }
        button {
            width: 100%;
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            background: #111827;
            color: #fff;
            cursor: pointer;
            font-weight: 600;
        }
        .error {
            margin-bottom: 14px;
            padding: 10px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Login</h1>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <div class="row">
                <input id="remember" type="checkbox" name="remember" value="1">
                <label for="remember" style="margin: 0; font-weight: 400;">Remember me</label>
            </div>

            <button type="submit">Sign in</button>
        </form>
    </div>
</body>
</html>
