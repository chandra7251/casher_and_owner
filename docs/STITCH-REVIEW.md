# Stitch UI/UX Review

Date: 2026-07-22
Source: `C:\Users\Lenovo\Downloads\stitch_document_content_analyzer.zip`

## 1. Deliverables found

- `cashier_login`
- `order_workspace`
- `table_selection`
- `payment`
- `payment_success`
- `cashier_table_monitor`
- `owner_dashboard`
- `owner_menu`
- `owner_reports`
- `cafe_settings`
- `warm_operational_heritage/DESIGN.md`

Each screen has HTML and PNG output. `cafe_settings/screen.png` is broken and contains `FIFE Image failed to fetch`.

## 2. Design system accepted

The visual direction is usable for implementation:

- Espresso brown primary.
- Cream surface.
- Terracotta action accent.
- Muted status colors.
- Playfair Display for brand/headings.
- Inter for operational UI/data.
- 44px minimum touch target.
- Tablet-first layout.
- Fixed right cart/sidebar on cashier workspace.
- Fixed 58mm receipt direction is compatible with product requirements.

## 3. Required corrections before implementation

### Product and business rules

1. Remove tax from every screen. Tax is out of scope.
2. Remove card/debit payment. Valid methods are only `cash` and `QRIS manual`.
3. Replace `Batal`/cancel language with `Expired`/`Kedaluwarsa`. Cancel is out of scope.
4. Remove `Takeaway` examples. Every order requires a table.
5. Remove `Dipesan`/reserved table state. Table states are only `available` and `occupied`.
6. Remove menu modifiers and notes such as ice, sugar, syrup, and toppings. Only Regular and Large are supported.
7. Fix all totals. The order workspace currently displays a subtotal inconsistent with item quantity and line totals.
8. Use one currency format: `Rp 22.000`, not `R 22k`.
9. Add explicit Regular/Large selection before adding an item or in an edit dialog.
10. Add quantity increment, decrement, remove, and edit controls in cart.
11. Payment screen must show a 60-second countdown starting at `01:00`, plus a clear expired state.
12. QRIS tab must show manual cashier validation and amount input. No QR generation or gateway language.
13. Reports must visibly include both paid and expired transactions. Revenue must count paid only.
14. Report examples must use `Kedai Senja` consistently. Current output mixes `Kedai Senja` and `Kopi & Cerita`.
15. Owner menu must expose price, stock, low-stock threshold, and availability separately for Regular and Large.
16. Table release must include confirmation text that waiter physically confirmed the table is free.
17. Remove employee management from navigation; only owner and cashier are in MVP, and waiter has no account.
18. Remove offline/connection behavior from product claims. Cloud-only is the requirement.

### Technical implementation notes

- Stitch HTML is prototype output, not Laravel/Inertia code.
- It uses Tailwind CDN and Google Fonts; production must use the project build pipeline and decide whether fonts are self-hosted or externally loaded.
- It contains static sample values and cannot be used as business logic.
- It does not prove Android USB silent printing. Keep `PRINT-001` as a technical spike.
- `cafe_settings/screen.png` needs regeneration or manual review before implementation.

## 4. Screen mapping

| Stitch screen | Implementation page | Main correction |
|---|---|---|
| cashier_login | cashier login | Keep; use one cafe name consistently |
| order_workspace | cashier order entry | Remove tax; fix totals; add size/quantity controls; table required |
| table_selection | table selection | Only available/occupied; support additional order |
| payment | payment confirmation | Cash/manual QRIS only; 60-second expiry; manual amount |
| payment_success | payment result | Show print queued/printed/failed; no fake payment methods |
| cashier_table_monitor | cashier table monitor | Only available/occupied; waiter confirmation dialog |
| owner_dashboard | owner dashboard | Remove unsupported KPIs/features; prioritize low stock |
| owner_menu | owner catalog | Regular/Large stock, price, threshold, availability |
| owner_reports | owner reports | Paid + expired; remove canceled/card/takeaway examples |
| cafe_settings | owner settings | Regenerate broken screenshot; fixed receipt preview |

## 5. Decision

Keep the visual system and screen structure. Do not copy prototype HTML directly into production. Apply corrections above during React/Inertia implementation.

Implementation remains blocked until the Stitch corrections are reflected in the design or explicitly accepted as implementation adjustments.

Next technical work: run `PRINT-001` spike, then start `SETUP-001` only after design correction is accepted.
