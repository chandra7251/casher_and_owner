# Technical Blueprint — Cafe Cashier POS

Status: Approved baseline
Version: 1.0

## 1. Architecture

Use one Laravel modular monolith. Inertia serves server-driven page navigation while React/TypeScript handles cashier interactions. MySQL is source of truth. No microservices and no offline client database.

```text
Android tablet
  → HTTPS
Laravel app
  ├─ Owner pages
  ├─ Cashier pages
  ├─ Order/payment/stock/table domains
  ├─ Reports/export
  └─ Print-job API
       → local Android print bridge
            → USB OTG → 58mm ESC/POS printer
```

## 2. Stack

- Laravel 12
- PHP 8.3+
- MySQL 8
- Inertia.js + React + TypeScript
- Tailwind CSS
- Pest
- Playwright
- Queue worker for expiry/print-related jobs where deployment supports it
- Redis optional; do not add until deployment or queue requirements justify it

## 3. Bounded modules

```text
Authentication
CafeSettings
Catalog
Tables
Orders
Payments
Stock
Receipts
Reports
Audit
```

Use services only for stateful workflows: submit order, expire order, confirm payment, adjust stock, export report. Simple CRUD stays in controllers/actions with Form Requests and policies.

## 4. Data model

Tables:

```text
users
categories
products
product_sizes
cafe_settings
tables
orders
order_items
payments
stock_reservations
stock_movements
receipt_print_jobs
audit_logs
```

Key fields:

- `product_sizes`: `product_id`, `size` enum Regular/Large, `price`, `on_hand`, `reserved`, `low_stock_threshold`, `is_available`.
- `orders`: `order_number`, `table_id`, `status`, `total`, `expires_at`, `paid_at`, `created_by`.
- `order_items`: product and size IDs plus immutable `product_name`, `size_name`, `unit_price`, `quantity`, `subtotal`.
- `payments`: `order_id`, `method`, `status`, `amount_paid`, `change_amount`, `confirmed_by`, `confirmed_at`.
- `stock_reservations`: `order_id`, `product_size_id`, `quantity`, `status`, `expires_at`, `consumed_at`, `released_at`.
- `receipt_print_jobs`: `order_id` unique, status, attempts, timestamps, error message.
- `audit_logs`: actor, action, entity type/id, old/new JSON, timestamp.

Use integer rupiah values. Add foreign keys, unique order number, unique product/size pair, and unique print job per order.

## 5. Critical transactions

### Submit order

1. Validate menu, size, quantity, and table.
2. Begin transaction.
3. Lock each selected `product_sizes` row in deterministic ID order.
4. Recalculate totals from server data.
5. Check available stock.
6. Increment `reserved`.
7. Create order, snapshots, and reservations.
8. Commit.

### Confirm payment

1. Lock order.
2. Reject if not `awaiting_payment` or expired.
3. Lock reservations and stock rows.
4. Validate amount and method.
5. Mark payment paid.
6. Set order paid.
7. Decrement reserved and on-hand.
8. Mark reservations consumed.
9. Create one print job with unique `order_id`.
10. Write audit log.
11. Commit.

### Expire order

Scheduled command/job finds `awaiting_payment` where `expires_at <= now()`. Lock order, recheck state, release reservation, set expired, and commit. Safe to run repeatedly.

## 6. Routes

```text
/login
/owner/dashboard
/owner/catalog
/owner/stock
/owner/tables
/owner/reports
/owner/settings
/cashier/orders
/cashier/orders/{order}
/cashier/tables
/cashier/history
/cashier/print-jobs/{printJob}
```

Use role middleware plus Policies. Never rely on hidden navigation.

## 7. Printing contract

The cloud app creates a print job after payment. Android bridge authenticates with a scoped device token and polls or receives a short-poll response. Bridge requests receipt payload/ESC-POS data, sends it to USB printer, then reports `printed` or `failed` with error. Do not expose unrestricted job mutation.

Printing spike is required before full implementation because silent Android USB printing is device- and bridge-dependent.

## 8. Reports

Use server-side query objects/actions. Paid and expired are separate status groups. CSV uses UTF-8 with BOM for spreadsheet compatibility if needed. PDF uses a dedicated print layout with repeated table headers and page totals. Do not render dashboard HTML as PDF.

## 9. Performance

- Index order status/date, payment status/confirmed_at, reservations expiry, foreign keys, and product availability.
- Select only needed catalog columns.
- Paginate history and reports.
- Aggregate reports in SQL.
- Cache cafe settings and catalog only after invalidation rules are tested.
- Keep Redis optional.
- Do not use Octane for MVP.

## 10. Security

- Laravel password hashing, CSRF, HTTPS, session expiry.
- Form Requests at trust boundaries.
- Policies for every owner/cashier mutation.
- Rate-limit login and print bridge endpoints.
- Validate upload MIME/size and store outside executable paths.
- Never trust client prices, totals, stock, role, or payment state.
- Audit sensitive changes.

## 11. Verification gates

Before implementation phase completes:

```text
Pest unit/integration tests pass
Playwright cashier happy path passes
Owner/cashier authorization tests pass
Reservation expiry test passes
Concurrent stock test passes
Double payment test passes
Receipt 58mm snapshot/manual print check passes
CSV/PDF export checks pass
```
