# ADR-009: Mobile payments and student wallet for Saudi Arabia

- Status: Accepted
- Date: 2026-08-08
- Scope: Parent mobile app payment, invoices, wallet top-up, and canteen QR token
- Extends: ADR-006

## Decision

EduBridge keeps payment business logic provider-agnostic behind `PaymentGateway`. The first production adapter is Moyasar and the test adapter is `FakePaymentGateway`.

The mobile-facing contract is server-authoritative:

- School currency defaults to `SAR`.
- Mobile/payment API amounts are integer minor units. Existing finance tables keep decimal amounts for backward compatibility; `Money` is the conversion boundary.
- The enabled launch methods are `mada`, `apple_pay`, `visa`, and `mastercard`. `stc_pay` and `samsung_pay` are opt-in configuration flags.
- Invoice payment amount is derived from the locked invoice balance. Flutter cannot choose or override it.
- Wallet top-up amount is client-selected only inside server-configured minimum/maximum limits.
- A stable UUID `given_id` is created by the server for a payment session and used as the provider payment identifier/correlation id.
- A client callback/deep link is UX only. It never settles an invoice or credits a wallet.
- Only a verified, idempotently processed provider webhook can settle payment state.
- Provider webhook payloads are sanitized before persistence; card secrets/PAN/CVV and webhook secrets are never persisted in audit or webhook payload columns.
- Refund provider events do not directly rewrite local accounting. They enter `requires_reconciliation`; the existing audited finance-refund workflow is the accounting authority.

## Webhook authentication

Provider-native authentication takes precedence over a generic invented signature scheme. For the Moyasar adapter, the configured webhook secret is compared to the provider `secret_token` using constant-time comparison. Other future providers may use HMAC headers/signatures if that is their documented contract.

Replay protection is independent of provider authentication: `(provider, event_id)` is unique. A retry of an event left in `received` may resume processing; terminal `processed`, `ignored`, or `requires_reconciliation` events are safe duplicates.

## Student wallet QR

The mobile app must not invent a QR value from a timestamp. `POST /parent/students/{student}/wallet/payment-token` issues a random token stored only as SHA-256 hash, short TTL, single-use, scoped to `canteen`, and capped by a server-controlled maximum amount.

## Secrets and multi-school deployment

No secret is embedded in Flutter. Production credentials must be provided server-side through environment/secret-management deployment. `integration_settings.secret_ref` remains a reference and must not contain plaintext provider credentials.

## Operational note

The archive used for this implementation did not contain the Laravel root runtime files (`artisan`, `composer.json`, `composer.lock`, `phpunit.xml`, `vendor/`). Therefore this delivery was syntax/contract validated in the supplied archive but requires migration and full test execution in the complete repository before production deployment.
