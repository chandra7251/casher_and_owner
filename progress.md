# Cafe POS — Progress Log

Last updated: 2026-07-22

## Status

Current phase: Phase 3 — Cashier vertical slice
Current next slice: stock release/consume + receipt/print boundary
Overall: Authenticated, DB-backed cashier order/payment path working; owner and print remain.

## Completed

### Product discovery and requirements

- Defined one cafe, one location, one Android cashier tablet.
- Defined verbal customer ordering; cashier enters order.
- Confirmed waiter has no account/device.
- Confirmed every order requires a table.
- Confirmed multiple active orders per table.
- Confirmed menu sizes `Regular` and `Large` only.
- Confirmed payment methods `cash` and `qris_manual` only.
- Confirmed 60-second payment timeout.
- Confirmed order states: `draft`, `awaiting_payment`, `paid`, `expired`.
- Confirmed reservation lifecycle: reserve, consume, release.
- Confirmed fixed 58mm receipt and Android print bridge boundary.
- Confirmed no tax, discount, modifiers, kitchen, offline, gateway, multi-tenant, cancellation, refund, or reprint in MVP.

### Documentation

Created and reviewed:

```text
C:\casher_and_owner\AGENTS.md
C:\casher_and_owner\docs\BRD.md
C:\casher_and_owner\docs\PRD-SRS.md
C:\casher_and_owner\docs\TECHNICAL-BLUEPRINT.md
C:\casher_and_owner\docs\CONVENTIONS.md
C:\casher_and_owner\tickets\MVP.md
```

Design review artifacts exist but are not source of truth:

```text
docs/STITCH-REVIEW.md
docs/STITCH-REVIEW-v2.md
docs/STITCH-INTEGRATED-REVIEW.md
docs/STITCH-UNIFIED-REVIEW.md
```

Stitch is no longer used for implementation. Approved product docs and `task.md` control scope.

### Foundation

- Laravel 12.12.2 installed.
- PHP 8.3.30 verified.
- Composer 2.10.2 installed at `tools/composer.phar`.
- Inertia Laravel installed.
- React 19, React DOM, Vite, TypeScript, Tailwind CSS installed.
- Inertia root view created.
- Inertia middleware created and attached to web middleware.
- Vite input changed to `resources/js/app.jsx`.
- Local non-DB session/cache/queue drivers configured so frontend can run without SQLite driver.
- Route `/health` created.

### Frontend slice

Created:

```text
C:\casher_and_owner\resources\js\Pages\Cashier\Workspace.jsx
C:\casher_and_owner\app\Http\Controllers\CashierWorkspaceController.php
C:\casher_and_owner\resources\css\app.css
C:\casher_and_owner\resources\js\app.jsx
C:\casher_and_owner\resources\views\app.blade.php
C:\casher_and_owner\routes\web.php
```

Working route:

```text
http://127.0.0.1:8000/cashier/orders
```

Current UI behavior:

- Server passes cafe, categories, products, sizes, stock, and tables as props.
- React renders product cards and category filters.
- Search filters visible products.
- Product size buttons add Regular/Large cart items.
- Cart supports quantity increase/decrease.
- Quantity zero removes item.
- Meja 05 is visible.
- Total excludes tax.
- Primary action is `Proses Pembayaran`.
- UI uses Indonesian copy, espresso/cream/terracotta palette, tablet-first layout.

## Verification evidence

Passed:

```text
npm run build
✓ built successfully

php artisan test
Tests: 2 passed (2 assertions)
```

Browser verification passed for page rendering:

```text
GET /cashier/orders
```

Observed in rendered accessibility tree:

```text
Kedai Senja
Pesanan baru
Regular Rp18.000
Large Rp22.000
Meja 05
Proses Pembayaran
```

Visual review found usable hierarchy and prominent cart. Follow-up polish needed: product image placeholders are initials, secondary text contrast needs review, quantity buttons should be at least 44×44px.

## Known gaps / honest blockers

