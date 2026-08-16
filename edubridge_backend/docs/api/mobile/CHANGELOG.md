# EduBridge Mobile Backend Delivery Changelog

## Scope

This delivery extends the supplied EduBridge backend for the verified Parent + Teacher Flutter UI scope and prepares a Saudi-oriented mobile payment/wallet contract without changing Flutter code.

## Added mobile backend domains

- Parent student discovery, profile and aggregated home overview.
- Parent school activities: list/detail/register/cancel, including paid-activity invoice handoff.
- Shared support tickets for Parent/Teacher/Student plus Dashboard administration endpoints.
- Parent term report discovery/detail for the current grade-report workflow.
- Parent finance summary, invoice list/detail, payment-session/status/receipt APIs.
- Parent wallet balance, transaction history, top-up sessions and short-lived server-issued canteen QR tokens.
- Provider-agnostic `PaymentGateway` contract with Moyasar and Fake adapters.
- Payment webhook settlement with replay protection, amount/currency verification and reconciliation safeguards.
- Teacher dashboard summary, classes, class students, daily schedule/session discovery, assessment listing and gradebook.
- Notification `read-all` and preferences read endpoint.
- Richer Parent transport live-status payload; ETA remains `null` when no real estimator is configured.
- Dashboard CRUD/review support for school activities and support tickets.

## Database changes

Tenant migrations added:

- `2026_08_08_000001_create_mobile_activity_tables.php`
- `2026_08_08_000002_extend_support_tickets_for_mobile.php`
- `2026_08_08_000003_extend_payment_sessions_for_mobile.php`
- `2026_08_08_000004_extend_wallet_tokens_for_mobile.php`

## Saudi payment defaults

Server configuration defaults to:

- `mada` enabled
- `apple_pay` enabled
- `visa` enabled
- `mastercard` enabled
- `stc_pay` disabled by feature flag
- `samsung_pay` disabled by feature flag

The client must render methods returned by `GET /api/v1/payments/methods` rather than hard-code them.
All API/mobile money values use integer minor units at the boundary (100 halalas = 1 SAR).

## Security/integrity rules implemented

- Invoice payment amount is server-derived.
- Idempotency key required for payment sessions/top-ups.
- Payment success is webhook-authoritative, not callback-authoritative.
- Provider event replay is guarded.
- Amount/currency/invoice-balance drift is sent to `requires_reconciliation` instead of mutating balances.
- Wallet credits are ledger-based and correlated to payment references.
- QR token is random, short-lived and single-use; only SHA-256 token hash is stored.
- Parent/student ownership and current Teacher allocation are checked in mobile managers.
- Provider secrets are server-side only.

## Documentation generated

Under `docs/api/mobile/`:

- `README.md`
- `AUTHENTICATION.md`
- `SHARED.md`
- `PARENT.md`
- `TEACHER.md`
- `STUDENT.md`
- `TRANSPORT.md`
- `PAYMENTS_AND_WALLET.md`
- `ERRORS.md`
- `ENVIRONMENT.md`
- `INTEGRATION_MATRIX.md`
- `MISSING_APIS.md`
- `VERIFICATION.md`
- `IMPLEMENTATION_REPORT.md`
- `openapi-mobile-v1.yaml`
- Postman collection + Local/Production environments
- `EduBridge_Mobile_API_Contract_AR_Final.docx`

Master `openapi/openapi.yaml` was also updated.

## Deliberately not invented

- Student App APIs beyond the currently known assignment/shared routes: standalone Student UI source was not supplied.
- Driver/Transport read/current-trip/roster/stop APIs: standalone Driver UI source was not supplied.
- Public canteen/POS QR redemption endpoint: a POS app/authentication context was not supplied. The atomic wallet deduction primitive exists, but production redemption must be authenticated under a dedicated trusted client context.
- Fake ETA values: parent transport returns `eta_minutes = null` unless a real route estimator is integrated.

## Production decisions still required

- If every school uses its own PSP/Moyasar merchant account, implement a real tenant-aware secret resolver/secret manager. The current adapter uses deployment environment secrets and is suitable for one platform merchant account.
- Decide paid-activity temporary seat-hold expiry if `awaiting_payment` registrations should not reserve a seat indefinitely.
- Decide whether direct provider-side refunds are allowed. Current provider refund events require reconciliation with the audited local refund workflow.

## Verification status for the supplied archive

Static QA completed:

- 522 PHP files: syntax PASS.
- Mobile OpenAPI: 3.1.0, 86 paths, no unresolved internal refs, no duplicate operation IDs.
- Master OpenAPI: 3.1.0, 183 paths, no unresolved internal refs, no duplicate operation IDs.
- Postman collection/environments: valid JSON.
- Route/controller static mapping: all referenced controller methods resolve in the supplied source tree.

Runtime gate is **blocked by the uploaded archive**, because it does not contain root `artisan`, `composer.json`, `composer.lock`, `phpunit.xml` or `vendor/`. Therefore this delivery does not claim that migrations/full Laravel tests were executed in this sandbox. Run the checklist in `VERIFICATION.md` after overlaying these changes onto the complete repository.
