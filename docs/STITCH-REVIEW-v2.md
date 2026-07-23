# Stitch Revision Review v2

Source:
`C:\Users\Lenovo\Downloads\stitch_document_content_analyzer(1).zip`

Verdict: FAIL — do not implement from this revision yet.

## What improved

- Tax removed from fixed workspace/payment/receipt variants.
- Most labels use `Kedai Senja`.
- Indonesian Rupiah format improved.
- Table release confirmation exists.
- Expired appears on owner dashboard/reports.
- Payment countdown shows 60-second starting state in fixed payment.
- Fixed receipt preview no longer shows tax.
- Fixed table states are closer to available/occupied.

## Remaining blockers

### 1. Order workspace math and size

`order_workspace_fixed` still shows:

```text
2 × Kopi Susu Senja — Rp 44.000
1 × Croffle Original — Rp 15.000
Subtotal Rp 59.000
Total Rp 59.000
```

The visual does not show Regular/Large on items. `Rp 44.000` is ambiguous between unit and line price. Add explicit size, unit price, and line total. Use a fixture where the total is unambiguous.

`Simpan Pesanan` remains, but saving an unpaid order is not in scope. Remove it.

### 2. Payment remains contaminated

`payment_fixed` still contains:

```text
Es, Less Sugar
Extra Maple Syrup
```

These are modifiers and out of scope. Remove them.

Visible amount/cash math is inconsistent:

```text
Received Rp 100.000
Total Rp 80.000
Shown change Rp 12.000
Expected change Rp 20.000
```

Fix fixture data and display.

QRIS manual tab is present but the provided static image does not prove the manual validation state. Add a visible QRIS state with amount input, manual validation instruction, and confirmation checkbox/button.

Add an explicit expired state that disables payment controls.

### 3. Reports still contain forbidden payment

`owner_reports_fixed` still contains:

```text
Kartu Debit
```

Only `Tunai` and `QRIS manual` are valid. The report must show paid and expired rows, and visibly distinguish revenue from expired. Use `QRIS manual`, not ambiguous `QRIS`.

### 4. Menu stock violates requirement

`owner_menu_fixed` still shows:

```text
250g
1.2kg
15 pcs
```

Stock must be integer item counts only. Every product must show both Regular and Large. Croffle currently lacks Large.

Use values like:

```text
Sisa: 15
Minimum: 5
```

### 5. Payment success offers forbidden reprint

`payment_success_fixed` still shows:

```text
Cetak Ulang Struk
```

Remove it. Successful print state has status only. Failed print state may have `Coba cetak lagi`.

### 6. Receipt lacks order number

`cafe_settings_fixed` receipt preview is close, but lacks a visible order number. Add:

```text
No. Pesanan: #ORD-1024
```

### 7. Inconsistent duplicate screens

The ZIP contains original and `_fixed` versions. This creates ambiguity about source of truth. Export only corrected screens, or label old screens clearly as rejected references.

### 8. Broken original settings screenshot

`cafe_settings/screen.png` still contains `FIFE Image failed to fetch`. Regenerate it or exclude rejected originals.

## Strong correction prompt

Use:

```text
C:\casher_and_owner\docs\STITCH-CORRECTION-PROMPT-v2.md
```

It contains strict source-of-truth rules, exact fixtures, forbidden strings, required screens, and final rejection checks.

## Implementation status

No production source code started. Keep implementation blocked until the next Stitch export passes all blockers above.
