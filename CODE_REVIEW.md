# Code Review — Priyanthi Multi Stores Ordering Platform

**Scope:** Full review of the Laravel / Filament application in this repository
(branch `arena/01a05d9f-randalu-pc`, commit `e73d227`).

**Method:** Static read of the complete codebase (routes, controllers, services,
models, Filament resources, migrations, seeders, Blade views, tests, config).
PHP is not installed in this environment, so the test suite was **not executed**;
findings are from code inspection only.

> Note: no standalone "report" document exists in the repository or workspace.
> This review therefore covers the application code itself, which is the only
> reviewable deliverable present.

---

## 1. Executive summary

The codebase is a well-structured, single-purpose ordering platform for a Sri
Lankan bedsheet retailer. It has a clean service layer, disciplined use of
database transactions and row locks, pervasive audit logging, a guarded order
state machine, SMS/OTP-based public order tracking, and a solid feature-test
suite covering the core flows.

The most important issues to address before trusting it in production are:

1. **Known default admin credentials are re-applied on every seed** (High).
2. **Stock is deducted on confirmation but never returned when an order is
   cancelled** (High — data integrity).
3. **Authorization is inconsistent for staff users** — staff can mutate orders
   and inventory even though the role hierarchy suggests otherwise (Medium).
4. **The "stock adjustment note" feature is dead** — the note is never persisted
   (Medium).
5. **OTP verification is not rate-limited**, leaving a brute-force window on the
   6-digit code (Medium).

---

## 2. What is done well

- **Clean layering.** Business logic lives in dedicated services
  (`OrderStatusService`, `InventoryAdjustmentService`, `OrderOtpService`,
  `SmsService`, `SmsTestService`, `EventLogger`, `SriLankanPhone`) rather than
  in controllers.
- **Concurrency safety.** Stock deduction on order confirmation and manual stock
  edits both use `DB::transaction()` + `lockForUpdate()`, which correctly
  prevents overselling under concurrent admin actions.
- **Audit trail.** A consistent `EventLogger` records orders, status changes,
  SMS, OTP, inventory and settings events with severity levels, IP, phone and
  user attribution, and fails safely (logged, never breaks the user flow).
- **Order state machine.** `Order::ALLOWED_STATUS_TRANSITIONS` + `canTransitionTo()`
  prevent illegal jumps; dispatch requires courier + tracking number.
- **Anti-enumeration messaging.** The OTP endpoint returns the same generic
  message whether or not orders exist for a phone number.
- **OTP hygiene.** 6-digit code, hashed at rest in cache, 10-minute TTL,
  single-use (forgotten on success), per-phone + per-IP rate limiting.
- **Admin hardening.** Filament multi-factor authentication is **required** for
  the admin panel; the password field hashes via the `hashed` cast and enforces
  a minimum length.
- **Phone normalization.** `SriLankanPhone` cleanly handles local (`077…`),
  international (`+94…`) and stripped (`94…`, `7…`) formats.
- **Idempotent, defensive data setup.** Seeders use `updateOrCreate`; migrations
  guard with `Schema::hasColumn`; settings backfill uses `updateOrInsert`.
- **Test coverage.** Feature tests exercise the storefront, checkout, stock
  deduction, status transitions, dispatch validation, OTP send/verify,
  rate-limiting, SMS templating, settings authorization, and MFA redirect.
- **Blade output is escaped by default**; WhatsApp messages are `urlencode`d.

---

## 3. Findings

### High severity

#### H1 — Known default admin password re-applied on every seed
- `database/seeders/DatabaseSeeder.php` does `User::updateOrCreate(['email' =>
  env('ADMIN_EMAIL', 'admin@bedsheets.ptree.lk')], [..., 'password' =>
  Hash::make(env('ADMIN_PASSWORD', 'ChangeMeNow!2026'))])`.
- The default credentials are published in `README.md` and `.env.example`.
- Running `php artisan migrate --seed --force` in production (as the README
  instructs) **resets the admin's password to the known default** if
  `ADMIN_PASSWORD` is not set.
- `database/migrations/2026_05_10_000200_...` additionally auto-promotes the
  **first user** to `super_admin` when the admin email isn't found — a
  surprising privilege escalation on a pre-existing database.

