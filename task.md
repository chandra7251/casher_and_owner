# Cafe POS — Master Workflow

Status: Active
Source of truth: this file + `progress.md` + approved docs in `docs/`

## Non-negotiable product rules

- One cafe, one location, one Android tablet.
- Cashier enters verbal customer orders. Waiter has no account/device.
- Every order requires a table. Multiple active orders per table allowed.
- Menu sizes exactly `Regular` and `Large`.
- Payment methods exactly `cash` and `qris_manual`.
- Payment timeout: 60 seconds.
- Order states: `draft`, `awaiting_payment`, `paid`, `expired`.
- Paid is final. No cancel, refund, preparing, completed, takeaway, or reprint.
- Stock: `on_hand`, `reserved`, `available = on_hand - reserved`.
- Reserve at `awaiting_payment`; consume at `paid`; release at `expired`.
- Money uses integer rupiah. Server recalculates prices, stock, totals, and status.
- Receipt: one fixed 58mm layout. Payment stays paid when printer fails.
- Cloud-only. No offline queue in MVP.

## Working rules

1. Read `AGENTS.md` before code changes.
2. Read relevant `docs/BRD.md`, `docs/PRD-SRS.md`, `docs/TECHNICAL-BLUEPRINT.md`, and `docs/CONVENTIONS.md`.
3. Pick one vertical slice. Do not build unrelated screens.
4. Use TDD for behavior: write failing test, run it, implement minimum code, run it again, refactor.
5. Use `frontend-design` skill for UI decisions: intentional visual system, real cafe context, accessibility, reduced motion, 44px touch targets.
6. Never hardcode business data in React. React receives server props/API data. Fixtures belong in seeders/factories/tests only.
7. Never trust frontend totals, prices, stock, role, or status.
8. Finish each slice with build, tests, and rendered browser check.
9. Update `progress.md` after every completed slice. Record failures honestly.
10. Do not mark a task complete from a written plan. Require real command output.

## Workflow gates

### Gate 1 — Discovery

- [x] Define users, cafe workflow, payment flow, table flow.
- [x] Confirm one cafe/location/tablet.
- [x] Confirm cashier-only device workflow.
- [x] Confirm cash + manual QRIS.
- [x] Confirm stock, 60-second timeout, fixed receipt, reports.
- [x] Record exclusions.

Output: `docs/BRD.md`.

### Gate 2 — Requirements

- [x] Define roles and permissions.
- [x] Define order/payment/table/stock/print states.
- [x] Define acceptance criteria.
- [x] Remove unsupported tax, discount, modifiers, kitchen, offline, gateway, and multi-tenant scope.

Output: `docs/PRD-SRS.md`.

### Gate 3 — Technical design

- [x] Define Laravel modular monolith.
- [x] Define Inertia + React + TypeScript boundary.
- [x] Define entities, snapshots, transactions, row locks, idempotency.
- [x] Define Android print bridge boundary.

Output: `docs/TECHNICAL-BLUEPRINT.md`.

### Gate 4 — Engineering conventions

- [x] Define naming, money, enums, validation, policies, snapshots, audit logs.
- [x] Define server-authoritative totals and stock mutation rules.

Output: `docs/CONVENTIONS.md` and `AGENTS.md`.

### Gate 5 — Planning

- [x] Break work into dependency-ordered tickets.
- [x] Keep one vertical slice per implementation cycle.
- [ ] Convert `tickets/MVP.md` into individually trackable tickets when scope grows.

Output: `tickets/MVP.md`, this file, `progress.md`.

## Implementation order

### Phase 0 — Foundation

- [x] Laravel 12 app created.
- [x] PHP 8.3 verified.
- [x] Composer installed locally at `tools/composer.phar`.
- [x] Inertia Laravel installed.
- [x] React + React DOM installed.
- [x] Vite + Tailwind CSS configured.
- [x] Inertia root view and middleware configured.
- [x] Set local MySQL connection. Do not use SQLite as project target.
- [x] Add baseline auth and role model.
- [ ] Add CI-style commands and environment documentation.

### Phase 1 — Domain data

