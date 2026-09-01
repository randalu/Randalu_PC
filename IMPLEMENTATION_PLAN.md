# Randalu PC — Improvement Implementation Plan

**Repository:** `randalu/Randalu_PC` · **Working branch:** `arena/01a05d9f-randalu-pc`
**Related artifacts:** `CODE_REVIEW.md` (findings this plan operationalizes), PR #1.

This document turns the 22 improvement suggestions into a phased, shippable
implementation roadmap. Each item lists objective, current state, exact changes,
edge cases, tests, acceptance criteria, and effort.

---

## How to read this plan

- **Item IDs** (`S1`, `A1`, `P1`, …) are stable references used across the plan.
- **Effort** — `S` ≈ half a day · `M` ≈ 1–2 days · `L` ≈ 3+ days (single developer).
- **Risk** — probability × impact of leaving it un-fixed.
- Each **phase** is independently shippable and mergeable.
- The **"Definition of done"** for every item: code reviewed, feature tests added
  where feasible, `php artisan test` green, and the acceptance criteria met.

---

## Phase summary

| Phase | Theme | Items | Goal |
|-------|-------|-------|------|
| **1** | Security & data integrity | S1, S2, S3, A1, A2, A3, A4, O3, O4 | Safe to run in production |
| **2** | Storefront & commerce UX | P1, P2, P3, P4, P5, P6 | A credible customer shopping experience |
| **3** | SMS & accounts hardening | M1, M2, M3, M4 | Reliable, observable, scalable messaging + accounts |
| **4** | Performance, ops & quality | O1, O2, O5 | Maintainable, fast, CI-guarded codebase |

**Dependency notes**

- `S3` (OTP verify rate-limit) is implemented in both `OrderOtpService` and
  `CustomerAuthService` in Phase 1; `M1` (unify OTP) refactors those into one
  service in Phase 3 **without changing behaviour**.
- `A3` (orders.customer_id) is a prerequisite for reliable account order
  history and for `M4` (admin Customers resource).
- `P6` (cart persistence) builds on `M1`'s service extraction only conceptually;
  it is independent and can slip to a later phase if desired.
- `O1` (cache catalog) depends on nothing, but is best done after `P5`
  (pagination/search) so the cache keys account for pagination.

---

## Phase 1 — Security & data integrity (ship before production)

### S1 — Harden admin seeding (no known-default password reset)

- **Objective:** seeding must never overwrite a live admin's password with a
  published default, and must fail fast on insecure configuration.
- **Current state:** `database/seeders/DatabaseSeeder.php` runs
  `User::updateOrCreate(['email' => env('ADMIN_EMAIL', 'admin@randalu-pc.lk')],
  [..., 'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMeNow!2026'))])`.
  `README.md` and `.env.example` publish the default. `migrate --seed --force`
  in production therefore resets the super-admin password.
  Additionally `database/migrations/2026_05_10_000200_…` auto-promotes the
  **first user** to `super_admin` if the admin email is not found.
- **Changes:**
  1. `DatabaseSeeder::run()` — only **create** the admin when absent; when the
     admin already exists, **do not touch the password** (log a notice).
  2. Add a guard: if `app()->environment('production')` and
     `ADMIN_PASSWORD` is missing or equals the known default, **throw** with a
     clear message.
  3. Remove the default password from `README.md`/`.env.example` (or mark it
     "local-only placeholder") and document `php artisan migrate --seed` vs a
     dedicated password-reset command.
  4. Add an optional `php artisan admin:create` command for provisioning a new
     super-admin explicitly.
  5. The auto-promote-first-user logic in migration `000200` is historical and
     already applied to existing DBs; leave the file unchanged (migrations are
     immutable history) but document the risk in the README.
- **Edge cases:** fresh install (admin absent → create with env password);
  re-seed on existing prod DB (admin present → skip password); missing env in
  prod (throw).
- **Tests:** seed twice with an altered `ADMIN_PASSWORD` and assert the password
  hash is unchanged on the second run; assert production guard throws.
