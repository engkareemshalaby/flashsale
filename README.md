**Flashsale — Minimal Inventory & Holds API**

- **Purpose:** Small Laravel API that demonstrates safe temporary holds on inventory, order creation, and idempotent payment webhook handling without overselling under concurrent requests.

**Assumptions & Invariants**
- **Single source of truth:** MySQL (or SQLite for tests) stores products, holds and orders.
- **Active holds:** holds are active when `consumed = false` AND `expires_at > now()`.
- **Availability:** `available = product.stock - sum(active hold.qty)`.
- **Holds:** Created in a DB transaction with `SELECT ... FOR UPDATE` on the product row to avoid oversell.
- **Webhooks:** Stored by `idempotency_key` to make processing idempotent and safe if delivered before order creation.

**Quick Setup (Windows bash / Git Bash / WSL)**
- **Clone & install:**
  - `composer install`
- **Environment:** copy `.env.example` → `.env` and set DB credentials and `APP_URL`.
- **Migrate & seed:**
  - `php artisan migrate`
  - `php artisan db:seed` (seeds 10 example products)
- **Development server:**
  - `php artisan serve --host=127.0.0.1 --port=8000`

**Testing**
- Recommended test config (in `phpunit.xml` or `.env.testing`):
  - `DB_CONNECTION=sqlite`
  - `DB_DATABASE=:memory:`
  - `QUEUE_CONNECTION=sync`
- Run tests:
  - `php artisan test` or `./vendor/bin/phpunit --testdox`

**API (endpoints)**
- `GET /api/products/{id}`
  - Response: `{ id, name, price_cents, stock, available }`
- `POST /api/holds` — body: `{ product_id, qty }`
  - Success: `{ hold_id, expires_at }` (reduces available immediately)
  - Fails: `409` if insufficient stock
- `POST /api/orders` — body: `{ hold_id }`
  - Creates order with `status = pre_payment` and marks hold `consumed = true`.
- `POST /api/payments/webhook` — body: `{ idempotency_key, hold_id, status }`
  - Idempotent; stores idempotency record and updates order to `paid` or `cancelled`.

**Seeder**
- `database/seeders/ProductSeeder.php` creates 10 products (`Product 1`..`Product 10`) with sample `price_cents` and `stock`.
- `DatabaseSeeder` calls `ProductSeeder` and safely creates a test user if missing.

**How it works (short)**
- **Create hold:** `HoldController` runs a DB transaction and `lockForUpdate()` on the product row, computes active holds, and inserts a hold with `expires_at = now + 2 minutes`. It invalidates the product availability cache and dispatches an `ExpireHold` job delayed 2 minutes.
- **Expire hold:** `ExpireHold` invalidates the product availability cache when the hold is expired (and not consumed). Use a queue worker or `QUEUE_CONNECTION=sync` for tests.
- **Create order:** `OrderController` locks the hold row, verifies it's valid and not expired, creates the order and marks the hold consumed.
- **Payment webhook:** `PaymentWebhookController` stores idempotency records, updates the order if present, and handles webhooks that arrive before or after the order creation.

**Operational notes & recommendations**
- Use Redis as `CACHE_DRIVER` for better performance under burst traffic.
- Run queue worker for delayed expiry jobs in production: `php artisan queue:work`.
- Add a periodic sweep (artisan scheduled command) to clean/validate expired holds as a safety-net.
- Add structured metrics for contention and webhook duplicates (Prometheus/StatsD) if needed.

**Where to find logs**
- Laravel logs: `storage/logs/laravel.log` (contains warnings for contention and webhook duplicate logs).

**Troubleshooting**
- 404 on `/api/*` in tests: ensure `bootstrap/app.php` registers `routes/api.php` (this project does).
- Tests failing with "no such table": ensure migrations run and `RefreshDatabase` is enabled in `tests/TestCase.php`.
- Concurrency tests spawn `scripts/attempt_hold.php` — ensure PHP binary is available in PATH for the test runner.

**Files of interest**
- Controllers: `app/Http/Controllers/Api/*`
- Models: `app/Models/{Product,Hold,Order,WebhookIdempotency}.php`
- Jobs: `app/Jobs/ExpireHold.php`
- Tests: `tests/Feature/*` (covers concurrency, expiry, webhook idempotency, webhook-before-order)
- Seeder: `database/seeders/ProductSeeder.php`

If you want, I can add a simple `phpunit.xml` test config and a scheduled sweep command next.
