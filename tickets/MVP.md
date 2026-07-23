# MVP Tickets

Dependency order: complete in listed order. Each ticket needs acceptance tests before implementation is marked done.

## Foundation

- SETUP-001 Create Laravel 12 app with PHP 8.3+.
- SETUP-002 Configure MySQL, `.env.example`, and local setup docs.
- SETUP-003 Install/configure Inertia, React, TypeScript, and Tailwind.
- SETUP-004 Configure Pest and baseline test command.
- SETUP-005 Add role middleware and base layouts.
- SETUP-006 Add CI running migrations and tests.

## Auth and cafe

- AUTH-001 Owner/cashier login.
- AUTH-002 Owner/cashier route authorization tests.
- CAFE-001 Cafe settings CRUD and logo validation.
- CAFE-002 Fixed receipt branding data.

## Catalog

- MENU-001 Category CRUD.
- MENU-002 Product CRUD.
- MENU-003 Regular/Large size, price, availability fields.
- MENU-004 Owner catalog permission tests.
- MENU-005 Cashier menu/search/category UI.

## Tables

- TABLE-001 Owner table configuration.
- TABLE-002 Cashier table status UI.
- TABLE-003 Mandatory table on order.
- TABLE-004 Additional order on occupied table.
- TABLE-005 Available transition warning with active orders.

## Stock

- STOCK-001 Per-size stock and threshold management.
- STOCK-002 Low-stock query/alert.
- STOCK-003 Stock movement audit.
- STOCK-004 Reservation transaction with row lock.
- STOCK-005 Consume reservation on paid.
- STOCK-006 Release reservation on expiry.
- STOCK-007 Concurrent stock and negative-stock tests.

## Orders and payments

- ORDER-001 Draft cart and immutable item snapshot.
- ORDER-002 Server-side total calculation.
- ORDER-003 Awaiting-payment submission and 60-second expiry.
- ORDER-004 Edit order before payment.
- ORDER-005 Expired order history.
- PAY-001 Cash amount/change validation.
- PAY-002 Manual QRIS amount confirmation.
- PAY-003 Atomic/idempotent payment confirmation.
- PAY-004 Paid order lock and audit.

## Printing

- PRINT-001 Printer integration spike: Android USB OTG/bridge/58mm device.
- PRINT-002 Fixed 58mm receipt renderer.
- PRINT-003 Unique print job on paid order.
- PRINT-004 Bridge authentication and print-job status contract.
- PRINT-005 Failed print retry without duplicate payment.

## Reports

- REPORT-001 Date filters.
- REPORT-002 Revenue and payment breakdown.
- REPORT-003 All transaction detail query.
- REPORT-004 CSV export.
- REPORT-005 Clean PDF export.
- REPORT-006 Owner-only export authorization.

## Quality

- TEST-001 Critical cashier happy path with Playwright.
- TEST-002 Owner permissions.
- TEST-003 Stock reservation/expiry/concurrency.
- TEST-004 Duplicate payment and print-job protection.
- TEST-005 Receipt 58mm snapshot/manual print check.
- TEST-006 CSV/PDF content checks.
- REVIEW-001 Security and authorization review.
- REVIEW-002 Performance/query/index review.
- REVIEW-003 Final acceptance against BRD/PRD.

## Done definition

A ticket is done only when code, focused tests, authorization, validation, and documentation are complete. No ticket may add out-of-scope features without updating BRD, PRD/SRS, Blueprint, and this list.