- [x] Create migrations: users, cafe_settings, tables, menu_categories, menu_items, menu_item_sizes, stocks, orders, order_items, payments, print_jobs, audit_logs.
- [ ] Add PHP enums for order, payment, table, print states.
- [x] Add model relationships and casts.
- [x] Add seed data for Kedai Senja only in seeder.
- [x] Add factories for tests.
- [ ] Add policies for owner/cashier access.

### Phase 2 — Cashier order slice

- [x] Render cashier workspace from server props.
- [x] Show categories, products, both sizes, stock, table, cart, quantities, and total.
- [x] Load products/tables from DB, not controller literals.
- [x] Add table selection screen.
- [x] Add order creation endpoint with Form Request.
- [x] Recalculate authoritative total on server.
- [x] Reserve stock transactionally at `awaiting_payment`.
- [ ] Support adding another order to occupied table.

### Phase 3 — Payment slice

- [x] Add payment screen with only cash and manual QRIS.
- [x] Add 60-second countdown and expiry transition.
- [x] Validate cash received and calculate change server-side.
- [x] Require manual QRIS validation confirmation.
- [x] Make payment confirmation idempotent.
- [x] Consume stock exactly once after paid.
- [x] Release reservation exactly once after expired.
- [ ] Add paid and expired UI states.

### Phase 4 — Printing

- [x] Add immutable 58mm receipt data from order snapshots.
- [x] Add print job states: queued, printing, printed, failed.
- [x] Add Android print bridge contract.
- [x] Payment remains paid on print failure.
- [x] Show retry only when print state is failed.
- [ ] Do not add reprint feature.

### Phase 5 — Owner operations

- [x] Owner dashboard: paid revenue, paid orders, expired orders, low stock, recent transactions.
- [x] Menu management: name, Regular/Large prices, stock, thresholds, availability.
- [x] Table management: table numbers and availability.
- [x] Cafe settings: logo, name, address, phone, thank-you message.
- [x] Reports with date filters, all paid/expired transactions, CSV, clean PDF.

### Phase 6 — Quality

- [ ] Add Pest tests for each domain transition.
- [ ] Add feature tests for authorization and validation.
- [x] Add Playwright cashier happy path.
- [x] Add Playwright expired payment path.
- [x] Add printer-failed state test.
- [x] Run `php artisan test`.
- [x] Run `npm run build`.
- [x] Run `npx playwright test`.
- [x] Review keyboard focus, contrast, reduced motion, and 44px targets.
- [x] Remove hardcoded production fixtures from controllers and React.
- [x] Review against this file before release.

## Required checks for every slice

```bash
php artisan test
npm run build
npx playwright test
```

If a command cannot run, record exact blocker in `progress.md`; never claim pass.

## Hardcode policy

Allowed:

- UI copy and visual tokens.
- Test fixtures, factories, and seeders.
- Empty-state examples in tests.

Forbidden in production:

- Product prices in React.
- Stock in React.
- Table availability in React.
- Totals calculated only in React.
- Role/permission decisions in navigation only.
- Cafe identity duplicated across components.

Target boundary:

```text
DB → Laravel model/service/controller → Inertia props → React UI
```

## Definition of done

A slice is done only when:

- Requirement is represented in code.
- Failing test was observed first for behavior changes.
- Server validates and owns business truth.
- UI has loading, empty, error, timeout, and printer-failed state where relevant.
- `php artisan test`, `npm run build`, and relevant Playwright tests pass.
- Browser render was inspected.
- `progress.md` was updated.
- No unsupported feature entered the scope.
- No production business data remains hardcoded.

## Skill usage map

- `frontend-design`: every UI slice and visual review.
- `test-driven-development`: every behavior, bug fix, and domain transition.
- `systematic-debugging`: every unexpected error or failed test.
- `requesting-code-review`: before merging a meaningful slice.
- `plan`: before multi-file feature work; save plan under `.hermes/plans/`.
- `github-*`: only when repository/PR workflow starts.
- `hermes-agent`: only for Hermes configuration, not product code.

## Current next slice

Payment flow vertical slice:

1. Write failing feature test for payment choices and total.
2. Build server endpoint and validated Form Request.
3. Build React payment panel.
4. Add expiry behavior.
5. Test paid, expired, duplicate confirmation, and printer-failed states.
6. Review rendered tablet UI.
7. Update `progress.md`.
