# Mobile Error Contract

## Envelope

```json
{
  "message": "...",
  "code": "VALIDATION_FAILED",
  "errors": {},
  "meta": {"request_id": "01..."}
}
```

## Main codes

| HTTP | code | Flutter action |
|---:|---|---|
| 401 | `UNAUTHENTICATED` | clear local auth session; route to login |
| 403 | `APP_ACCESS_DENIED` | wrong app/role; access denied UI |
| 403 | `FORBIDDEN` | permission denied; keep session |
| 404 | `NOT_FOUND` | not found/ownership-hidden state |
| 409 | `CONFLICT` | state/business conflict; show safe message and refresh |
| 422 | `VALIDATION_FAILED` | map `errors` to form fields |
| 429 | `RATE_LIMITED` | temporarily disable/retry later |
| 500 | `SERVER_ERROR` | safe generic UI + keep `request_id` for support |

## Network errors

Timeout/offline is not a 401. Do not log out. Show retry/offline state.

## Payment examples

Possible 409 scenarios:

- invoice already paid/cancelled;
- no outstanding balance;
- idempotency key reused with different request;
- disabled payment method;
- wallet top-up outside server limits;
- activity capacity reached/registration closed;
- paid activity cancellation requires refund workflow.

Do not branch business logic on English `message`; use HTTP + stable `code`, then show localized UI copy.