- **Acceptance:** `migrate --seed --force` twice does not reset the password.
- **Effort:** S · **Risk:** High.

### S2 — Return stock when an order is cancelled

- **Objective:** stock deducted at `confirmed` must be returned when the order
  is cancelled, with a matching `inventory_movements` row.
- **Current state:** `app/Services/OrderStatusService.php` decrements stock once
  when an order first reaches `confirmed`. `cancelled` is a legal transition
  from `new`, `confirmed`, `processing`, `packed`, `dispatched`
  (`Order::ALLOWED_STATUS_TRANSITIONS`), so cancelling after confirmation
  permanently removes reserved stock.
- **Changes:**
  1. In `OrderStatusService::update()`, inside the existing `DB::transaction`,
     detect `nextStatus === 'cancelled' && previousStatus !== 'cancelled' &&
     $order->confirmed_at !== null`.
  2. For each order item with a non-null `product_variant_id`, re-lock the
     variant (`lockForUpdate()`), `increment('stock_quantity', $item->quantity)`,
     and create an `InventoryMovement` with `quantity_change = +qty`,
     `stock_after`, `reason = 'order_cancelled'`, `note = "Order {$order->order_number}"`.
  3. If a variant no longer exists (`order_items.product_variant_id` is
     `nullOnDelete`), skip it and log an `EventLogger` warning rather than fail
     the whole transition.
  4. Guard against double-restock: `canTransitionTo()` returns `true` for the
     *same* status, so explicitly require `previousStatus !== 'cancelled'`.
- **Edge cases:** cancel before confirmation (no restock — nothing was deducted);
  cancel after dispatch (restock returns items); variant deleted (skip + log);
  repeat cancel (idempotent via previous-status guard).
- **Tests:** confirm → cancel → assert stock restored and a
  `reason = order_cancelled` movement exists; cancel from `new` → no restock;
  cancel twice → only one restock.
- **Acceptance:** net stock change is zero for confirm→cancel; audit trail intact.
- **Effort:** M · **Risk:** High.

### S3 — Rate-limit OTP verification

- **Objective:** close the brute-force window on the 6-digit OTP verify step.
- **Current state:** `OrderOtpService::send()` and
  `CustomerAuthService::requestOtp()` are rate-limited (3/5 min per phone+IP),
  but `verify()` in both services is unthrottled; the code is valid for 10 minutes.
- **Changes:**
  1. Add `ensureVerifyRateLimit($phone, $ip)` to both services: e.g. **5 failed
     attempts per phone per 10 minutes** (and per IP), using the existing
     `RateLimiter` pattern.
  2. On limit breach: log `otp.verify_rate_limited` /
     `customer.otp_verify_rate_limited` (severity warning) and throw a
     `ValidationException`.
  3. On each failed verification, `RateLimiter::hit()` the verify keys; clear
     them on success.
  4. (Optional hardening) shorten OTP TTL from 10 → 5 minutes; invalidate the
     cached code after N failures.
- **Edge cases:** shared IP (NAT) throttles innocent users — keep phone-key as
  primary and IP as secondary with generous limits.
- **Tests:** 5 wrong attempts then a correct OTP is rejected with
  `otp`/`phone` error; event log records `verify_rate_limited`.
- **Acceptance:** an attacker cannot try more than N codes per phone within the TTL.
- **Effort:** S · **Risk:** High.

### A1 — Role-gated orders & inventory (stop staff overreach)

- **Objective:** staff users must not be able to mutate orders, deduct stock,
  send status SMS, or delete inventory.
- **Current state:** `User` has `super_admin`/`admin`/`staff` and gates catalog,
  users, settings, but **no order/inventory gate**. `OrderResource` overrides
  only `canCreate()`; `ProductVariantResource` overrides only `canViewAny()`
  (`auth()->check()`). Filament defaults the rest to `true`, so staff can edit
  orders, run the quick status actions, and bulk-delete variants.