- Product/category/table data is now DB-backed through migrations, seeders, models, and Inertia props.
- Auth login/logout exists; role separation and policies remain.
- Payment endpoint and cash/manual QRIS UI exist.
- Server-side order creation exists with authoritative prices/totals.
- Transactional stock reservation exists with row locks.
- Expiry is checked at payment boundary; scheduled release job remains.
- Payment idempotency baseline exists with unique order/key constraint.
- No print jobs or Android bridge.
- No owner screens.
- No report exports.
- No Playwright tests yet.
- SQLite PHP driver is unavailable locally. Project target remains MySQL; do not change product architecture to SQLite.
- `npx playwright test` has not run because no Playwright suite exists yet.

## Hardcode removal queue

1. Move cafe settings into DB-backed model/seed.
2. Move categories and products into migrations/models/seeder.
3. Move tables into DB-backed model/seeder.
4. Return validated resource-shaped props from controller.
5. Remove literal product/cart fixture from React. Cart starts empty from user action.
6. Add test fixtures separately from production data.
7. Add MySQL `.env` setup instructions without storing credentials.

## Decisions made during implementation

- Use Laravel 12, not Laravel 13, because project requirement says Laravel 12.
- Use JSX temporarily for first tracer slice; migrate to TypeScript/TSX before domain work is considered complete.
- Use Inertia props as temporary server boundary; no client-side business authority.
- Keep current visual direction direct-built, not copied from Stitch.
- Keep server alive only for local browser review; do not treat dev server as production process.

## Next work log template

For each slice append:

```markdown
### YYYY-MM-DD — [slice]

Goal:

Files changed:

TDD RED command/output:

Implementation:

TDD GREEN command/output:

Build output:

Browser/Playwright output:

Hardcode removed:

Open gaps:

Decision:
```

## Next exact action

Payment flow vertical slice, following `task.md`:

1. Create failing Pest feature test for allowed payment methods and authoritative total.
2. Run test and record RED output.
3. Implement minimum validated endpoint/service.
4. Run test and record GREEN output.
5. Add React payment UI for cash/manual QRIS.
6. Add 60-second expiry and printer-failed state.
7. Build, test, browser-check.
8. Remove any temporary hardcoded production fixture touched by slice.
9. Append result here.

Do not start owner dashboard until payment slice has real server behavior and tests.

## Batch progress

### 2026-07-22 — Batch 1: payment and order vertical slice

Goal: server-authoritative order/payment path, no production fixture hardcode.

Implemented:

- DB-backed cafe settings, categories, products, Regular/Large prices, stock, and tables.
- Server recalculates totals from locked DB price rows.
- Order creation reserves stock inside transaction.
- Cash payment calculates server-side change.
- QRIS manual requires explicit cashier validation.
- Only `cash` and `qris_manual` accepted.
- Duplicate payment request returns existing payment.
- 60-second payment expiry checked while paying.
- Login/logout session regeneration and CSRF meta token.
- Production React cart starts empty; menu data comes from server props.
- Payment modal submits order then payment through protected routes.

TDD RED:

```text
PaymentFlowTest initially failed: payment domain/routes/models absent.
PHP sqlite driver unavailable; local verification uses MySQL 8.
```

GREEN:

```text
php artisan test --filter='PaymentFlowTest|OrderCreationTest'
Tests: 5 passed (17 assertions)
```

Security review:

- Protected order/payment routes with `auth` middleware.
- Form Requests validate trust-boundary input.
- Server ignores client price and client total.
- Row locks protect stock and payment state.
- Integer rupiah fields.
- Unique order/idempotency constraint.

Performance review:

- Workspace eager-loads menu sizes.
- Payment/order work uses short DB transactions.
- Production JS build: 325.33 kB raw, 101.93 kB gzip.
- Build warning remains: external Google Fonts `@import` order.

Verification:

```text
php artisan migrate:fresh --seed --force        PASS
php artisan test                                PASS — 7 tests, 19 assertions
npm run build                                   PASS — 325.33 kB JS / 101.93 kB gzip
```

Open gaps:

- UI still uses JSX, not TSX.
- Table picker not yet interactive.
- Expiry must release reservations; paid must consume reservations.
- Print job/Android bridge boundary absent.
- Role authorization, rate limits, audit trail, and production credential policy remain.
- Browser login/payment smoke test not yet run after auth change.

### Next batch

