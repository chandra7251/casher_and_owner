# Project Conventions

## Language

- User-facing UI: Indonesian.
- Code, database identifiers, tests, and technical docs: English.
- Use sentence case in UI labels.

## Naming

- PHP classes/enums: PascalCase.
- PHP methods/variables: camelCase.
- Tables/columns: plural/snake_case.
- Routes: kebab-case.
- React components: PascalCase `.tsx`.
- TypeScript types: PascalCase.
- Tests: behavior-focused names.

## Laravel

- Form Requests validate request input.
- Policies authorize resource access.
- Enums model persisted states.
- Services/actions exist only for multi-step transactional workflows.
- Controllers stay thin.
- Use eager loading deliberately; avoid N+1.
- Use integer money values; never float.
- Use UTC in storage and configured cafe timezone for display.

## Frontend

- Inertia page components live under `resources/js/Pages`.
- Shared UI lives under `resources/js/Components`.
- Feature-specific UI stays near its page.
- Server is source of truth for price, total, stock, status, and permissions.
- Every async mutation has loading, success, and failure states.
- Disable submit while payment mutation is in flight.
- Preserve visible focus and keyboard access.
- Minimum touch target: 44px.

## Data and state

- Order item snapshots are immutable after payment.
- Database transitions enforce valid state changes.
- Stock mutations require transaction and audit record.
- Payment confirmation must be idempotent.
- Print job is unique per paid order.
- No offline state or IndexedDB in MVP.

## Git

Commit format:

```text
feat: add cashier order entry
fix: release expired stock reservation
test: cover duplicate payment confirmation
docs: update report requirements
```

Small commits. No generated secrets. Keep `.env.example` current.

## Quality

Before merge:

```text
format/lint pass
Pest pass
Playwright critical flow pass
authorization tests pass
migration rollback considered
git diff reviewed
```

## Deliberate simplifications

- `ponytail:` Fixed receipt layout. Upgrade path: preset receipt templates after printer compatibility is proven.
- `ponytail:` One cafe/one location. Upgrade path: tenant/branch model only after real requirement.
- `ponytail:` Manual QRIS. Upgrade path: payment provider integration only with verified provider contract.
- `ponytail:` No offline mode. Upgrade path: sync design only after outage requirement is proven.