- **Changes:**
  1. `app/Models/User.php` — add `canManageOrders(): bool` (super_admin || admin)
     and `canManageInventory(): bool` (reuse `canManageCatalog`).
  2. `OrderResource` — override `canViewAny`, `canEdit`, `canDelete`,
     `canDeleteAny` with `canManageOrders()`.
  3. `ProductVariantResource` — override `canViewAny`, `canCreate`, `canEdit`,
     `canDelete`, `canDeleteAny` with `canManageCatalog()`.
  4. `OrdersTable::statusAction()` — add `->visible(fn ($record) =>
     auth()->user()?->canManageOrders() && $record->canTransitionTo($status)
     && $record->status !== $status)`. (Custom row actions are not auto-gated
     by `canEdit`.)
  5. Optionally restrict `EventLogResource`/`InventoryMovementResource`
     `canViewAny` to admins, and `OrderStatusService` could assert
     `canManageOrders` as defence-in-depth.
- **Edge cases:** super-admin always allowed; staff can still *view* orders if
  desired (split `canViewAny` from `canEdit`).
- **Tests:** acting as staff, assert `OrderResource::canEdit()` is false and the
  status-action visibility closure is false; admin stays true.
- **Acceptance:** staff cannot reach any order/inventory mutation path.
- **Effort:** S · **Risk:** High.

### A2 — Re-validate cart at checkout

- **Objective:** prevent ordering deactivated or out-of-stock variants whose
  state changed after add-to-cart.
- **Current state:** `CheckoutController::store()` builds order + items from the
  session cart with no availability re-check; stock is only validated later at
  confirmation.
- **Changes:**
  1. Inside `CheckoutController::store()`'s transaction, for each cart line:
     re-fetch the variant, and `abort`/redirect with a friendly error if the
     variant is missing, `is_active = false`, its product/category is inactive,
     or `stock_quantity < quantity`.
  2. Recompute line totals from the **current** DB price (not a stale session value).
  3. Return a `ValidationException`/`withErrors` message listing the offending
     SKU(s) so the customer can fix their cart.
- **Edge cases:** variant deleted between cart and checkout; quantity increased
  via cart update beyond stock; inactive category.
- **Tests:** add variant → deactivate it → checkout rejected with error;
  stock 1 → quantity 2 → rejected.
- **Acceptance:** no order can contain an unavailable line.
- **Effort:** S · **Risk:** High.

### A3 — Link orders to `customer_id`

- **Objective:** make account order history reliable (not phone-only), and lay
  groundwork for the admin Customers resource.
- **Current state:** orders carry only `customer_phone`; the account page matches
  by phone (`SriLankanPhone::same`).
- **Changes:**
  1. New migration: add nullable `customer_id` FK on `orders`
     (`nullOnDelete`), indexed.
  2. `Order` model: add `customer_id` to `$fillable`, add `customer()` belongsTo.
  3. `CheckoutController::store()`: attach `session('customer_id')` when present.
  4. `CustomerAuthController::account()`: query orders by
     `customer_id = X OR customer_phone matches` (covers legacy orders and
     orders placed while logged out).
- **Edge cases:** guest orders (customer_id null) still show via phone match;
  customer deletes account (orders survive via nullOnDelete).
- **Tests:** logged-in checkout writes `customer_id`; account page shows both
  customer-linked and phone-matched orders.
- **Acceptance:** order history survives phone-number changes.
- **Effort:** M · **Risk:** High.

### A4 — Regenerate session on authentication

- **Objective:** prevent session fixation on OTP login.
- **Current state:** `CustomerAuthController::verify()` and
  `OrderStatusController::verify()` call `$request->session()->put(...)` without
  regenerating the session ID.
- **Changes:** after successful verification (and before writing the auth keys),
  call `$request->session()->regenerate();` in both controllers.