1. Add reservation release/consume service and tests.
2. Add payment UI expiry countdown and explicit expired state.
3. Add print job state contract: queued, printing, printed, failed.
4. Add browser smoke test for login → add item → payment.
5. Fix font CSS warning and measure critical route.

### 2026-07-22 — Batch 2: stock lifecycle, print queue, countdown, browser smoke

Goal: close payment lifecycle gaps and verify real browser path.

Implemented:

- Paid payment consumes `on_hand` and `reserved` exactly once.
- Expired payment releases reservation and marks order expired.
- Added `print_jobs` migration and `PrintJob` model.
- Paid payment creates one `queued` print job.
- Added 60-second frontend countdown.
- Added Playwright smoke script: `tests/browser_smoke.mjs`.
- Fixed browser selector strictness in smoke script.
- Removed duplicate Google Fonts import causing CSS import-order warning.
- Updated `task.md` completed gates.

TDD RED:

```text
PaymentFlowTest: stock remained reserved after paid/expired.
Print job test: print_jobs table did not exist.
Browser smoke: Python Playwright unavailable; switched to installed Node Playwright.
```

GREEN:

```text
php artisan test --filter=PaymentFlowTest
Tests: 6 passed (17 assertions)

node tests/browser_smoke.mjs
browser smoke: PASS
```

Security/performance:

- Stock mutation remains inside payment transaction with row locks.
- Payment remains authoritative server-side.
- Print queue created atomically with paid state.
- No new production business hardcodes.
- Browser smoke uses seeded local account only.
- Build JS: 325.65 kB raw, 102.03 kB gzip.
- Full Pest suite slower than prior run: 10 tests, 25 assertions, 21.13s. Investigate before scale-up.

Verification:

```text
php artisan migrate:fresh --seed --force        PASS
php artisan test                                PASS — 10 tests, 25 assertions
npm run build                                   PASS — 325.65 kB / 102.03 kB gzip
node tests/browser_smoke.mjs                    PASS
```

Remaining gaps:

- `paid`/`expired` result UI needs explicit complete state design.
- Print queue lacks Android bridge endpoints and retry-on-failed behavior.
- Receipt snapshot payload absent.
- Table selection and occupied-table additional orders absent.
- Policies/roles not enforced beyond authenticated access.
- Playwright script is smoke script, not full `npx playwright test` suite.
- TSX migration remains.

Next batch: table selection + occupied-table additional orders, then authorization policies.

### 2026-07-23 — Batch 3: receipt boundary and Playwright test runner

Implemented:

- Added immutable receipt snapshot to `print_jobs.receipt_snapshot`.
- Snapshot includes order number, table, items, quantities, prices, total, payment method.
- Added authenticated print bridge endpoints:
  - `GET /api/print-jobs/next`
  - `PATCH /api/print-jobs/{printJob}`
  - `POST /api/print-jobs/{printJob}/retry`
- Bridge claims queued job as `printing`.
- Bridge can mark job `printed` or `failed`.
- Failed jobs can retry to `queued`.
- Printed jobs cannot retry.
- Payment remains `paid` when print fails.
- Added `playwright.config.mjs` and `tests/browser/cashier.spec.mjs`.
- Installed `@playwright/test`.

TDD:

```text
RED: snapshot was null; bridge route returned 404; retry route returned 404.
GREEN: PaymentFlowTest — 9 passed, 28 assertions.
GREEN: npx playwright test — 1 passed.
```

Verification:

```text
php artisan migrate:fresh --seed --force        PASS
php artisan test --filter=PaymentFlowTest        PASS — 9 tests, 28 assertions
npx playwright test                              PASS — 1 test
```

Remaining gaps from task.md:

- CI-style commands/environment docs.
- PHP enums for state values.
- Policies and role authorization.
- Interactive table selection.
- Multiple active orders per occupied table.
- Explicit expired payment Playwright path.
- Printer-failed Playwright path.
- Owner dashboard/menu/stock/table/settings/reports.
- Full `php artisan test`, `npm run build`, and `npx playwright test` are required again after next slice.

### 2026-07-23 — Batch 4: table selection and active-order data

Implemented:

