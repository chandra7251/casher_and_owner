# Stitch Unified Design Review

Source: `C:\Users\Lenovo\Downloads\stitch_unified_document_design_engine.zip`

Verdict: FAIL. Visual direction is cleaner, but this export violates multiple locked requirements.

## Passed

- Screen set covers login, workspace, table selection, payment, payment success, table monitor, expired history, owner dashboard, menu, reports, and settings.
- Cash and QRIS manual tabs exist.
- 60-second timer appears.
- Occupied-table confirmation exists.
- Expired-history screen exists.
- Fixed 58mm receipt preview exists.
- Regular/Large appears on some products.

## Blockers

### Workspace

Contains forbidden or incorrect content:

```text
Pajak (10%)
Catatan
Americano (Hot/Ice)
BATAL
SIMPAN
```

Required:

- Remove tax.
- Remove notes/modifiers.
- Every product has exactly Regular and Large.
- Remove cancel and save-unpaid actions.
- Use `Proses Pembayaran`, not generic `BAYAR`.
- Keep only quantity, size, remove, and payment actions.

Current workspace fixture also has only one size for Matcha and Caramel Macchiato. Every product needs both sizes.

### Payment

Contains forbidden action:

```text
Batal
```

Remove it. Payment screen must have only back/new-transaction navigation appropriate to state, not cancel transaction.

QRIS instruction says:

```text
Validasi pembayaran di aplikasi EDC atau mutasi
```

This is unnecessary and conflicts with the agreed simple manual flow. Use:

```text
Customer scan QRIS fisik di kasir.
Kasir validasi uang masuk secara manual.
```

Payment fixture must show amount input and correct cash change. Only methods are `Tunai` and `QRIS manual`.

### Payment success

Contains:

```text
Cetak Struk
Cetak Struk Ulang
```

Both violate the rule. Successful print state must show status only:

```text
Pembayaran Lunas
Status Struk: Tercetak
Pesanan Baru
```

Only failed print state may show `Coba cetak lagi`.

### Owner dashboard

Contains unsupported stock units:

```text
500g
2 pcs
```

Use integer counts only, with no unit suffix. Also label expired orders as `Pesanan Expired`, not `Pesanan Batal/Expired`.

### Owner menu

Violations:

- Column says `Stok (Cup)`; remove unit wording.
- `Latte Macchiato` Large is `-`; every product must have Regular and Large.
- Menu includes `Staf` navigation, but waiter has no account and employee management is out of scope.
- `Export CSV` on menu is not required; CSV/PDF exports belong to reports.

### Reports

Contains forbidden records/features:

```text
Takeaway
Kartu Debit
QRIS (not QRIS manual)
```

Required:

- Only `Tunai` and `QRIS manual`.
- No takeaway.
- No card.
- Include paid and expired transactions.
- Columns must include order ID, time, cashier, table, item/size, total, method, status.
- Revenue counts paid only.

### Expired history

Contains forbidden content:

```text
SELESAI
DIBATALKAN
takeout_dining Bungkus
```

Required:

- Only `Expired` status/history.
- No canceled/completed sections.
- No takeaway.
- Every expired order still has its required table.
- Show `Expired = tidak masuk pendapatan`.

### Cafe settings / receipt

Receipt preview is missing or unclear for:

- order number;
- item size;
- explicit unit price and line total;
- payment method label consistency;
- configurable thank-you message as the final footer.

Remove `Powered by Kedai Senja POS`; not a required receipt field. Keep fixed 58mm layout with logo, name, address, order number, date/time, cashier, table, item, size, quantity, unit price, line total, total, payment method, and thank-you message.

Settings must not add tax, email, custom layout, or unrelated fields.

### Data consistency

The export uses inconsistent product samples and unsupported states across screens. Source of truth must be:

```text
Cafe: Kedai Senja
Methods: Tunai, QRIS manual
Sizes: Regular, Large
Table states: Tersedia, Terisi
Order states: draft, awaiting_payment, paid, expired
Stock: integer count only
```

## Verdict

Do not implement from this export. It is not the best candidate despite its cleaner visual presentation. It needs one more strict correction pass.

Review completed from extracted HTML text, not only screenshots, so hidden/static sample labels were included in the verdict.

No production source code started.

## Required next step

Use `docs/STITCH-UNIFIED-CORRECTION-PROMPT.md`, then export a clean ZIP with only corrected screens.
