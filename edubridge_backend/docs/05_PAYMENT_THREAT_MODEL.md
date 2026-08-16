# Payment threat model

Status: active baseline for PAY-001.

## Controls

- Server never trusts redirects as proof of payment; only verified provider webhooks can mutate payment state.
- Webhooks require the provider-native authentication contract plus provider event-id replay protection. Moyasar uses a configured webhook `secret_token`; providers that document HMAC use HMAC/timestamp verification. See ADR-009.
- Client idempotency keys are required for payment session creation, wallet top-up, POS deduct, refunds, and webhook processing.
- No card data is stored. Provider secrets stay server-side and must not appear in logs, responses, fixtures, or audit payloads.
- Refunds/reversals are separate audited actions; wallet ledger entries are immutable and compensating, not edited.

## Required contract behavior

- Invalid signatures return forbidden and do not reserve/process an event.
- Replayed terminal event ids are treated as safe duplicates before side effects; an event left in `received` after a transient processing failure may be retried under a row lock.
- Same idempotency key with different payload returns conflict.
- Paid/failed/void events are explicit states; unknown provider events are recorded then ignored safely. Provider refund events require reconciliation through the audited finance-refund workflow before local accounting is changed.


## Saudi mobile payment boundary

- Mobile-facing monetary values use integer minor units; existing decimal finance storage is converted through `App\Support\Money`.
- Invoice payment amount is server-derived under a database lock. Wallet top-up is bounded by server configuration.
- The mobile client never receives provider secret keys and never proves payment by callback/redirect.
- Wallet QR tokens are random, hashed at rest, short-lived, single-use, and scope-limited.
- Webhook payload persistence is sanitized and excludes the webhook secret and sensitive card data.