**Recommendation:** stop writing the password on re-seed (only create if the
admin does not exist), fail loudly if `ADMIN_PASSWORD` is unset in non-local
environments, and remove the default password from the README/`.env.example`
(or make it clearly a local-only placeholder).

#### H2 — Stock is never returned when an order is cancelled
- `OrderStatusService::update()` decrements stock exactly once when an order
  first reaches `confirmed` (guarded by `confirmed_at === null`).
- `cancelled` is a legal transition from `new`, `confirmed`, `processing`,
  `packed`, and `dispatched` (see `Order::ALLOWED_STATUS_TRANSITIONS`).
- Cancelling an order **after** confirmation therefore permanently removes the
  reserved stock, and the order can never re-enter a fulfillable state
  (`cancelled => []`).

**Recommendation:** on transition to `cancelled`, if `confirmed_at` is set and
stock was deducted, increment stock back and write a matching
`InventoryMovement` (reason e.g. `order_cancelled`), inside the same transaction.

### Medium severity

#### M1 — Staff users can mutate orders and inventory
- `User` defines three roles and gates catalog/users/settings with
  `canManageCatalog()`, `canManageUsers()`, `canManageSettings()`, but there is
  **no `canManageOrders()` / inventory gate**.
- `OrderResource` overrides only `canCreate()`; `canViewAny`, `canEdit`,
  `canDelete`, `canDeleteAny` fall back to Filament's permissive defaults, so
  **any panel user (including `staff`)** can edit orders, run the quick status
  actions (confirm → deduct stock, dispatch → send SMS), etc.
- `ProductVariantResource` overrides only `canViewAny()` (`auth()->check()`), so
  staff can also create, edit, and **bulk-delete** inventory variants.

**Recommendation:** add a `canManageOrders()` / `canManageInventory()` helper and
explicit `can*()` overrides (or Laravel Policies) on `OrderResource` and
`ProductVariantResource`, aligned with the intended role model.

#### M2 — Stock adjustment note is never persisted
- `ProductVariantForm` declares the field with `->dehydrated(false)`:
  `Textarea::make('adjustment_note')->dehydrated(false)`.
- `EditProductVariant::handleRecordUpdate()` reads `$data['adjustment_note']`,
  but because the field is dehydrated, it is absent from `$data`, so `$note` is
  always `null`.
- The helper text ("Saved only when stock quantity changes") is therefore
  misleading — the note is **never** written to `inventory_movements.note`.

**Recommendation:** remove `->dehydrated(false)` and keep the existing
`unset($data['adjustment_note'])` in `handleRecordUpdate()`.

#### M3 — OTP verification has no rate limit
- `OrderOtpService::send()` is rate-limited (3 per 5 min per phone + IP), but
  `verify()` is not.
- The code is a 6-digit numeric OTP with a 10-minute lifetime; an unthrottled
  client can attempt brute force against the verify endpoint within the window.

**Recommendation:** rate-limit `verify()` too (e.g. cap attempts per phone/IP,
then invalidate the code), and consider a shorter OTP lifetime or lockout after
N failures.

#### M4 — Checkout does not re-validate variant availability
- `CheckoutController::store()` builds the order from the session cart and
  current DB prices, but never re-checks that each variant (and its parent
  product/category) is still `is_active`, nor that stock is > 0.
- A product deactivated between add-to-cart and checkout can still be ordered;
  stock is only validated later at confirmation time.

**Recommendation:** inside the checkout transaction, re-verify `is_active` and
`stock_quantity > 0` for each line (and fail gracefully with a message).

### Low severity

- **L1 — Broken admin favicon.** `AdminPanelProvider` references
  `asset('favicon.png')`, which does not exist in `public/` and is listed in
  `.gitignore`; `public/favicon.ico` is a 0-byte file.
- **L2 — Duplicated cart logic.** `cartItems()` is copy-pasted between
  `CartController` and `CheckoutController`; drift risk. Extract to a
  `CartService`.
- **L3 — Phone matching loads 250 rows in PHP.** `OrderOtpService::hasOrdersForPhone()`
  and `OrderStatusController::show()` both fetch the latest 250 orders and filter
  in PHP with `SriLankanPhone::same()`. Works for a small store; becomes slow and
  incomplete past 250 orders. A normalized-phone column + SQL index would scale.
