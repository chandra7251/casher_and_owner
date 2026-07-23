# BRD — Cafe Cashier POS

Status: Approved discovery baseline
Version: 1.0

## 1. Business summary

Cloud POS for one cafe and one location. One Android tablet is used by cashier. Customers order from a physical menu. Cashier enters orders, validates cash or manual QRIS payment, and prints a fixed 58mm receipt. Waiter does not use the system; waiter observes physical tables and tells cashier when a table is free.

The application must be reusable for another cafe through settings and menu data, without hardcoding one cafe's identity.

## 2. Business problems

- Verbal orders can be entered incorrectly.
- Manual totals and change can be wrong.
- Table availability is hard to track.
- Owner lacks live stock and sales visibility.
- Receipts are inconsistent.
- Expired unpaid orders can hold stock unless reservation expires safely.

## 3. Goals

- Fast, clear cashier order entry on Android tablet.
- Accurate server-calculated totals, payment, change, and stock.
- Visible table availability and multiple orders per table.
- Low-stock alerts for owner.
- Cash and manual QRIS payment recording.
- Automatic 58mm receipt printing through a local Android print bridge.
- Clean CSV and PDF reports containing all transactions.

## 4. Actors

### Owner
Manage categories, menu, Regular/Large prices, stock, low-stock thresholds, tables, cafe settings, reports, CSV/PDF exports, and transaction history.

### Cashier
Create/edit unpaid orders, select tables, accept cash, confirm manual QRIS, change table state after waiter confirmation, and retry failed printing.

### Waiter
No account and no device access. Physically observes tables and tells cashier when a table is free.

## 5. Core flow

```text
Cashier receives verbal order
→ selects table
→ selects menu and size
→ order awaits payment for 60 seconds
→ cashier validates cash or manual QRIS
→ order becomes paid
→ stock is consumed
→ receipt print job is created
→ waiter later reports table free
→ cashier marks table available
```

## 6. Business rules

- One cafe, one location, one cashier tablet.
- Every order requires a table.
- One table may have multiple active orders.
- Sizes are Regular and Large only.
- Owner alone changes prices, stock, and low-stock thresholds.
- Stock is reserved at `awaiting_payment`.
- Default payment timeout is 60 seconds.
- Expired orders remain in history; released reservations become available again.
- Cash requires `amount_paid >= total`; system calculates change.
- QRIS is static and manually validated by cashier. No gateway or webhook.
- Paid order is final in MVP. No cancel, refund, or reprint.
- Payment success is not rolled back when printer fails.
- Revenue includes paid orders only. Reports also show expired orders.
- No offline mode.

## 7. In scope

Authentication and role separation, cafe settings, menu/category management, Regular/Large pricing, stock and low-stock alerts, table states, order creation/editing before payment, stock reservation, cash/manual QRIS, expiry, fixed receipt, print bridge contract, reports, CSV/PDF export, audit log.

## 8. Out of scope

Customer self-order, QR menu, payment gateway, automatic QRIS verification, kitchen display, waiter tablet, multi-cafe, multi-branch, custom receipt builder, offline mode, cancel/refund/reprint, tax, discount, service charge, toppings, modifiers, advanced units, supplier/accounting integration.

## 9. Success criteria

- No negative stock.
- No duplicate payment or print job from repeated confirmation.
- Expired reservations are released.
- Paid receipts contain correct snapshots and totals.
- Owner can export all paid and expired transactions to clean CSV/PDF.
- Cashier cannot access owner routes.
- A cashier can complete an order with minimal navigation on target tablet.

## 10. Constraints and risks

- Cloud app needs internet; outage uses cafe's manual fallback process.
- Android browser cannot reliably silent-print USB without a local print bridge.
- Static QRIS cannot be automatically verified.
- Removing cancel/refund increases correction risk; paid orders are intentionally final in MVP.
- Stock reservation and expiry require atomic transactions and scheduled expiry processing.

## 11. Approval state

Discovery decisions are complete. Requirements and technical design must remain consistent with this document. Implementation starts only after PRD/SRS, Technical Blueprint, conventions, AGENTS.md, and tickets are reviewed.

## 12. Future decisions

- Correction/void workflow with owner audit.
- Printer-specific Android bridge implementation.
- Backup and restore procedure for production deployment.
- Whether future deployments need multiple tablets or branches.