- **Edge cases:** none — `regenerate()` preserves existing session data.
- **Tests:** assert the session ID changes across verify (optional; standard
  Laravel behaviour is trusted).
- **Acceptance:** session ID rotates on login.
- **Effort:** S · **Risk:** Medium.

### O3 — Trust proxies for correct client IPs

- **Objective:** OTP rate limits and event-log IPs reflect the real client, not
  the proxy, behind CyberPanel/Cloudflare.
- **Current state:** no `TrustProxies` configuration; `$request->ip()` returns
  the proxy IP in production.
- **Changes:** in `bootstrap/app.php`'s `withMiddleware()`, add
  `$middleware->trustProxies(at: '*')` (or env-driven: only trust when
  `TRUSTED_PROXIES` is set), so Laravel reads `X-Forwarded-For` correctly.
  Document that Cloudflare/`REMOTE_ADDR` safety relies on this.
- **Edge cases:** blindly trusting all proxies when **not** behind a proxy lets
  clients spoof `X-Forwarded-For` — gate on an env flag.
- **Acceptance:** logs/rate-limits show real IPs behind the proxy; flag-off
  behaviour unchanged.
- **Effort:** S · **Risk:** Medium.

### O4 — Safe production defaults & favicon fix

- **Objective:** stop shipping debug logging/errors and fix the broken admin favicon.
- **Current state:** `.env.example` has `APP_DEBUG=true`, `LOG_LEVEL=debug`;
  `AdminPanelProvider` references `asset('favicon.png')` which does not exist.
- **Changes:**
  1. `.env.example`: `APP_DEBUG=false`, `LOG_LEVEL=warning`, with comments on
     enabling debug locally.
  2. `AdminPanelProvider`: `->favicon(asset('images/logo.png'))`.
  3. Remove `favicon.png` from `.gitignore` (or commit a real favicon).
- **Acceptance:** a fresh `.env` never exposes debug output; admin favicon loads.
- **Effort:** S · **Risk:** Low.

---

## Phase 2 — Storefront & commerce UX

### P1 — Delivery fee visible at checkout

- **Objective:** show the customer a real total (subtotal + delivery fee) instead
  of a fee added silently by admin later.
- **Current state:** checkout stores `total = subtotal`; admin edits
  `delivery_fee` after the fact. The customer never sees a final total.
- **Changes:**
  1. Add Settings keys: `delivery_fee` (flat amount, default `0`) and
     `delivery_fee_note` (e.g. "Within Colombo 350 LKR; other areas on confirmation").
  2. `CheckoutController::store()`: read `Setting::getValue('delivery_fee', '0')`,
     compute `total = subtotal + fee`, store it.
  3. `checkout.blade.php`: render subtotal, delivery fee, and total; keep the
     "admin confirms" note where zones vary.
  4. `OrderForm` already has a `delivery_fee` field — admin can still override.
- **Edge cases:** fee disabled (`0`); future zone-based fees (leave a `computeDeliveryFee()` seam).
- **Tests:** checkout with `delivery_fee` set → stored `total` includes it.
- **Acceptance:** customer sees a total at checkout; admin can still adjust.
- **Effort:** M · **Risk:** Medium.

### P2 — Out-of-stock handling on the storefront

- **Objective:** stop presenting stock-0 variants as orderable.
- **Current state:** the product page lists "Stock 0" options as selectable;
  cards show products even when all variants are sold out.
- **Changes:**
  1. `product.blade.php`: render `disabled` option + "(out of stock)" label for
     `stock_quantity <= 0`.
  2. `product-card.blade.php`: show a "Sold out" badge when every active variant
     has 0 stock.
  3. `CartController::add()`: validate `stock_quantity >= quantity` before adding
     (and cap to available stock).
- **Edge cases:** variant out of stock between page render and add (re-validate).
- **Tests:** cart.add rejects quantity > stock; sold-out product shows badge.
- **Acceptance:** customers cannot add unavailable stock.
- **Effort:** S · **Risk:** Medium.

### P3 — Product imagery

