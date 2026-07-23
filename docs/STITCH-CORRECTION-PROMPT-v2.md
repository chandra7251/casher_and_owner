# Stitch AI — Strict Correction Prompt v2

You must revise the existing Cafe Cashier POS screens. Do not create a new product direction. Keep the existing visual system: warm espresso brown, cream surfaces, terracotta actions, Playfair Display for branding/headings, Inter for operational UI, tablet-first layout, minimum 44px touch targets.

This is a strict correction pass. Treat every rule below as non-negotiable. Do not preserve conflicting sample data, labels, buttons, payment methods, or features from previous screens.

## Product rules — source of truth

- One cafe: Kedai Senja.
- One location: no branch selector and no fake branch feature.
- One Android tablet used by cashier.
- Waiter has no account, no tablet, and no screen.
- Customer orders verbally from a physical menu.
- Every order requires a table.
- Table states are exactly two: `Tersedia` and `Terisi`.
- An occupied table may receive an additional order.
- Menu sizes are exactly two: `Regular` and `Large`.
- Every product must show both Regular and Large variants, including food such as Croffle. Do not create products without both sizes.
- Stock is an integer item count only. Never show grams, kilograms, liters, milliliters, or `pcs`. Examples: `Sisa: 15`, `Minimum: 5`.
- Owner can edit price, stock, low-stock threshold, and availability for each size.
- Cashier cannot edit owner data.
- Payment methods are exactly `Tunai` and `QRIS manual`.
- No debit card, credit card, e-wallet, payment gateway, dynamic QRIS, webhook, or automatic payment verification.
- QRIS is a physical static QRIS placed at the cashier. Cashier validates incoming money manually.
- Payment countdown starts at exactly `01:00`.
- After timeout, order status is `Expired`, payment controls are disabled, and the order remains in history.
- Stock is reserved while waiting for payment, consumed after payment, and released after expiry.
- Order states are exactly `draft`, `awaiting_payment`, `paid`, and `expired`.
- Paid means order is finished in the system. Do not show `preparing`, `completed`, `served`, or kitchen workflow.
- No cancel, refund, save unpaid order, takeaway, tax, discount, service charge, toppings, modifiers, notes, customer name, split bill, or custom receipt builder.
- No offline mode. Do not show offline fallback or sync UI.
- Receipt layout is one fixed thermal 58mm layout.
- Reports show every paid and expired transaction in the selected date range. Revenue counts paid only.
- CSV and PDF exports must represent the same complete filtered dataset.
- Print success has no reprint button. Print failure has a retry button only.

## Mandatory sample data rules

Use one consistent cafe identity everywhere:

```text
Cafe: Kedai Senja
Address: Jl. Melati No. 12
```

Use only these payment labels:

```text
Tunai
QRIS manual
```

Use only these statuses:

```text
Lunas
Expired
```

Use only these size labels:

```text
Regular
Large
```

Use Indonesian Rupiah formatting everywhere:

```text
Rp 22.000
```

Never use `R 22k`.

## Correct data fixtures — use these exact values in visual examples

### Order workspace fixture

```text
Meja: Meja 04

2 × Kopi Susu Senja — Large — Rp 22.000/unit — Rp 44.000
1 × Croffle Original — Regular — Rp 15.000/unit — Rp 15.000

Subtotal: Rp 59.000
Total: Rp 59.000
```

Important: `Rp 44.000` is the line total for quantity 2, not the unit price. Display both unit price and line total clearly.

The workspace must contain:

- Explicit Regular/Large selector on each product card or an item-size dialog.
- Cart size label for every item.
- Unit price and line total.
- Quantity plus, minus, remove, and edit controls.
- Selected table.
- Button exactly `Proses Pembayaran`.
- Do not include `Simpan Pesanan`.

### Cash payment fixture

```text
Total: Rp 59.000
Jumlah diterima: Rp 100.000
Kembalian: Rp 41.000
```

The displayed calculation must be mathematically correct.

### QRIS manual fixture

Create a visible QRIS manual tab state. It must show:

