# Stitch Unified Design — Strict Final Correction

Revise the current unified design. Keep its visual quality and information architecture, but remove every feature and sample label outside the approved Cafe Cashier POS scope. This is a strict correction pass, not a brainstorming request.

## Approved scope

```text
One cafe: Kedai Senja
One location
One Android tablet for cashier
Waiter has no account/device/screen
Every order requires a table
Table states: Tersedia, Terisi
Multiple orders per occupied table allowed
Menu sizes: exactly Regular, Large
Every product has both sizes
Payment: Tunai, QRIS manual
Payment timer: 60 seconds
Order states: draft, awaiting_payment, paid, expired
Stock reservation during awaiting_payment
Stock consumed on paid
Stock released on expired
Cloud-only, no offline UI
Fixed thermal receipt 58mm
Reports: all paid and expired transactions
```

## Delete everywhere

Delete visible UI, sample data, settings, navigation, and copy related to:

```text
Pajak
Tax
Biaya
Kartu
Debit
Kredit
E-wallet
Payment gateway
Webhook
Batal
BATAL
Cancel
Refund
Reprint
Cetak Struk
Cetak Struk Ulang
Takeaway
Bungkus
Catatan
Notes
Modifier
Hot/Ice
Ice
Cheese
Topping
Preparing
Disiapkan
Bar
Kitchen
Campaign
Promo
Discount
Staf
Employee management
Indoor
Outdoor
Area filter
Cup
pcs
Gram
g
kg
L
ml
oz
Auto-cancel
SELESAI
DIBATALKAN
```

Never replace tax with another fee setting. Remove tax/fee functionality entirely.

## Workspace Kasir

Use exactly this fixture:

```text
Meja 05

2 × Kopi Susu Senja — Large — Rp 22.000/unit — Rp 44.000
1 × Matcha Latte — Regular — Rp 28.000/unit — Rp 28.000

Subtotal: Rp 72.000
Total: Rp 72.000
```

Required controls:

- Explicit Regular/Large choice for every product.
- Every product card has both Regular and Large.
- Quantity plus/minus.
- Remove item.
- Table visible and mandatory.
- Button exactly `Proses Pembayaran`.

Remove:

```text
Simpan
Simpan Pesanan
Batal
BAYAR
Catatan
Pajak
```

Use Indonesian Rupiah format consistently: `Rp 22.000`.

## Payment

Methods exactly:

```text
Tunai
QRIS manual
```

Cash state must show:

```text
Total: Rp 72.000
Jumlah diterima: Rp 100.000
Kembalian: Rp 28.000
```

QRIS manual state must show:

```text
Customer scan QRIS fisik di kasir.
Kasir validasi uang masuk secara manual.
Total yang harus dibayar: Rp 72.000
Jumlah diterima: Rp 72.000
Pembayaran sudah divalidasi kasir
Konfirmasi Pembayaran
```

Do not show an EDC, API, gateway, generated QR, webhook, or automatic verification.

Remove payment cancel action. The 60-second timer must be visible and start at `01:00`.

Expired state must show:

```text
Batas waktu habis
Status Pesanan: Expired
Reservasi stok dilepas
Kontrol pembayaran terkunci
Kembali ke Transaksi Baru
```

No reactivation, cancel, or refund.

## Payment success and printing

Success state:

```text
Pembayaran Lunas
Status Struk: Tercetak
Pesanan Baru
```

Do not show any print action after success. No `Cetak Struk`, no `Cetak Struk Ulang`, no `Reprint`.

Create separate failed-print state only:

```text
Pembayaran Lunas
Status Struk: Gagal mencetak
Coba cetak lagi
```

Retry exists only in failed state.

## Owner dashboard

Keep only:

```text
Pendapatan Lunas
Pesanan Lunas
Pesanan Expired
Pendapatan Tunai
Pendapatan QRIS manual
Peringatan Stok Rendah
Transaksi Terbaru
```

Stock examples must be integer counts without units:

```text
Matcha Regular — Sisa: 5
Kopi Susu Large — Sisa: 12
```

No promo, campaign, tax, ingredients, packaging units, or auto-cancel.

## Owner menu

Every product must have both variants:

```text
Kopi Senja Signature
Regular — Rp 25.000 — Sisa: 45 — Minimum: 10
Large — Rp 30.000 — Sisa: 40 — Minimum: 10

Latte Macchiato
Regular — Rp 28.000 — Sisa: 5 — Minimum: 10
Large — Rp 33.000 — Sisa: 4 — Minimum: 10
```

Use table heading `Stok`, not `Stok (Cup)`.

Owner edit form includes for each size:

```text
Harga
Stok saat ini
Minimum stok
Tersedia
```

Remove Staf navigation and menu CSV export. Report exports belong on reports screen.

## Table screens

Use only:

```text
Tersedia
Terisi
```

Occupied table may show active order count and total. Release action must show:

```text
Waiter memastikan meja ini sudah kosong?
```

Remove area filters, reserved state, takeaway, and employee/waiter UI.

## Expired history

Show only expired orders. Use:

```text
Riwayat Expired
Expired
Tidak masuk pendapatan
```

Every row must include:

```text
Order ID
Meja
Waktu
Total
Expired
```

Remove `Selesai`, `Dibatalkan`, `Takeaway`, and `Bungkus` sections/data.

## Reports

Show all paid and expired transactions for selected date range.

Required columns:

```text
ID Pesanan
Waktu
Kasir
Meja
Item dan ukuran
Total
Metode pembayaran
Status
```

Valid methods:

```text
Tunai
QRIS manual
```

Valid statuses:

```text
Lunas
Expired
```

Legend:

```text
Lunas = masuk pendapatan
Expired = tidak masuk pendapatan
```

Keep `Ekspor CSV` and `Ekspor PDF`. PDF must look like a clean printable report with all filtered rows, not a dashboard screenshot.

## Cafe settings and receipt

Settings only:

```text
Logo
Nama cafe
Alamat
Nomor telepon
Pesan terima kasih
Preview struk 58mm
```

Receipt preview must include:

```text
KEDAI SENJA
Jl. Melati No. 12
No. Pesanan: #ORD-1024
Tanggal dan waktu
Kasir: Budi
Meja: 04
Matcha Large — 1 × Rp 32.000 — Rp 32.000
TOTAL: Rp 32.000
Metode: QRIS manual
Terima kasih
```

Remove `Powered by Kedai Senja POS` unless it is explicitly part of the configured thank-you footer. No tax, fee, email, or custom layout editor.

## Final automated rejection check

Before exporting, search all HTML and visible design copy. Reject and regenerate if any of these appear:

```text
Pajak
Tax
Biaya
Kartu
Debit
Kredit
Batal
Cancel
Refund
Reprint
Cetak Struk
Takeaway
Bungkus
Catatan
Notes
Hot/Ice
Ice
Cheese
Topping
Preparing
Disiapkan
Bar
Kitchen
Campaign
Promo
Discount
Staf
Employee
Indoor
Outdoor
Cup
pcs
gram
g
kg
L
ml
oz
Selesai
Dibatalkan
Auto-cancel
```

Also reject if:

- Any product lacks Regular or Large.
- Any stock has a unit suffix.
- Any total is mathematically wrong.
- Any successful print state has a print action.
- Any report row uses card/takeaway/unsupported status.
- Any order has no table.
- Receipt lacks order number.
- QRIS manual or expired screen is missing.

Export only final corrected screens as HTML and PNG. Include DESIGN.md. UI/UX only; no backend code.
