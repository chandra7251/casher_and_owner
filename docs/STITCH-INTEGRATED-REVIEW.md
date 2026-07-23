# Stitch Integrated Design Review

Source: `C:\Users\Lenovo\Downloads\stitch_integrated_design_adaptation.zip`

Verdict: FAIL. Stronger than previous export, but still contains scope-breaking UI and data.

## Passed improvements

- Dedicated cash, manual QRIS, and expired screens exist.
- Regular/Large appears in workspace and owner inventory.
- Stock counts are integer-only on owner menu.
- Table selection uses available/occupied states.
- Report includes paid and expired labels.
- Fixed receipt preview includes order number and 58mm direction.
- Manual QRIS instruction and cashier validation checkbox exist.
- Expired screen disables controls and releases reservation.
- Payment success has a new-order action and print status area.

## Blockers

### 1. Tax remains in workspace

`workspace_kasir` contains:

```text
Pajak (10%) Termasuk
```

Tax is out of scope. Remove the row and the word `Pajak` entirely. Total must equal subtotal with no tax setting.

`pengaturan_cafe` also contains:

```text
Tampilkan Pajak & Biaya
```

Remove this setting. Cafe settings must not expose tax or fees.

### 2. Payment success still contains tax and unsupported print flow

`pembayaran_berhasil_cetak` contains:

```text
Pajak (10%)
Cetak Struk
Pesanan sedang disiapkan di bar
```

Problems:

- Tax is forbidden.
- Payment-success screen must not offer a second print action after the print job is already successful. Show status only.
- `Pesanan sedang disiapkan di bar` implies kitchen/preparing workflow, which is out of scope.
- Fixture uses `Croissant Almond` and `Matcha Latte Ice`; `Ice` is a modifier-style concept and not in the requirements.

Keep only payment success, order paid, receipt status `Tercetak`, and `Pesanan Baru`. If printer fails, use a separate failed state with `Coba cetak lagi` only.

### 3. Cash payment has unsupported product size

`pembayaran_tunai` contains:

```text
Croissant — Cheese
```

Allowed sizes are exactly:

```text
Regular
Large
```

Replace `Cheese` with Regular or Large. Do not create modifier/variant names outside those two sizes.

The cash fixture is mathematically correct (`Rp 100.000 - Rp 59.000 = Rp 41.000`), but the amount input state should visibly show `Rp 100.000`, not an ambiguous empty `Rp` field while the receipt says 100.000.

### 4. Dashboard contains out-of-scope features and units

`dashboard_owner` contains:

```text
Campaign Senja Sore
Promo "Buy 2 Get 1"
Edit Promo
250g
1L
12oz
```

Remove:

- Campaign/promo section.
- Buy 2 Get 1.
- Edit Promo.
- Ingredient/packaging units such as g, L, and oz.

MVP stock is integer count per product size only. Dashboard low-stock examples must use values such as `Sisa: 5`, not ingredient units.

Also `Auto-cancel` is dangerous terminology. Use `Pesanan Expired` and never imply cancel.

### 5. Cafe settings still exposes tax and email

`pengaturan_cafe` includes:

```text
Email Bisnis
Tampilkan Pajak & Biaya
```

Email is not in the agreed cafe settings. Remove it unless requirements are updated. Remove all tax/fee controls. Keep name, address, phone, logo, thank-you message, and fixed 58mm receipt preview.

### 6. Reports are close, but verify exact scope

`laporan_transaksi` is structurally close:

- Paid and expired labels exist.
- Only Tunai and QRIS manual examples appear.
- CSV/PDF actions exist.

Required corrections:

- Use `QRIS manual` everywhere, never bare `QRIS`.
- Include table and cashier columns because every transaction needs operational traceability.
- State clearly that expired does not enter revenue.
- Do not show unsupported methods or statuses in any hidden/sample row.
- All export rows must represent the complete filtered dataset, not only visible page rows.

### 7. Table selection adds unsupported filters

`pilih_meja` contains `Indoor`, `Outdoor`, and area filtering. This was not requested and creates unnecessary table configuration scope. Remove area filters unless requirements are amended. Keep only available/occupied and table number.

### 8. Navigation wording and extra scope

Navigation includes `Operational Mode`, `Kasir`, `Monitor`, `Dashboard`, and other broad product areas. This is not a blocker if only visual, but routes must still map to the approved modules:

```text
Owner: dashboard, menu/stok, laporan, pengaturan cafe
Cashier: order, meja, pembayaran, history
```

No employee management, campaign, kitchen, preparation, or offline module.

### 9. Receipt fixture consistency

`pengaturan_cafe` receipt is close and contains:

```text
No. Pesanan
Meja
Regular/Large-style item
Total
QRIS manual
```

Keep it. Ensure actual receipt renderer uses the same fixed layout and includes logo, name, address, order number, date/time, cashier, table, items, size, quantity, unit price, line total, total, payment method, and thank-you message. No tax or fee lines.

## Acceptance gate

Do not start implementation until a replacement export passes this checklist:

- No visible occurrence of `Pajak`, `Tax`, `Biaya`, or tax controls.
- No `Kartu`, `Debit`, `Kredit`, `E-wallet`, or payment gateway.
- No `Batal`, `Cancel`, `Refund`, `Reprint`, or `Cetak Struk` after successful print.
- No `Preparing`, `Disiapkan`, `Bar`, `Kitchen`, or kitchen queue.
- No promo/campaign/discount.
- No `g`, `kg`, `L`, `ml`, `oz`, or `pcs` stock units.
- No `Cheese`, `Ice`, `Less Sugar`, topping, modifier, or notes as variants.
- No area filters or reserved table state.
- Every menu item has exactly Regular and Large.
- Cash, QRIS manual, and expired screens exist.
- Cash math is correct.
- Expired controls are disabled.
- Paid success shows status only; failed print shows retry only.
- Reports include all paid and expired records, with revenue distinction.
- Cafe identity is `Kedai Senja` everywhere.

## Current implementation status

No production source code started. Keep implementation blocked until corrected design export passes the acceptance gate.

The existing strict prompt remains useful, but this review adds integrated-design-specific blockers.
