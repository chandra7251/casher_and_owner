# PRD/SRS — Cafe Cashier POS

Status: Approved requirements baseline
Version: 1.0

## 1. Product

A cloud-based cashier POS for one cafe. Customers use a physical menu; cashier enters verbal orders on one Android tablet. Waiter has no system account.

## 2. Roles and permissions

| Capability | Owner | Cashier |
|---|---:|---:|
| Login | yes | yes |
| Cafe settings | manage | no |
| Categories/menu | manage | read active only |
| Regular/Large price | manage | read |
| Stock/threshold | manage | read availability |
| Tables | manage configuration | use and update status |
| Create/edit unpaid order | no/optional read | yes |
| Confirm payment | no/optional read | yes |
| Reports and exports | yes | no |
| Audit log | read | no |

Waiter has no login.

## 3. State model

Order: `draft → awaiting_payment → paid` or `awaiting_payment → expired`.

Payment: `unpaid → paid` or `unpaid → expired`.

Table: `available | occupied`.

Reservation: `reserved → consumed` or `reserved → released`.

Print job: `queued → printing → printed | failed`.

## 4. Functional requirements

### FR-01 Authentication

Owner and cashier have separate protected routes. Server-side authorization must reject cross-role URL access. Record actor and timestamp for important actions.

### FR-02 Cafe settings

Owner manages name, logo, address, thank-you message, and currency. Empty phone field is omitted from receipt; phone is not required for MVP.

### FR-03 Menu

Owner manages categories and products. Each product has exactly two sizes: Regular and Large. Each size stores price, stock quantity, low-stock threshold, and availability. Cashier sees active available sizes only.

### FR-04 Tables

Owner configures table numbers. Cashier must select a table for every order. Occupied tables may receive an additional order. Cashier marks a table available only after waiter physically confirms it is free. The system warns if active orders remain.

### FR-05 Order entry

Cashier selects category, product, size, quantity, and table. Server recalculates item prices, subtotals, and total. Order item stores product name, size name, unit price, quantity, and subtotal snapshot. Order can be edited only before payment confirmation.

### FR-06 Reservation and expiry

On submit, server locks the relevant stock row and checks `on_hand - reserved`. If sufficient, create reservation and set `awaiting_payment`. Default expiry is 60 seconds. Expiry releases reservation and preserves order in history. Expired order cannot be paid.

### FR-07 Cash

Cashier enters amount received. Server validates amount is at least total, calculates change, records payment method, amount, cashier, and timestamp, then atomically marks order paid, consumes reservation, and creates one print job.

### FR-08 Manual QRIS

Static QRIS is displayed physically at cashier. Cashier validates money received outside the system, enters amount manually, and confirms. No gateway, API, webhook, or automatic verification.

### FR-09 Receipt and print

One fixed 58mm layout includes cafe branding, order number, timestamp, cashier, table, item/size/quantity/price, total, payment method, and thank-you message. Payment remains paid when print fails. Failed jobs can be retried without creating another payment.

### FR-10 Reports

Owner filters today, last 7 days, this month, or custom range. Reports show all paid and expired transactions. Revenue counts paid only. Detail includes order, time, cashier, table, item, size, quantity, total, method, and status. Export CSV and clean PDF.

### FR-11 Audit

Log payment confirmation, stock adjustment, price update, availability update, table status update, and report export with actor, action, entity, before/after values where relevant, and timestamp.

## 5. Acceptance criteria

- Owner creates Regular/Large menu prices and stock.
- Cashier cannot edit owner data.
- Every order has a table.
- Additional order on occupied table works.
- Insufficient available stock blocks submit.
- Reservation releases after 60 seconds.
- Expired order stays in history and is excluded from revenue.
- Cash change is correct.
- Manual QRIS payment records cashier and amount.
- Payment confirmation is idempotent.
- Paid order consumes stock exactly once.
- Paid order creates one print job exactly once.
- Print failure leaves payment paid and supports retry.
- CSV and PDF contain paid and expired transactions.
- PDF is a report layout, not a dashboard screenshot.
- Owner routes reject cashier.

## 6. Non-functional requirements

- Tablet-first UI with touch targets at least 44px.
- HTTPS in deployment.
- Server-side validation and authorization.
- Money stored as integer minor units; no float arithmetic.
- Atomic payment/stock transition.
- Database indexes for status/date and foreign keys.
- Target cashier page ready within 3 seconds on normal cafe connection.
- Clear loading, empty, validation, expiry, and printer-failed states.
- No offline support.

## 7. Out of scope

Customer ordering, QR menu, gateways, automatic QRIS, kitchen screen, waiter app, offline mode, multi-cafe/branch, custom receipt builder, cancel/refund/reprint, tax/discount/service charge, toppings/modifiers, advanced stock units, suppliers, accounting integration.

## 8. Traceability

Requirements trace to tickets in `tickets/MVP.md`. Any ticket violating this document needs explicit scope review before work.
