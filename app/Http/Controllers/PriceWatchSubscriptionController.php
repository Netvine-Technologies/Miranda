<?php

namespace App\Http\Controllers;

use App\Models\CanonicalProduct;
use App\Models\PriceWatchSubscription;
use App\Notifications\ConfirmPriceWatchNotification;
use App\Services\PriceWatchSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class PriceWatchSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'canonical_slug' => ['required', 'string', 'max:255'],
        ]);

        $canonicalProduct = CanonicalProduct::query()
            ->where('slug', $data['canonical_slug'])
            ->firstOrFail();

        $subscription = PriceWatchSubscription::firstOrNew([
            'email' => Str::lower($data['email']),
            'canonical_product_id' => $canonicalProduct->id,
        ]);

        if ($subscription->exists && $subscription->status === PriceWatchSubscription::STATUS_ACTIVE) {
            return response()->json([
                'message' => 'You are already subscribed to this product watch.',
            ]);
        }

        $subscription->fill([
            'status' => PriceWatchSubscription::STATUS_PENDING,
            'confirm_token' => Str::random(64),
            'unsubscribe_token' => $subscription->unsubscribe_token ?: Str::random(64),
            'confirmed_at' => null,
        ]);
        $subscription->save();

        Notification::route('mail', $subscription->email)
            ->notify(new ConfirmPriceWatchNotification($canonicalProduct, $subscription));

        return response()->json([
            'message' => 'Please check your email to confirm your subscription.',
        ], 201);
    }

    public function confirm(string $token, PriceWatchSnapshotService $snapshotService)
    {
        $subscription = PriceWatchSubscription::query()
            ->where('confirm_token', $token)
            ->firstOrFail();

        $snapshot = $snapshotService->snapshotForCanonicalProduct($subscription->canonical_product_id);

        $subscription->update([
            'status' => PriceWatchSubscription::STATUS_ACTIVE,
            'confirmed_at' => Carbon::now(),
            'confirm_token' => Str::random(64),
            'last_notified_price' => $snapshot['lowest_price'],
            'last_notified_currency' => $snapshot['currency'],
            'last_notified_stock_status' => $snapshot['stock_status'],
            'last_checked_at' => Carbon::now(),
        ]);

        return response('<h1>Subscription confirmed</h1><p>You will now receive price/stock alerts.</p>', 200)
            ->header('Content-Type', 'text/html');
    }

    public function unsubscribe(string $token)
    {
        $subscription = PriceWatchSubscription::query()
            ->where('unsubscribe_token', $token)
            ->firstOrFail();

        $subscription->update([
            'status' => PriceWatchSubscription::STATUS_UNSUBSCRIBED,
        ]);

        return response('<h1>Unsubscribed</h1><p>You will no longer receive alerts for this product.</p>', 200)
            ->header('Content-Type', 'text/html');
    }
}
