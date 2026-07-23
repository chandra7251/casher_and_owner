# Stitch AI UI/UX Prompt

Design a production-focused tablet-first cafe cashier POS web app in Indonesian.

This is not a customer self-ordering app. Customers order verbally from a physical cafe menu. One cashier uses one Android tablet to enter orders, confirm cash or manually validated QRIS payments, and print a fixed 58mm thermal receipt. Waiters do not use the app. Waiters physically observe tables and tell the cashier when a table is free.

Users:
- Owner: manage menu, Regular/Large prices, stock, low-stock thresholds, cafe settings, tables, reports, CSV/PDF exports.
- Cashier: fast order entry, table selection, cash/manual QRIS confirmation, table availability update after waiter confirmation, print retry.
- Waiter: no app access and no screen.

Design direction:
- Warm practical cafe POS, not generic SaaS.
- Espresso brown primary, cream background, charcoal text, terracotta action accent, muted green for available/paid, muted red for expired/low-stock/printer errors.
- Characterful readable display font for cafe identity; clean sans-serif for operational UI and tables.
- 10-inch Android tablet, portrait and landscape.
- Minimum touch target 44px.
- High contrast, visible focus, reduced-motion support.
- No hero sections, glassmorphism, excessive gradients, decorative charts, or heavy animation.

Create screens:

1. Cashier login: logo, cafe name, PIN/password, large touch keypad option, clear error state.
2. Cashier order workspace: header with cafe, cashier, time, connection; category tabs; search; menu cards showing name, image, Regular/Large prices, availability; sticky cart with table, items, size, quantity, subtotal, total; large payment button.
3. Table selection: tiles for available/occupied/disabled; distinct new order and additional order actions; warning for occupied table; table required.
4. Payment: order number, table, items, total, visible 60-second countdown; Cash and QRIS manual tabs; cash amount and change; QRIS instruction: “Customer scan QRIS di kasir. Validasi pembayaran sebelum konfirmasi.”; manual amount input; strong confirmation; expired state.
5. Payment success: “Pembayaran berhasil”; method, amount, change, table, order; print status “Menyiapkan struk”, “Tercetak”, or “Gagal mencetak”; retry print only on failure.
6. Cashier table monitor: table grid, label/icon/color for available and occupied, active order count, mark available after waiter confirmation, dialog “Waiter memastikan meja ini sudah kosong?”.
7. Expired history: order, table, time, total, status; clear “Tidak masuk pendapatan”; no cancel or reprint.
8. Owner dashboard: paid revenue, paid orders, expired orders, cash revenue, QRIS revenue; low-stock alerts above decorative analytics; date range and useful charts only.
9. Owner menu: categories; products; Regular/Large rows; price, stock, threshold, availability; owner editing only.
10. Owner reports: today, last 7 days, this month, custom range; all paid and expired transactions; columns order, time, cashier, table, items, size, quantity, total, method, status; CSV and PDF export; clean printable PDF preview.
11. Cafe settings: logo, name, address, thank-you message, fixed 58mm receipt preview; no custom layout builder.

Interaction rules:
- Cashier flow uses minimal navigation.
- Cart and payment total stay prominent.
- Include loading, empty, validation error, timeout, and printer-failed states.
- Use Indonesian sentence-case labels.
- Avoid technical terms such as API, webhook, reservation, and idempotency in user-facing UI.
- Require confirmation for payment and marking occupied table available.
- Do not design customer, waiter, kitchen, payment gateway, offline, or custom-template screens.

Sample data:
Cafe “Kedai Senja”, “Jl. Melati No. 12”, Matcha, Kopi Susu, Americano, Croffle, Tea, sizes Regular/Large, Meja 01–12, Rupiah.
