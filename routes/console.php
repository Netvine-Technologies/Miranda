<?php

use App\Jobs\ProcessPriceWatchSubscriptions;
use App\Jobs\SyncShopifyStoreProducts;
use App\Models\CanonicalProduct;
use App\Models\Product;
use App\Models\PriceWatchSubscription;
use App\Models\Store;
use App\Notifications\ConfirmPriceWatchNotification;
use App\Notifications\PriceWatchAlertNotification;
use App\Services\CanonicalProductMatcher;
use App\Services\PriceWatchSnapshotService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('seo:canonicalize-products', function () {
    $matcher = app(CanonicalProductMatcher::class);
    $count = 0;

    Product::query()->chunkById(200, function ($products) use (&$count, $matcher): void {
        foreach ($products as $product) {
            $matcher->matchOrCreate($product);
            $count++;
        }
    });

    $this->info("Canonicalized {$count} products.");
})->purpose('Match existing products to canonical product records');

Artisan::command('watch:test-subscribe {email} {slug?}', function () {
    $email = Str::lower((string) $this->argument('email'));
    $slug = $this->argument('slug');

    $canonicalProduct = CanonicalProduct::query()
        ->when($slug, fn ($query) => $query->where('slug', $slug))
        ->orderBy('id')
        ->first();

    if (! $canonicalProduct) {
        $this->error('No canonical product found.');

        return;
    }

    $subscription = PriceWatchSubscription::firstOrNew([
        'email' => $email,
        'canonical_product_id' => $canonicalProduct->id,
    ]);

    $subscription->fill([
        'status' => PriceWatchSubscription::STATUS_PENDING,
        'confirm_token' => Str::random(64),
        'unsubscribe_token' => $subscription->unsubscribe_token ?: Str::random(64),
        'confirmed_at' => null,
    ]);
    $subscription->save();

    Notification::route('mail', $subscription->email)
        ->notify(new ConfirmPriceWatchNotification($canonicalProduct, $subscription));

    $this->info('Queued confirmation email.');
    $this->line("Email: {$subscription->email}");
    $this->line("Slug: {$canonicalProduct->slug}");
    $this->line("Confirm URL: ".route('watch-subscriptions.confirm', ['token' => $subscription->confirm_token]));
    $this->line("Unsubscribe URL: ".route('watch-subscriptions.unsubscribe', ['token' => $subscription->unsubscribe_token]));
})->purpose('Create/update a watch subscription and queue a confirmation email');

Artisan::command('watch:test-alert {email} {slug}', function () {
    $email = Str::lower((string) $this->argument('email'));
    $slug = (string) $this->argument('slug');

    $canonicalProduct = CanonicalProduct::query()
        ->where('slug', $slug)
        ->first();

    if (! $canonicalProduct) {
        $this->error("Canonical product not found for slug: {$slug}");

        return;
    }

    $subscription = PriceWatchSubscription::firstOrCreate(
        [
            'email' => $email,
            'canonical_product_id' => $canonicalProduct->id,
        ],
        [
            'status' => PriceWatchSubscription::STATUS_ACTIVE,
            'confirm_token' => Str::random(64),
            'unsubscribe_token' => Str::random(64),
            'confirmed_at' => now(),
        ]
    );

    if ($subscription->status !== PriceWatchSubscription::STATUS_ACTIVE) {
        $subscription->status = PriceWatchSubscription::STATUS_ACTIVE;
        $subscription->confirmed_at = now();
        $subscription->save();
    }

    $snapshot = app(PriceWatchSnapshotService::class)->snapshotForCanonicalProduct($canonicalProduct->id);

    Notification::route('mail', $email)
        ->notify(new PriceWatchAlertNotification($canonicalProduct, $subscription, $snapshot));

    $this->info('Queued test alert email.');
    $this->line("Email: {$email}");
    $this->line("Slug: {$canonicalProduct->slug}");
})->purpose('Send an immediate test price alert email for a canonical product');

Schedule::call(function (): void {
    Store::query()
        ->whereNotNull('next_sync_at')
        ->where('next_sync_at', '<=', now())
        ->get()
        ->each(function (Store $store): void {
            SyncShopifyStoreProducts::dispatch($store->id);

            $nextSyncAt = $store->sync_interval_minutes
                ? now()->addMinutes($store->sync_interval_minutes)
                : null;

            $store->update(['next_sync_at' => $nextSyncAt]);
        });
})->everyMinute();

Schedule::job(new ProcessPriceWatchSubscriptions)->everyFiveMinutes();

Artisan::command('queue:last-failed', function () {
    $row = DB::table('failed_jobs')->orderByDesc('id')->first();

    if (! $row) {
        $this->info('No failed jobs.');

        return;
    }

    $this->line('UUID: '.$row->uuid);
    $this->line('Connection: '.$row->connection);
    $this->line('Queue: '.$row->queue);
    $this->line('Failed at: '.$row->failed_at);
    $this->line('Exception:');
    $this->line($row->exception);
})->purpose('Show latest failed job exception');