- **Objective:** replace the single placeholder image with per-product visuals.
- **Current state:** every seeded product uses `images/product-placeholder.png`;
  Filament `ProductForm` already supports `FileUpload` → `storage/products`.
- **Changes:**
  1. Generate/upload a distinct image per product (and per category) and set
     `image_path` in `ProductCatalogSeeder` accordingly (the `FileUpload`
     `dehydrateStateUsing` already prefixes `storage/` for new uploads).
  2. Add a square image-friendly aspect ratio note for admins (avoid stretching).
  3. (Optional) an image in the Filament table via existing `ImageColumn`.
- **Acceptance:** catalog looks like a real store; no products share one photo.
- **Effort:** M (asset production) · **Risk:** Low.

### P4 — Product spec comparison

- **Objective:** show hardware specs (variant/spec, price, stock) as a table on
  the product page, not only a dropdown.
- **Current state:** variants carry a free-text `size` (spec) + `price` +
  `stock_quantity`; the product page shows only a select.
- **Changes:**
  1. `product.blade.php`: render a spec table (Spec | Price | Stock) from
     `$product->activeVariants`, with the select kept for quick add-to-cart.
  2. (Optional) add a `specs` JSON column to `products` for detailed attributes
     (brand, wattage, socket, etc.) rendered as key/value rows.
- **Acceptance:** a buyer can compare variants at a glance.
- **Effort:** S–M · **Risk:** Low.

### P5 — Pagination & search improvements

- **Objective:** scale the catalog and make search faster.
- **Current state:** `StorefrontController` uses `->get()` (all rows) and
  `LIKE %term%` (non-sargable, no index); no pagination links in views.
- **Changes:**
  1. `index()`/`collection()`: `->paginate(12)` with `->withQueryString()` to
     preserve `?s=…`; add `{{ $products->links() }}` (or simple prev/next) to
     both views.
  2. Keep `LIKE` for now (fine at hundreds of SKUs); document full-text
     (`FULLTEXT` index on name/sku) as a follow-up when the catalog grows.
- **Edge cases:** search + category filters combined (preserve query string).
- **Acceptance:** large catalogs render in pages; search term survives pagination.
- **Effort:** S · **Risk:** Low.

### P6 — Persistent cart (survives session/merge on login)

- **Objective:** carts survive browser sessions and merge into the account on login.
- **Current state:** cart is session-only (`session('cart')`) and duplicated
  between `CartController` and `CheckoutController`.
- **Changes:**
  1. New migration `cart_items` (`session_id`/`customer_id`, `variant_id`,
     `quantity`, timestamps).
  2. New `CartService` (single source of truth) replacing the duplicated
     `cartItems()` in both controllers (also resolves the code-review L2 note).
  3. Store guest carts against a cookie session id; on OTP login, merge the
     guest cart into the customer's cart (sum quantities, cap 99).
- **Edge cases:** merge conflicts (sum vs replace — choose sum with cap);
  stale/deleted variants (prune on read).
- **Tests:** add → logout → login → cart persists; guest cart merges without
  losing lines.
- **Acceptance:** cart state survives login/logout and across sessions.
- **Effort:** L · **Risk:** Medium.

---

## Phase 3 — SMS & accounts hardening

### M1 — Unify OTP services

- **Objective:** remove duplicated OTP logic and guarantee consistent behaviour.
- **Current state:** `OrderOtpService` and `CustomerAuthService` duplicate
  normalize / cache / hash / rate-limit / verify logic.
- **Changes:**
  1. New `app/Services/OtpService.php` exposing `request($phone, $ip, $type)`
     and `verify($phone, $code, $type)` with typed event names.
  2. Refactor both services to delegate; keep their business specifics
     (`hasOrdersForPhone`, `firstOrCreate`) in the callers.
  3. Carry over the Phase-1 verify rate-limit so it now lives in one place.
- **Acceptance:** identical security posture, fewer lines, no behaviour drift.
- **Effort:** M · **Risk:** Low.