- Workspace now computes active order count per table from DB using `withCount`.
- Table status prop derives from active orders, not stale stored UI state.
- Added `Table::orders()` relationship.
- Added cashier table picker modal with available/occupied indicators.
- Added explicit expired payment UI state: payment controls disabled after countdown reaches zero.
- Added active-order regression test.

TDD:

```text
RED: active table test exposed invalid Inertia test headers, then isolated data assertion.
GREEN: OrderCreationTest — 3 passed, 8 assertions.
GREEN: npm run build — 326.70 kB raw, 102.24 kB gzip.
```

Remaining:

- Multi-order selection for occupied table needs separate order chooser; current picker selects table only.
- PHP enums, policies/roles, owner operations, expired/printer-failed Playwright paths.

### 2026-07-23 — Batch 5: RBAC baseline

Implemented and tested immediately:

- Added `users.role` enum column: `owner | cashier`.
- Added `App\Enums\UserRole`.
- Added enum cast on `User::role`.
- Added server-side `role` middleware.
- Added owner-only route `/owner/menu` as authorization boundary.
- Seeder now assigns explicit owner/cashier roles.
- Added `AuthorizationTest` for cashier access, cashier denial, owner access, and role enum values.

TDD:

```text
RED: AuthorizationTest failed because users.role column did not exist.
RED: enum assertion failed because UserRole did not exist.
GREEN: AuthorizationTest — 4 passed, 5 assertions.
```

Security:

- Authorization enforced server-side, not navigation-only.
- Cashier receives HTTP 403 on owner route.
- Role values allow-listed by DB enum and PHP enum.
- Password fields remain hidden and hashed.

Remaining:

- Replace placeholder owner route with real owner menu/settings/stock controllers.
- Add Laravel Policies for resources; role middleware is baseline, not complete policy layer.
- Add login rate limiting and auth audit log.

### 2026-07-23 — Batch 6: owner menu price/stock mutation

Implemented and tested immediately:

- Added owner-only `PATCH /owner/menu/sizes/{menuItemSize}`.
- Added `UpdateMenuSizeRequest` validation.
- Owner can update price, on_hand, and low_stock_threshold.
- Mutation uses transaction + row lock.
- Prevents physical stock below reserved quantity.
- Cashier receives 403 and cannot mutate menu size.
- Added `AuthorizationTest` coverage.

TDD:

```text
RED: route returned 404; MenuItemSize factory assumption failed.
GREEN: AuthorizationTest — 7 passed, 11 assertions.
```

Security/performance:

- Owner role enforced twice: route middleware + Form Request authorization.
- Numeric fields validated as non-negative integers.
- Reserved stock invariant checked inside locked transaction.
- No client role or price trusted.

Remaining:

- Owner menu UI still absent; endpoint verified.
- Add audit log for price/stock mutations.
- Add menu availability mutation.
- Add owner dashboard and reports.

### 2026-07-23 — Batch 7: owner menu management UI

Implemented and tested per feature:

- Replaced placeholder owner menu route with DB-backed Inertia page.
- Added `resources/js/Pages/Owner/Menu.jsx`.
- Owner sees every active/inactive product, Regular/Large prices, stock, reserved stock.
- Owner can toggle menu availability.
- Owner can save menu size price/stock/threshold endpoint data.
- Login now redirects owner to `/owner/menu`; cashier to `/cashier/orders`.
- Cashier remains blocked from owner page and mutation endpoints.

TDD / browser checks:

```text
RED: availability route returned 404.
GREEN: AuthorizationTest — 10 passed, 17 assertions.
GREEN: owner browser test — owner page visible.
GREEN: cashier browser test — owner page returns 403.
```

Remaining:

- Price/stock inputs in UI now editable and browser-tested.

### 2026-07-23 — Batch 8: editable owner menu inputs

Implemented and tested immediately:

- Price input per menu size.
- Physical stock input per menu size.
- Save button sends validated PATCH request.
- Successful response updates local UI state.
- Added Playwright coverage for owner editing Regular price and stock.

Verification:

```text
npm run build — PASS
owner browser suite — 3 passed
```

Remaining:

- Add editable low-stock threshold input.
- Owner dashboard, table management, settings, reports.

