<?php

namespace App\Jobs;

use App\Models\PriceWatchSubscription;
use App\Notifications\PriceWatchAlertNotification;
use App\Services\PriceWatchSnapshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class ProcessPriceWatchSubscriptions implements ShouldQueue
{
    use Queueable;

    public function handle(PriceWatchSnapshotService $snapshotService): void
    {
        // Process in chunks to keep worker memory stable on large subscriber sets.
        PriceWatchSubscription::query()
            ->where('status', PriceWatchSubscription::STATUS_ACTIVE)
            ->with('canonicalProduct:id,slug,title')
            ->chunkById(200, function ($subscriptions) use ($snapshotService): void {
                $snapshotCache = [];

                foreach ($subscriptions as $subscription) {
                    $canonicalProductId = $subscription->canonical_product_id;

                    if (! isset($snapshotCache[$canonicalProductId])) {
                        // Snapshot once per canonical product per chunk to avoid duplicate DB work.
                        $snapshotCache[$canonicalProductId] = $snapshotService->snapshotForCanonicalProduct($canonicalProductId);
                    }

                    $snapshot = $snapshotCache[$canonicalProductId];
                    $hasSnapshot = $snapshot['lowest_price'] !== null || $snapshot['stock_status'] !== 'unknown';

                    if (! $hasSnapshot) {
                        continue;
                    }

                    if ($this->hasMeaningfulChange($subscription, $snapshot) && ! $this->isInCooldown($subscription)) {
                        Notification::route('mail', $subscription->email)
                            ->notify(new PriceWatchAlertNotification($subscription->canonicalProduct, $subscription, $snapshot));

                        $subscription->last_notified_price = $snapshot['lowest_price'];
                        $subscription->last_notified_currency = $snapshot['currency'];
                        $subscription->last_notified_stock_status = $snapshot['stock_status'];
                        $subscription->last_notified_at = Carbon::now();
                    }

                    $subscription->last_checked_at = Carbon::now();
                    $subscription->save();
                }
            });
    }

    /**
     * @param array{lowest_price: float|null, currency: string|null, stock_status: string} $snapshot
     */
    protected function hasMeaningfulChange(PriceWatchSubscription $subscription, array $snapshot): bool
    {
        // First pass establishes baseline state so users are only emailed on subsequent change.
        if ($subscription->last_notified_stock_status === null && $subscription->last_notified_price === null) {
            $subscription->last_notified_price = $snapshot['lowest_price'];
            $subscription->last_notified_currency = $snapshot['currency'];
            $subscription->last_notified_stock_status = $snapshot['stock_status'];
            $subscription->last_notified_at = null;

            return false;
        }

        $previousPrice = $subscription->last_notified_price !== null ? round((float) $subscription->last_notified_price, 2) : null;
        $currentPrice = $snapshot['lowest_price'] !== null ? round((float) $snapshot['lowest_price'], 2) : null;
        $priceChanged = $previousPrice !== $currentPrice;
        $priceDropped = $previousPrice !== null && $currentPrice !== null && $currentPrice < $previousPrice;
        $stockChanged = (string) $subscription->last_notified_stock_status !== (string) $snapshot['stock_status'];
        $backInStock = (string) $subscription->last_notified_stock_status !== 'in_stock'
            && (string) $snapshot['stock_status'] === 'in_stock';

        $mode = (string) config('price_watch.alert_mode', 'any_change');

        return match ($mode) {
            'price_change' => $priceChanged,
            'price_drop' => $priceDropped,
            'stock_change' => $stockChanged,
            'back_in_stock' => $backInStock,
            default => $priceChanged || $stockChanged,
        };
    }

    protected function isInCooldown(PriceWatchSubscription $subscription): bool
    {
        $cooldownMinutes = (int) config('price_watch.cooldown_minutes', 0);
        if ($cooldownMinutes <= 0 || $subscription->last_notified_at === null) {
            return false;
        }

        // Cooldown limits notification frequency when prices oscillate rapidly.
        return $subscription->last_notified_at->copy()->addMinutes($cooldownMinutes)->isFuture();
    }
}
