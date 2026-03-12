# Shopify Scraper Guide (Laravel)

This guide is for building a Laravel app that lets authenticated users run and manage Shopify scraping jobs.

## 1) Goal and Scope

- Build a web app where users can:
- Sign up and log in.
- Add a Shopify store URL.
- Start a scrape job.
- View scraped products and job status.

Important:
- Only scrape public pages.
- Respect each site's Terms of Service and robots policy.
- Add rate limits and retries to avoid abusive traffic.

## 2) Phase 1: Authentication First (Sanctum)

Use Sanctum for API authentication and token/session protection.

Install API auth scaffolding:

```bash
php artisan install:api
php artisan migrate
php artisan serve
```

After this, verify:
- API auth routes exist in `routes/api.php`.
- You can register/login via API.
- Protected API route returns `401` when unauthenticated and `200` with a valid token/session.

## 3) App Architecture (High Level)

- `Store` model: one Shopify domain per user.
- `ScrapeJob` model: tracks each run (`queued`, `running`, `done`, `failed`).
- `Product` model: stores scraped product snapshots.
- Queue job: actual scraping should run in background, not in controller.

Use this relationship model:
- `User hasMany Store`
- `Store hasMany ScrapeJob`
- `Store hasMany Product`

## 4) Phase 2: Database Design

Create migrations for:

1. `stores`
- `id`
- `user_id` (foreign key)
- `domain` (unique per user)
- `is_active` (boolean)
- timestamps

2. `scrape_jobs`
- `id`
- `store_id` (foreign key)
- `status` (`queued`, `running`, `done`, `failed`)
- `started_at`, `finished_at`
- `error_message` (nullable text)
- timestamps

3. `products`
- `id`
- `store_id` (foreign key)
- `shopify_product_id` (nullable string)
- `title`
- `handle` (nullable)
- `url`
- `price` (nullable decimal)
- `currency` (nullable string)
- `raw_payload` (json nullable)
- `last_seen_at` (timestamp)
- timestamps

## 5) Phase 3: Scraping Strategy for Shopify

Start with the simplest public endpoints:

1. Try `/products.json?limit=250&page=1` (or similar pagination form supported by target store).
2. Fallback to HTML product listing pages if JSON is unavailable.
3. Normalize data into `products` table.

Implementation notes:
- Use Laravel HTTP client (`Http::timeout(...)->retry(...)`).
- Set a clear user-agent.
- Add per-domain delay between requests.
- Cap max pages per run to avoid runaway jobs.

## 6) Phase 4: Queues and Reliability

Run scraping in queued jobs:

```bash
php artisan queue:table
php artisan migrate
php artisan queue:work
```

Flow:
1. User clicks "Scrape now".
2. Controller creates `scrape_jobs` row with `queued`.
3. Dispatch queue job with `scrape_job_id`.
4. Job updates status to `running`, processes pages, writes products.
5. Job sets status to `done` or `failed`.

## 7) Phase 5: UI/API Surface

Minimum pages to build:

1. Auth API endpoints:
- `POST /api/register`
- `POST /api/login`
- `POST /api/logout`

2. Store endpoints:
- `GET /api/stores`
- `POST /api/stores`
- `GET /api/stores/{store}`

3. Scrape endpoints:
- `POST /api/stores/{store}/scrape`
- `GET /api/stores/{store}/jobs`
- `GET /api/stores/{store}/products`

Protect private API routes with `auth:sanctum` middleware.

## 8) Security and Abuse Controls

- Validate and normalize domain input (store domain only, no arbitrary URL fetching).
- Add policy checks so users only access their own stores/jobs/products.
- Throttle "Run scrape" action (for example: one active job per store).
- Log failures and network issues.
- Never execute scraped scripts or untrusted code.

## 9) Testing Plan

Write tests for:

1. Auth-required API routes (`->middleware('auth:sanctum')`).
2. User cannot view another user's store.
3. "Run scrape" creates a queued job.
4. Scraper job parses sample Shopify JSON into `products`.
5. Job failure sets `status = failed`.

Use `Http::fake()` for scraper tests so tests are fast and deterministic.

## 10) Suggested Build Order (Day 1 to Day 3)

Day 1:
- Install Sanctum API scaffolding.
- Create `Store`, `ScrapeJob`, `Product` migrations and models.
- Build auth API + store CRUD (auth protected).

Day 2:
- Add queue job and "Run scrape" button.
- Implement basic Shopify JSON scraper.
- Save products.

Day 3:
- Add retries, throttling, and better error handling.
- Add authorization policies.
- Add feature tests.

## 11) Immediate Next Step

Do this now:

1. Run `php artisan install:api` and `php artisan migrate`.
2. Confirm register/login/protected API route works with Sanctum.
3. Then scaffold models + migrations for `Store`, `ScrapeJob`, and `Product`.

If you want, next I can generate the exact artisan commands and starter code for Phase 1 and Phase 2 in this project.