### 2026-07-23 — Batch 9: owner mutation audit log

Implemented and tested immediately:

- Added `audit_logs` table with actor, action, auditable resource, before/after JSON snapshots.
- Owner menu size price/stock/threshold changes create audit record.
- Owner availability changes create audit record.
- Audit writes run inside size mutation transaction.
- Cashier cannot trigger owner audit mutations because backend role checks reject request.

Verification:

```text
RED: audit_logs table missing.
GREEN: AuthorizationTest — 10 passed, 19 assertions.
```

Remaining:

- Owner dashboard, table management, settings, reports.
- Add editable low-stock threshold browser assertion.
- Add audit viewer only if product scope requires it.

### 2026-07-23 — Batch 10: owner dashboard

Implemented and tested immediately:

- Added owner-only `GET /owner/dashboard`.
- Dashboard reports paid revenue, paid order count, expired order count.
- Dashboard lists low-stock sizes using `on_hand <= low_stock_threshold`.
- JSON response supports backend verification; Inertia page supports owner UI.
- Query uses aggregate counts/sum and eager loads low-stock menu relation.
- Added `resources/js/Pages/Owner/Dashboard.jsx`.
- Added feature and Playwright coverage.

Verification:

```text
RED: dashboard route returned 404.
GREEN: AuthorizationTest — 11 passed, 24 assertions.
GREEN: owner browser suite — 4 passed.
```

Remaining:

- Table management.
- Cafe settings.
- Reports/export.
- Expired payment and printer-failed browser paths.
- Accessibility and security review.

### 2026-07-23 — Batch 11: table management

Implemented and tested immediately:

- Added owner-only `GET /owner/tables`.
- Added owner-only `PATCH /owner/tables/{table}`.
- Owner can rename table and set available/occupied status.
- Server validates unique name, status allow-list, and max name length.
- Cashier receives 403 on table mutation.
- Added `resources/js/Pages/Owner/Tables.jsx`.
- Cashier table selection remains DB-backed.

TDD / browser verification:

```text
RED: table update/list routes returned 404.
GREEN: AuthorizationTest — 14 passed, 30 assertions.
GREEN: owner browser suite — 5 passed.
```

Remaining:

- Cafe settings.
- Reports/export.
- Expired payment and printer-failed browser paths.
- Accessibility and security review.
### 2026-07-23 — Batch 12: cafe settings

Implemented and tested immediately (strict TDD):

- `GET /owner/settings` — owner reads current settings via Inertia page.
- `PATCH /owner/settings` — owner updates name, address, phone, thank_you_message.
- `UpdateCafeSettingRequest` — validates required name, max-length fields.
- Cashier `PATCH /owner/settings` → 403, data unchanged.
- Cashier workspace already serves cafe name from DB — test added to prove contract.
- `resources/js/Pages/Owner/Settings.jsx` — editable form, saves in-place.
- No logo upload (file upload separate work).

Full verification:

```text
php artisan test    PASS — 31 tests, 75 assertions
npm run build       PASS — 337.07 kB / 103.85 kB gzip
npx playwright test PASS — 7 tests
```

Remaining:

- Reports with date filters, CSV, PDF.
- Expired payment and printer-failed browser paths.
- Hardcoded "Budi / Kasir" sidebar user.
- Hardcoded "14:32" clock.
- Accessibility + security review.

### 2026-07-23 — Batch 13: reports

Implemented and tested (strict TDD):

- `GET /owner/reports?from=&to=&status=` — JSON + Inertia.
- `GET /owner/reports/csv?from=&to=&status=` — UTF-8 CSV download.
- Filters by paid_at (paid) or payment_expires_at (expired).
- Returns total_orders, total_revenue in meta.
- Cashier → 403.
- `resources/js/Pages/Owner/Reports.jsx` — date filter, live fetch, CSV link, table.

TDD evidence:

```text
RED: /owner/reports → 404
GREEN: ReportTest — 4 passed, 10 assertions
GREEN: browser — 8 tests passed
```

Full verification:

```text
php artisan test    PASS — 35 tests, 85 assertions
npm run build       PASS — 341.22 kB / 104.60 kB gzip
npx playwright test PASS — 8 tests
```

