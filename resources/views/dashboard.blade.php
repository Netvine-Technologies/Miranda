<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f9fafb;
            color: #111827;
        }
        .wrap {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 16px;
        }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
        }
        h1 {
            margin-top: 0;
        }
        .logout {
            margin-top: 16px;
        }
        .actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        button {
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            background: #111827;
            color: #fff;
            cursor: pointer;
        }
        a.button-link {
            display: inline-block;
            border-radius: 8px;
            padding: 10px 14px;
            background: #334155;
            color: #fff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Dashboard</h1>
            <p>Welcome, {{ auth()->user()->name ?? auth()->user()->email }}.</p>
            <p>You are logged in.</p>

            <div class="actions">
                <a class="button-link" href="{{ route('stores.index') }}">Manage Stores</a>
                <a class="button-link" href="{{ route('tracker.products.index') }}">View Products</a>
                <a class="button-link" href="{{ route('leads.discovery.index') }}">Lead Discovery</a>
                <a class="button-link" href="{{ route('leads.index') }}">Leads</a>
            </div>

            <form class="logout" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </div>
    </div>
</body>
</html>