- **L4 — Landline numbers accepted.** `SriLankanPhone::normalize()` accepts any
  10-digit `0…` number (e.g. a landline) as a "mobile" number. Validate the
  mobile prefix (e.g. `07x` / `+947x`).
- **L5 — Client IP behind proxies.** `$request->ip()` is used for OTP
  rate-limiting and event logging. Behind CyberPanel/Cloudflare without trusted
  proxies configured, all requests may share one proxy IP, weakening per-IP
  limits (and mis-logging IPs).
- **L6 — Checkout phone not validated.** `customer_phone` accepts any string
  (`max:40`); an invalid number is only discovered later at the OTP step. Add a
  Sri Lankan phone rule at checkout.
- **L7 — Order-number entropy.** `PMS-YYYYMMDD-####` uses `random_int(1, 9999)`;
  fine for this scale but the `checkout.success` route exposes any order by
  number (`{order:order_number}`). The page only renders the order number today,
  so exposure is limited — keep it that way and avoid adding PII to it.
- **L8 — Dead/conflicting code.** `InventoryMovementForm` is wired to a resource
  whose create/edit are disabled, and `ViewInventoryMovement` shows an
  `EditAction` for a read-only resource; `EventLogResource::form()` returns an
  empty schema; `resources/views/welcome.blade.php` is unreachable;
  `resources/legacy/index-static.html` (≈1.5k lines) is an orphaned legacy page
  with placeholder `https://example.com/` canonicals.
- **L9 — Test hygiene.** All feature tests live in `tests/Feature/ExampleTest.php`;
  `tests/Unit/ExampleTest.php` is boilerplate. No CI workflow is present.
- **L10 — Defaults in `.env.example`.** `APP_DEBUG=true` and `LOG_LEVEL=debug`
  are ship-as-is defaults; production `.env` must override them.
- **L11 — `activeVariants()` ordering.** The `orderByRaw` CASE hard-codes the two
  sizes (`90 x 90`, `90 x 100`); new sizes silently sort to the end.
- **L12 — Agent "memory" files committed.** `.Jules/palette.md` and
  `.jules/bolt.md` (and the `⚡ Bolt:` comments) are agent tooling artifacts in
  the repo; `.Jules/palette.md` carries an implausible date (2024-05-10).
  Consider excluding these from source control.

---

## 4. Notes and observations (non-blocking)

- `EventLog` correctly disables `UPDATED_AT` and the migration creates only
  `created_at`; indexes on `(type, created_at)` and `(severity, created_at)`
  support the log views.
- `Setting::getValue()` correctly evaluates the fallback **outside** the cache
  closure (avoids caching `null`) and invalidates on `saved`/`deleted` — matches
  the `.jules/bolt.md` note.
- `StorefrontController::collection()` avoids an N+1 by injecting the loaded
  parent category (`setRelation('category', $category)`) — good.
- `OrderStatusService::update()` re-fetches each variant with `lockForUpdate()`
  twice per item (once to validate, once to decrement); functionally correct,
  slightly redundant queries.
- Status actions in `OrdersTable` (confirm → processing → pack → dispatch →
  deliver → cancel) correctly hide themselves via `canTransitionTo()`.
- `DashboardStats` "Dispatched/Delivered today" key off `updated_at` while
  "Today order value" uses `created_at` — intentional per the descriptions, but
  easy to misread.
- Email/SMS failures are caught and logged so a mail/SMS outage never blocks an
  order or a status change — good for a small-store COD workflow.

---

## 5. Suggested priorities

| # | Action | Severity |
|---|--------|----------|
| 1 | Harden admin seeding (don't reset password; drop published default) | High |
| 2 | Restock on order cancellation | High |
| 3 | Add order/inventory authorization for staff | Medium |
| 4 | Fix `adjustment_note` dehydration | Medium |
| 5 | Rate-limit OTP verification | Medium |
| 6 | Re-validate availability at checkout | Medium |
| 7 | Address the Low items (favicon, cart service, phone column, etc.) | Low |

The codebase is otherwise in good shape: coherent architecture, sensible
transactions, solid audit logging, and meaningful test coverage of the business
rules.