### M2 — Record SMS delivery metadata

- **Objective:** reconcile SMS cost/credit and diagnose delivery issues.
- **Current state:** `SmsService::send()` already logs `sms_credit_balance` in
  the `sms.sent` event; `campaign_id`/`pages` are not captured.
- **Changes:** add `campaign_id` and `pages` (from `data.campaign_id` /
  `data.pages`) to the `sms.sent` event `metadata`.
- **Acceptance:** every successful send's event shows campaign id, pages, balance.
- **Effort:** S · **Risk:** Low.

### M3 — Queue SMS sends

- **Objective:** stop synchronous SMS HTTP calls from adding latency to
  checkout/status pages.
- **Current state:** `SmsService::send()` performs a blocking
  `Http::timeout(15)` call; `QUEUE_CONNECTION=database` is already configured.
- **Changes:**
  1. New `app/Jobs/SendSms.php` (ShouldQueue) wrapping the send.
  2. `OrderSmsNotifier` (order status updates) dispatches the job instead of
     calling `SmsService` inline.
  3. **OTP sends stay synchronous** (latency matters for a 10-minute code);
     document this deliberate choice.
  4. Add `supervisor`/queue-worker guidance to README (`php artisan queue:work`).
- **Edge cases:** queue failure retries (set `tries`, backoff); idempotency
  (duplicate sends on retry) — acceptable for status SMS but note it.
- **Acceptance:** checkout/status requests don't wait on SMSlenz.
- **Effort:** M · **Risk:** Medium.

### M4 — Admin "Customers" resource

- **Objective:** let admins see customer accounts and their orders.
- **Current state:** no admin view of the new `customers` table.
- **Changes:**
  1. `app/Filament/Resources/Customers/CustomerResource.php` — table (name,
     phone, email, orders_count via `counts('orders')`, created_at), view page,
     and an orders relationship (either inline repeatable or a relation manager).
  2. Gate with `canManageUsers()` (super-admin) or `canManageOrders()` (admin+)
     per your access model; make it read-only.
  3. Add `orders()` hasMany to `Customer`.
- **Acceptance:** admins can find a customer and see their order list.
- **Effort:** M · **Risk:** Low.

---

## Phase 4 — Performance, ops & quality

### O1 — Cache the catalog

- **Objective:** reduce per-request DB load on the storefront.
- **Current state:** `StorefrontController` queries categories and
  products+variants on every request; `CACHE_STORE=database`.
- **Changes:**
  1. Cache the active-category list (`rememberForever` + invalidate on
     `Category::saved/deleted`), following the existing `Setting` pattern.
  2. Cache product listings with a short TTL keyed by `page|search|category`
     (works with `P5` pagination); invalidate on `Product`/`ProductVariant`
     `saved`/`deleted`.
  3. If key-based invalidation grows unwieldy with the database cache store,
     evaluate Redis for cache **tags**.
- **Edge cases:** stock changes must invalidate listing caches promptly
  (invalidate on `InventoryMovement`/variant stock change too).
- **Acceptance:** repeated storefront hits don't re-query; stock edits reflect
  within the TTL.
- **Effort:** M · **Risk:** Low.

### O2 — Prune event logs

- **Objective:** bound `event_logs` growth (it logs every SMS/OTP/order event).
- **Current state:** `event_logs` grows unbounded; no scheduled jobs exist.
- **Changes:**
  1. New `app/Console/Commands/PruneEventLogs.php` deleting logs older than a
     retention window (default 30 days, configurable via Setting/config).
  2. Register `Schedule::command('app:prune-event-logs')->daily()` in
     `routes/console.php`; note cron entry (`* * * * * php artisan schedule:run`)
     in README.
- **Acceptance:** log table size plateaus; retention is configurable.
- **Effort:** S · **Risk:** Low.

### O5 — CI + test hygiene

