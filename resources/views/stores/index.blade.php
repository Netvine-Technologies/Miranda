<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stores</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
        h1 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        input[type="text"] { width: 100%; max-width: 420px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        button, .btn-link { border: 0; border-radius: 8px; padding: 8px 12px; cursor: pointer; background: #0f172a; color: #fff; text-decoration: none; display: inline-block; }
        .btn-secondary { background: #334155; }
        .row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .error { color: #b91c1c; margin-top: 8px; }
        .status { color: #065f46; margin-bottom: 8px; }
        .queue-window {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
            overflow: hidden;
        }
        .queue-window-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid #cbd5e1;
            background: #e2e8f0;
            font-weight: 600;
        }
        .queue-window-body { padding: 14px; }
        .queue-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }
        .queue-stat {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            padding: 8px;
        }
        .queue-stat-label { display: block; font-size: 12px; color: #475569; }
        .queue-stat-value { font-size: 20px; font-weight: 700; }
        .table-scroll {
            max-height: 340px;
            overflow: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }
        .window-note {
            color: #475569;
            margin: 0 0 10px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="row" style="justify-content: space-between;">
                <h1>Shopify Stores</h1>
                <div class="row">
                    <a class="btn-link btn-secondary" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="btn-link btn-secondary" href="{{ route('tracker.products.index') }}">Products</a>
                </div>
            </div>

            @if (session('status'))
                <p class="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('stores.store') }}">
                @csrf
                <div class="row">
                    <input type="text" name="domain" placeholder="e.g. examplestore.com" value="{{ old('domain') }}" required>
                    <button type="submit">Add Store</button>
                </div>
                @error('domain')
                    <p class="error">{{ $message }}</p>
                @enderror
            </form>
        </div>

        <div class="card">
            <h2>Queue Controls (Local Dev)</h2>
            <p>Use this if you are not running <code>php artisan queue:work</code> in a terminal.</p>
            <form method="POST" action="{{ route('admin.queue.work-once') }}">
                @csrf
                <button type="submit" class="btn-secondary">Process One Queued Job</button>
            </form>

            <div style="margin-top: 14px;" class="queue-window">
                <div class="queue-window-header">
                    <span>Queue Monitor</span>
                    <small>{{ now()->toDateTimeString() }}</small>
                </div>
                <div class="queue-window-body">
                    @if (! $queueSnapshot['available'])
                        <p class="window-note">{{ $queueSnapshot['reason'] }}</p>
                    @else
                        <div class="queue-stats">
                            <div class="queue-stat">
                                <span class="queue-stat-label">Total In Queue</span>
                                <span class="queue-stat-value">{{ $queueSnapshot['summary']['total'] }}</span>
                            </div>
                            <div class="queue-stat">
                                <span class="queue-stat-label">Ready Now</span>
                                <span class="queue-stat-value">{{ $queueSnapshot['summary']['ready'] }}</span>
                            </div>
                            <div class="queue-stat">
                                <span class="queue-stat-label">Delayed</span>
                                <span class="queue-stat-value">{{ $queueSnapshot['summary']['delayed'] }}</span>
                            </div>
                            <div class="queue-stat">
                                <span class="queue-stat-label">Reserved</span>
                                <span class="queue-stat-value">{{ $queueSnapshot['summary']['reserved'] }}</span>
                            </div>
                            <div class="queue-stat">
                                <span class="queue-stat-label">Failed Jobs</span>
                                <span class="queue-stat-value">{{ $queueSnapshot['summary']['failed'] }}</span>
                            </div>
                        </div>

                        <p class="window-note">Jobs waiting to run (oldest first):</p>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Job</th>
                                        <th>Queue</th>
                                        <th>Status</th>
                                        <th>Attempts</th>
                                        <th>Available At</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($queueSnapshot['jobs'] as $job)
                                        <tr>
                                            <td>{{ $job['id'] }}</td>
                                            <td>{{ $job['job_name'] }}</td>
                                            <td>{{ $job['queue'] }}</td>
                                            <td>{{ $job['status'] }}</td>
                                            <td>{{ $job['attempts'] }}</td>
                                            <td>{{ $job['available_at'] }}</td>
                                            <td>{{ $job['created_at'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">No jobs currently waiting.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if (count($queueSnapshot['failedJobs']) > 0)
                            <p class="window-note" style="margin-top:12px;">Most recent failed jobs:</p>
                            <div class="table-scroll" style="max-height: 180px;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Job</th>
                                            <th>Queue</th>
                                            <th>Failed At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($queueSnapshot['failedJobs'] as $failedJob)
                                            <tr>
                                                <td>{{ $failedJob['id'] }}</td>
                                                <td>{{ $failedJob['job_name'] }}</td>
                                                <td>{{ $failedJob['queue'] }}</td>
                                                <td>{{ $failedJob['failed_at'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Tracked Stores</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Domain</th>
                        <th>Platform</th>
                        <th>Products</th>
                        <th>Last Checked</th>
                        <th>Next Scheduled</th>
                        <th>Recurring</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stores as $store)
                        <tr>
                            <td>{{ $store->id }}</td>
                            <td>{{ $store->domain }}</td>
                            <td>{{ $store->platform }}</td>
                            <td>{{ $store->products_count }}</td>
                            <td>{{ $store->last_checked?->toDateTimeString() ?? 'Never' }}</td>
                            <td>{{ $store->next_sync_at?->toDateTimeString() ?? 'Not scheduled' }}</td>
                            <td>{{ $store->sync_interval_minutes ? 'Every '.(int) ($store->sync_interval_minutes / 60).' hour(s)' : 'Off' }}</td>
                            <td>
                                <div class="row">
                                    <form method="POST" action="{{ route('stores.sync', $store) }}">
                                        @csrf
                                        <button type="submit">Sync Now</button>
                                    </form>
                                    <form method="POST" action="{{ route('stores.set-interval', $store) }}" class="row">
                                        @csrf
                                        <select name="interval_hours" style="padding:6px; border:1px solid #cbd5e1; border-radius:6px;">
                                            @php $hours = (int) (($store->sync_interval_minutes ?? 0) / 60); @endphp
                                            <option value="0" {{ $hours === 0 ? 'selected' : '' }}>Off</option>
                                            <option value="1" {{ $hours === 1 ? 'selected' : '' }}>Every 1 hour</option>
                                            <option value="2" {{ $hours === 2 ? 'selected' : '' }}>Every 2 hours</option>
                                            <option value="3" {{ $hours === 3 ? 'selected' : '' }}>Every 3 hours</option>
                                            <option value="6" {{ $hours === 6 ? 'selected' : '' }}>Every 6 hours</option>
                                            <option value="12" {{ $hours === 12 ? 'selected' : '' }}>Every 12 hours</option>
                                            <option value="24" {{ $hours === 24 ? 'selected' : '' }}>Every 24 hours</option>
                                            <option value="48" {{ $hours === 48 ? 'selected' : '' }}>Every 48 hours</option>
                                            <option value="72" {{ $hours === 72 ? 'selected' : '' }}>Every 72 hours</option>
                                            <option value="168" {{ $hours === 168 ? 'selected' : '' }}>Every 7 days</option>
                                        </select>
                                        <button type="submit" class="btn-secondary">Set Interval</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No stores yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div style="margin-top: 12px;">
                {{ $stores->links() }}
            </div>
        </div>
    </div>
</body>
</html>
