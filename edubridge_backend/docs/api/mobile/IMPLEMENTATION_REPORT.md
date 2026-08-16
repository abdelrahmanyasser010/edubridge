# Mobile Backend Implementation Report — 2026-08-08

## Added for Parent

- My Students.
- Parent profile read/update.
- Aggregated Home overview.
- Activities and registrations, including paid-activity invoice creation.
- Support ticket lifecycle shared with Teacher/Student.
- Term report list/detail.
- Finance summary and invoice mobile payloads.
- Payment method discovery, payment session/status/receipt.
- Student wallet balance/transactions/top-up.
- Short-lived single-use server-issued canteen QR token.
- Richer transport live-status payload.
- Notifications read-all and preference read.

## Added for Teacher

- Dashboard summary.
- My Classes.
- Class detail/students.
- Daily schedule/session discovery.
- Assessment list.
- Class gradebook.

## Saudi payment implementation

- Provider interface + Moyasar adapter + fake adapter.
- Server-configured methods: mada, Apple Pay, Visa, Mastercard default on; STC Pay/Samsung Pay optional.
- SAR/minor-unit boundary.
- Invoice amount derived server-side.
- Idempotency key on mobile payment session creation.
- Provider event replay protection.
- Verified webhook settlement.
- Sanitized provider payload storage.
- No client callback as proof of payment.
- Refund webhook requires finance reconciliation instead of silent ledger mutation.
- Provider amount/currency mismatch or changed invoice outstanding balance also requires reconciliation and does not silently settle.

## Schema additions

- `2026_08_08_000001_create_mobile_activity_tables.php`
- `2026_08_08_000002_extend_support_tickets_for_mobile.php`
- `2026_08_08_000003_extend_payment_sessions_for_mobile.php`
- `2026_08_08_000004_extend_wallet_tokens_for_mobile.php`

## Supporting Dashboard endpoints

Added school activity management and support-ticket management routes so the mobile features have an administrative backend counterpart.

## Important verification limitation

See `VERIFICATION.md`. The user-provided ZIP is missing the Laravel root dependency/runtime files, so full migrations/test suite cannot be executed from this archive alone.

## Static verification evidence

- PHP syntax: 522 files passed.
- Mobile OpenAPI: 3.1.0, 86 paths, no unresolved internal refs.
- Master OpenAPI: 3.1.0, 183 paths, no unresolved internal refs.
- Postman JSON: collection + Local + Production example parsed successfully.
- Route/controller method references: statically resolved.
- Full Laravel runtime tests remain blocked by the incomplete uploaded archive; see `VERIFICATION.md`.