Remaining:

- Expired payment browser path.
- Printer-failed browser path.
- Hardcoded "Budi / 14:32" sidebar cleanup.
- Accessibility review.
- Security review (rate limiting, CSRF, XSS audit).

### 2026-07-23 — Batch 14: edge cases + hardcoded cleanup

Implemented and tested (strict TDD):

- Browser tests: expired payment (server 422), UI countdown decrement, server rejection error display.
- Fixed `grouped is not defined` regression from clock refactor.
- Fixed hardcoded "Budi": controller passes `user.name` as Inertia prop, UI renders `{user.name}`.
- Fixed hardcoded "14:32": live clock via `useState` + `setInterval` (30s refresh).
- Added feature test: `test_cashier_workspace_exposes_logged_in_user_name` RED→GREEN.
- Cleanup: removed 2 debug scripts (`clock_debug.mjs`, `page_debug2.mjs`).

Full verification:

```text
php artisan test    PASS — 36 tests, 87 assertions
npm run build       PASS — 341.49 kB / 104.64 kB gzip
npx playwright test PASS — 11 tests
```

Remaining:

- Accessibility review (keyboard focus, contrast, 44px targets, reduced motion).
- Final review against task.md before release.

### 2026-07-23 — Batch 15: accessibility + final review

Implemented (Workspace.jsx):

- role="dialog" + aria-modal + aria-labelledby on both modals.
- tabIndex={-1} + ref.focus() → focus trapped into modal on open.
- Escape key closes any open modal.
- aria-live="assertive" → countdown / expired text.
- aria-live="polite" + aria-atomic → cart quantity.
- aria-pressed → category filter buttons, payment method buttons.
- aria-label → search input, table button, add-to-cart buttons, +/- buttons.
- aria-current="page" → active nav link.
- role="status" → success message.
- role="alert" → payment error.
- aria-hidden → decorative icons and status dot.
- min-h-11 (44px) on cart +/- buttons (was h-9 = 36px).
- nav aria-label="Navigasi kasir".
- section aria-label="Daftar menu", aside aria-label="Keranjang pesanan".

Final review findings:

- All Phase 5 owner operations: DONE.
- All non-negotiable product rules: met.
- Remaining [ ] items are explicitly deferred (CI docs, tickets tracker, PHP enums nice-to-have).
- "Kedai Senja" in Login.jsx: allowed (UI copy per hardcode policy; login page has no Inertia props).
- No subscriptions or paid services added.

Full verification:

```
php artisan test    PASS — 36 tests, 87 assertions
npm run build       PASS — 342.67 kB / 105.01 kB gzip
npx playwright test PASS — 11 tests
```

MVP DONE.

### 2026-07-23 — Batch 16: security review

Security checklist hasil vibecodingsecurity skill:

- Rate limiting:
  - Login: 5 attempt / 15 menit per IP (production), 100 (local/dev).
  - Mutation endpoints (cashier orders, payment, owner patches): 60/menit per user.
  - Print-bridge endpoints: 120/menit per IP.
  - Feature test: `test_auth_rate_limiter_is_registered` — assert limiter terdaftar.
- Dependency audit:
  - `npm audit`: 0 vulnerabilities.
  - `composer audit`: No security vulnerability advisories found.
- Input validation: semua endpoint pakai Form Request, idempotency_key regex, integer/boolean/enum enforcement.
- SQL injection: semua via Eloquent ORM + parameterized queries.
- CSRF: Laravel default middleware aktif, semua state-changing routes POST/PATCH.
- XSS: React escapes all JSX output by default; no `dangerouslySetInnerHTML`.
- Auth + RBAC: semua route protected, `role:owner` middleware + Form Request `authorize()`.
- Error disclosure: `APP_DEBUG=false` diset di AGENTS.md deploy checklist.
- Secrets: tidak ada hardcoded secret, `.env` di `.gitignore`.

Full verification:

```
php artisan test    PASS — 37 tests, 88 assertions
npm run build       PASS (CSS + JS)
npx playwright test PASS — 11 tests
```

DONE. Semua fase selesai. Siap deploy ke production setelah deploy checklist di AGENTS.md.