- **Objective:** continuous validation and maintainable tests.
- **Current state:** all feature tests live in `tests/Feature/ExampleTest.php`
  (447+ lines, mixed domains); `tests/Unit/ExampleTest.php` is boilerplate; no CI.
- **Changes:**
  1. Split tests into domain files (StorefrontTest, CheckoutTest,
     OrderStatusTest, SmsTest, CustomerAuthTest, AuthorizationTest).
  2. Add `.github/workflows/ci.yml`: PHP 8.3, `composer install`,
     `cp .env.example .env`, `php artisan key:generate`, run
     `php artisan migrate --env=testing` (sqlite) + `php artisan test`, plus
     `vendor/bin/pint --test`.
  3. Pin the MySQL service container only if tests require it (sqlite in-memory
     is already configured in `phpunit.xml`).
- **Acceptance:** every push/PR runs tests + style; failures block merge.
- **Effort:** M · **Risk:** Low.

---

## Sequencing & dependency map

```
Phase 1 (S1,S2,S3,A1,A2,A3,A4,O3,O4)  ← do first, all independent
        │
        ├── A3 ──► M4 (needs orders.customer_id)
        ├── S3 ──► M1 (unify rate-limited OTP)
        ▼
Phase 2 (P1,P2,P3,P4,P5,P6)           ← P5 before O1 (cache keys)
        │
        ▼
Phase 3 (M1,M2,M3,M4)                 ← M1 depends on S3 work
        │
        ▼
Phase 4 (O1,O2,O5)                    ← O1 after P5; O5 last (tests cover all)
```

Suggested implementation order (one PR per phase, or one per item for larger
ones like `P6` and `M1`):

1. **PR: Phase 1 items** — `S1..S3`, `A1..A4`, `O3`, `O4`.
2. **PR: Phase 2 storefront** — `P1..P5` (hold `P6` if too large for one PR).
3. **PR: cart persistence** — `P6`.
4. **PR: Phase 3 SMS/accounts** — `M1..M4`.
5. **PR: Phase 4 ops/quality** — `O1`, `O2`, `O5`.

---

## Testing strategy

- **Per item:** feature tests using the existing patterns (`RefreshDatabase`,
  `Http::fake` for SMS, `RateLimiter`/`Cache` for OTP).
- **Regression:** keep the current suite green as a baseline; never remove a
  test without replacing its coverage.
- **Auth matrix:** every role (super_admin/admin/staff/guest) × every
  order/inventory action, after `A1`.
- **SMS contract tests:** fake responses must mirror SMSlenz's real JSON
  (`success`, `data.campaign_id`, `data.pages`, `data.sms_credit_balance`).
- **Local run:** `php artisan test` (sqlite) — no external services required.

## Risks & mitigations

| Risk | Mitigation |
|------|-----------|
| `A1` locks staff out of legitimate tasks | Add `canManageOrders` to `admin` role; revisit role matrix with the owner |
| `S2` restock double-applies | Previous-status guard + movement audit test |
| `P6` cart merge loses items | Sum quantities (cap 99), prune deleted variants, test merge |
| `M3` queued SMS delayed | Keep OTP synchronous; set short `tries`/backoff for status SMS |
| `O1` stale cache shows wrong stock | Short TTL + invalidate on every stock mutation |
| `O3` spoofed X-Forwarded-For | Trust proxies only behind an env flag |

## Rollout checklist (production)

1. Merge Phase 1 and deploy **before** enabling SMS or public traffic.
2. `php artisan migrate --force` (customers FK, cart_items, etc.).
3. Set env: `APP_DEBUG=false`, `APP_ENV=production`, real SMSlenz credentials,
   `TRUSTED_PROXIES`, `QUEUE_CONNECTION=database`.
4. Configure cron (`schedule:run`) and a queue worker (`queue:work`).
5. Run `php artisan storage:link`; upload product images.
6. Post-deploy smoke test: place order → confirm → cancel → restock; login OTP;
   staff role cannot edit orders.
