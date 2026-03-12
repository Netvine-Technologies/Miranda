# Testing Guide

This project uses Laravel's test runner (`php artisan test`) with an in-memory SQLite database in test mode.

## Run tests

```bash
php artisan test
```

Run a single suite:

```bash
php artisan test --filter=SyncShopifyStoreProductsTest
php artisan test --filter=PriceWatchSnapshotServiceTest
php artisan test --filter=InternalApiSignatureTest
php artisan test --filter=ProcessPriceWatchSubscriptionsTest
```

## What each test class guarantees

## `Tests\Feature\SyncShopifyStoreProductsTest`
- Shopify pagination is processed correctly.
- Product and variant records are upserted (not duplicated).
- `price_history` rows are created only when meaningful variant state changes.

## `Tests\Unit\PriceWatchSnapshotServiceTest`
- Snapshot uses latest `price_history` row per variant.
- Lowest *in-stock* price is preferred.
- Falls back to out-of-stock rows when needed.
- Returns `unknown` state when no rows exist.

## `Tests\Feature\InternalApiSignatureTest`
- Internal watch-subscription endpoint rejects unsigned requests.
- Valid HMAC signed requests are accepted.
- Replay of same request id is rejected.

## `Tests\Feature\ProcessPriceWatchSubscriptionsTest`
- First run sets baseline without emailing users.
- Alert modes (like `price_drop`) behave correctly.
- Cooldown prevents rapid repeated notifications.

## Notes
- Tests isolate behavior with fakes/mocks (HTTP and notifications).
- Queue is sync in tests to keep assertions deterministic.
