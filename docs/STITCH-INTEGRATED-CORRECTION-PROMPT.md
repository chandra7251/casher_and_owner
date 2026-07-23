# Stitch Integrated Design — Final Correction Prompt

Revise the current integrated Cafe Cashier POS design in place. Keep its visual system and screen quality. Do not redesign into a generic SaaS product. Apply every rule below exactly.

## Non-negotiable product scope

- One cafe: Kedai Senja.
- One location.
- One Android tablet used by cashier.
- Waiter has no account, no tablet, and no screen.
- Customers order verbally from a physical menu.
- Every order requires a table.
- Table states: exactly `Tersedia` and `Terisi`.
- Multiple orders per occupied table are allowed.
- Menu sizes: exactly `Regular` and `Large`.
- Every product, including food, has both Regular and Large.
- Stock: integer count only. Valid examples: `Sisa: 15`, `Minimum: 5`.
- Payment methods: exactly `Tunai` and `QRIS manual`.
- QRIS is a static physical QRIS at the cashier. Cashier validates payment manually.
- Payment timer starts at exactly 01:00.
- Expiry releases stock reservation and keeps order in history.
- Paid means order finished in this system. No preparing/completed/kitchen workflow.
- No offline mode.
- Receipt fixed at 58mm.

## Delete these features and strings everywhere

Remove all visible UI, sample data, settings, and navigation relating to:

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
Cancel
Refund
Reprint
Cetak Ulang
Cetak Struk after successful print
Preparing
Disiapkan
Bar
Kitchen
Promo
Campaign
Buy 2 Get 1
Discount
Auto-cancel
Takeaway
Email Bisnis
Indoor
Outdoor
Area filter
Cheese
Ice
Less Sugar
Topping
Modifier
Notes
Gram
g
kg
L
ml
oz
pcs
```

Do not replace `Pajak` with another tax/fee label. Remove tax functionality entirely.

## Screen-by-screen corrections

### 1. Workspace kasir

Must show:

```text
Meja 04
Kopi Susu Senja
  Regular Rp 18.000
  Large Rp 22.000
Croffle Original
  Regular Rp 15.000
  Large Rp 19.000
```

Cart fixture:

```text
2 × Kopi Susu Senja — Large — Rp 22.000/unit — Rp 44.000
1 × Croffle Original — Regular — Rp 15.000/unit — Rp 15.000
Subtotal Rp 59.000
Total Rp 59.000
```

Required controls:

- Explicit size choice.
- Quantity plus/minus.
- Remove item.
- Selected table.
- `Proses Pembayaran`.

Forbidden control:

```text
Simpan Pesanan
```

### 2. Pembayaran Tunai

Use this exact fixture:

```text
Total Rp 59.000
Jumlah diterima Rp 100.000
Kembalian Rp 41.000
```

Show input value, quick amounts, keypad, and correct math. All item variants must be Regular or Large.

### 3. Pembayaran QRIS manual

Show a complete visible state:

```text
QRIS manual
Customer scan QRIS fisik di kasir.
Kasir validasi uang masuk secara manual.
Total yang harus dibayar Rp 59.000
Jumlah diterima Rp 59.000
Pembayaran sudah divalidasi kasir
Konfirmasi Pembayaran
```

Do not generate a QR code. Do not mention API or automatic verification.

### 4. Pembayaran expired

Show:

```text
Batas waktu habis
Status Pesanan: Expired
Reservasi stok dilepas
Kontrol pembayaran terkunci
Kembali ke Transaksi Baru
```

No payment confirmation, no reactivation, no cancel, no refund.

### 5. Payment success

Successful print state must show:

```text
Pembayaran Lunas
Status Struk: Tercetak
Pesanan Baru
```

Do not show `Cetak Struk`, `Cetak Ulang`, or any second print action.

Create a separate failed-print state:

```text
Pembayaran Lunas
Status Struk: Gagal mencetak
Coba cetak lagi
```

Retry is allowed only in this failed state.

### 6. Owner dashboard

Keep only:

- Paid revenue.
- Paid order count.
- Expired order count.
- Cash revenue.
- QRIS manual revenue.
- Low-stock alerts using integer counts.
- Recent transactions.

Remove promo/campaign cards and all measurement units.

### 7. Owner menu and stock

Every product must display both variants:

```text
Matcha Premium
Regular — Rp 25.000 — Sisa: 5 — Minimum: 10
Large — Rp 32.000 — Sisa: 8 — Minimum: 10

Croffle Original
Regular — Rp 15.000 — Sisa: 15 — Minimum: 5
Large — Rp 19.000 — Sisa: 10 — Minimum: 5
```

Owner edit form must expose for each size:

```text
Harga
Stok saat ini
Minimum stok
Tersedia/tidak tersedia
```

Do not show unit suffixes.

### 8. Reports

Show all paid and expired transactions in selected range. Include these columns:

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

Use only:

```text
Tunai
QRIS manual
Lunas
Expired
```

Display explicit legend:

```text
Lunas = masuk pendapatan
Expired = tidak masuk pendapatan
```

Keep `Ekspor CSV` and `Ekspor PDF`. Design PDF as a clean report document containing all filtered rows, not a dashboard screenshot.

### 9. Cafe settings

Keep only:

```text
Logo
Nama cafe
Alamat
Nomor telepon
Pesan terima kasih
Preview struk fixed 58mm
```

Receipt preview must contain:

```text
KEDAI SENJA
Jl. Melati No. 12
No. Pesanan: #ORD-1024
Tanggal dan waktu
Kasir: Budi
Meja: 04
Matcha Large 1 × Rp 32.000 Rp 32.000
TOTAL Rp 32.000
Metode: QRIS manual
Terima kasih
```

No tax, fees, email, or custom layout builder.

### 10. Table selection

Keep table number and available/occupied state only. Remove `Indoor`, `Outdoor`, area filters, and reserved state. Allow:

```text
Pesanan Baru
Tambah Pesanan ke meja Terisi
```

## Consistency requirements

- Use `Kedai Senja` everywhere. Never use another cafe name.
- Use Indonesian UI labels.
- Use `Rp 22.000`, never `R 22k`.
- No screen may contain contradictory sample data.
- Do not export old and corrected versions together. Export only final corrected screens.
- Export each screen as HTML and PNG.
- Include DESIGN.md stating that this exact scope was applied.

## Final rejection test

Before export, search every generated screen and reject your own output if any forbidden string from this prompt appears. Also reject if:

- Any total is mathematically wrong.
- Any product lacks Regular and Large.
- Any stock contains a unit suffix.
- Any successful print state offers printing again.
- Reports contain card, tax, canceled, takeaway, promo, or unsupported records.
- Receipt lacks order number.
- QRIS manual or expired state is missing.

UI/UX only. Do not generate production backend code.