```text
Total yang harus dibayar: Rp 59.000
Instruksi: Customer scan QRIS fisik di kasir.
Instruksi: Kasir validasi uang masuk secara manual.
Jumlah diterima: Rp 59.000
Checkbox/confirmation: Pembayaran sudah divalidasi kasir
Button: Konfirmasi Pembayaran
```

Do not show a generated QR code, transaction API, webhook, payment gateway, or automatic verification.

### Expired fixture

Create a separate expired state:

```text
Batas waktu habis
Order #ORD-1024
Status: Expired
Reservasi stok dilepas
```

Disable amount input and `Konfirmasi Pembayaran`. Provide only a clear action to return to a new transaction/order workspace. Do not provide cancel, refund, or reactivation.

### Owner menu fixture

Every product must show both sizes and integer stock counts:

```text
Matcha Premium
Regular — Rp 25.000 — Sisa: 5 — Minimum: 10
Large — Rp 32.000 — Sisa: 8 — Minimum: 10
Status: Tersedia

Croffle Original
Regular — Rp 15.000 — Sisa: 15 — Minimum: 5
Large — Rp 20.000 — Sisa: 10 — Minimum: 5
Status: Tersedia
```

Do not show `g`, `kg`, `ml`, `liter`, or `pcs`.

### Reports fixture

The reports table must include paid and expired rows, with no forbidden methods:

```text
#ORD-1024 | 14:30 | Budi | Meja 04 | Matcha Large x1 | Rp 32.000 | QRIS manual | Lunas
#ORD-1023 | 14:25 | Budi | Meja 02 | Croffle Regular x1 | Rp 15.000 | Tunai | Lunas
#ORD-1022 | 14:20 | Budi | Meja 07 | Kopi Susu Regular x1 | Rp 18.000 | — | Expired
```

Show a visible legend or filter state:

```text
Lunas = masuk pendapatan
Expired = tidak masuk pendapatan
```

Never show:

```text
Kartu Debit
Kartu Kredit
Batal
Canceled
Takeaway
```

Keep `Ekspor CSV` and `Ekspor PDF`. Make the PDF preview a clean report document with all transaction rows, not a dashboard screenshot.

### Fixed receipt fixture

Receipt preview must include:

```text
KEDAI SENJA
Jl. Melati No. 12
No. Pesanan: #ORD-1024
Tanggal dan waktu
Kasir: Budi
Meja: 04

Matcha Large  1 x Rp 32.000  Rp 32.000

TOTAL         Rp 32.000
Metode: QRIS manual
Terima kasih
```

Do not show tax, discount, service charge, or a reprint button.

## Required screens to revise or add

1. Cashier login.
2. Cashier order workspace.
3. Table selection.
4. Payment — cash state.
5. Payment — QRIS manual state.
6. Payment — expired state.
7. Payment success — print success state without reprint.
8. Payment success — print failed state with retry only.
9. Cashier table monitor with only available/occupied and waiter confirmation modal.
10. Owner dashboard with paid revenue, paid orders, expired orders, and integer low-stock alerts.
11. Owner menu with Regular/Large for every product and integer stock.
12. Owner reports with paid and expired rows and only valid payment methods.
13. Cafe settings with fixed 58mm receipt preview and order number.

## Final self-check before export

Before generating the revised ZIP, inspect every screen and reject the result if any of these strings or concepts appear:

```text
Pajak
Tax
Kartu Debit
Kartu Kredit
Batal
Canceled
Cancel
Takeaway
Simpan Pesanan
Cetak Ulang
Reprint
Es
Less Sugar
Extra Maple
Topping
Modifier
Garam
Gula
Gram
kg
g
pcs
Preparing
Completed
Kitchen
Waiter app
Offline sync
Webhook
Payment gateway
```

Also reject the result if:

- Any visible total is mathematically inconsistent.
- Any line item lacks Regular/Large.
- Any product lacks both sizes.
- Any stock uses units instead of integer counts.
- QRIS manual state is not visibly designed.
- Expired state is not visibly designed.
- Successful print state shows reprint.
- Report sample includes card, canceled, or takeaway records.
- Receipt lacks order number.
- Cafe identity changes between screens.

Export every screen as HTML and PNG. Include a short DESIGN.md documenting the final tokens and stating that all rules above were applied.

Do not generate production backend code. This task is UI/UX correction only.
