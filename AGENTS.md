# AGENTS.md — Cafe Cashier POS

## Read first

Before changing code, read:

1. `docs/BRD.md`
2. `docs/PRD-SRS.md`
3. `docs/TECHNICAL-BLUEPRINT.md`
4. `docs/CONVENTIONS.md`
5. Relevant ticket in `tickets/MVP.md`

## Product rules

- One cafe, one location, one cashier tablet.
- Laravel backend with Inertia React TypeScript frontend.
- MySQL is source of truth.
- Waiter has no account or tablet.
- Every order requires a table.
- Multiple orders per occupied table are allowed.
- Sizes: Regular and Large only.
- Roles: owner and cashier.
- Owner alone edits prices, stock, thresholds, and settings.
- Payment methods: cash and manual QRIS.
- Order states: `draft`, `awaiting_payment`, `paid`, `expired`.
- Default payment timeout: 60 seconds.
- Reserve stock while awaiting payment; consume on paid; release on expired.
- Paid is final in MVP. No cancel, refund, or reprint.
- No offline mode.
- One fixed 58mm receipt layout.
- Printer failure never rolls back payment.

## Engineering rules

- Never trust client total, price, stock, role, or status.
- Recalculate money on server using integer rupiah values.
- Use Form Requests and Policies.
- Use transactions and row locks for stock/payment transitions.
- Make payment confirmation and print-job creation idempotent.
- Store immutable order item snapshots.
- Add audit logs for payment, stock, price, menu availability, table status, and exports.
- Do not add Redis, Octane, microservices, offline sync, or custom template builders without a revised requirement.

## UI rules

- Tablet-first, Indonesian labels, large touch targets.
- Always include loading, empty, error, timeout, and printer-failed states.
- Keep cashier cart and payment total prominent.
- Never hide authorization behind frontend navigation only.

## Verification

Run relevant tests before reporting completion. At minimum for order/payment work:

```bash
php artisan test
```

For browser flows:

```bash
npx playwright test
```

Report real command output. Do not claim untested behavior.

## Deploy checklist (security)

Before deploying to production:

1. Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.
2. Set `APP_KEY` (run `php artisan key:generate` if not set).
3. Set `DB_*` production credentials.
4. Run `php artisan config:cache` and `php artisan route:cache`.
5. Confirm `.env` is NOT committed (`git status`).
6. Run `php artisan test` and `npx playwright test` — both must pass.
7. Run `composer audit` and `npm audit` — zero high/critical.
8. `APP_DEBUG=false` prevents stack traces leaking to client.

Rate limiting configured:

- Login: 5 attempts / 15 min per IP.
- Mutation endpoints: 60 / min per authenticated user.
- Print-bridge endpoints: 120 / min per IP.
