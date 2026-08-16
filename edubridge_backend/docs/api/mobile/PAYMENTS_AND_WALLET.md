# Saudi Payments, Invoices & Student Wallet

## Architecture

Payment logic خلف `PaymentGateway`. المزود الأول في الإعداد هو Moyasar، والاختبارات/التطوير يمكن أن تستخدم `FakePaymentGateway`.

Default launch payment methods from server config:

- `mada`
- `apple_pay`
- `visa`
- `mastercard`

Optional flags:

- `stc_pay`
- `samsung_pay`

Flutter **لا يثبت methods في الكود**؛ اعرض فقط ما يرجعه:

`GET /payments/methods`

## Money rules

- API/mobile boundary = integer minor units.
- SAR: 100 minor units = 1 SAR.
- Invoice amount is server-authoritative.
- Client does not send invoice payment amount.
- Wallet top-up accepts `amount_minor` only within server min/max.
- Existing finance DB decimals remain for backward compatibility and are converted centrally.

## Parent finance summary

`GET /parent/students/{student}/finance/summary`

Example:

```json
{
  "data": {
    "currency": "SAR",
    "total_due_minor": 312500,
    "overdue_minor": 10000,
    "next_due_date": "2026-09-15",
    "wallet_balance_minor": 15000,
    "unpaid_invoices_count": 2
  },
  "meta": {"request_id": "01..."}
}
```

## Invoices

- `GET /parent/students/{student}/invoices`
- `GET /parent/students/{student}/invoices/{invoice}`

Filters: `status`, `from`, `to`, `page`, `per_page`.

Invoice mobile payload exposes monetary fields as:

- `subtotal_minor`
- `discount_minor`
- `tax_minor`
- `total_minor`
- `paid_minor`
- `due_minor`

plus line items and recorded payments.

## Available methods

`GET /payments/methods`

Server response is the only source for what the school/provider has enabled.

## Invoice payment session

`POST /parent/students/{student}/invoices/{invoice}/payment-sessions`

```json
{
  "method": "mada",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000"
}
```

Server:

1. verifies Parent → Student ownership;
2. locks invoice;
3. derives outstanding balance;
4. validates method;
5. replays same idempotency key safely if request is identical;
6. creates provider correlation/given ID;
7. returns safe provider SDK configuration.

## Wallet

`GET /parent/students/{student}/wallet`

`GET /parent/students/{student}/wallet/transactions`

## Wallet top-up session

`POST /parent/students/{student}/wallet/top-up-sessions`

```json
{
  "amount_minor": 10000,
  "payment_method": "apple_pay",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440001"
}
```

Wallet is **not credited** when Flutter returns from provider UI. Credit happens only after verified paid webhook.

## Canteen QR payment token

`POST /parent/students/{student}/wallet/payment-token`

Optional request:

```json
{
  "max_amount_minor": 5000
}
```

Response:

```json
{
  "data": {
    "token": "random-server-token",
    "expires_at": "2026-08-08T15:30:45Z",
    "single_use": true,
    "scope": "canteen",
    "max_amount_minor": 5000
  },
  "meta": {"request_id": "01..."}
}
```

Flutter يحول **token الصادر من السيرفر** إلى QR. ممنوع توليد QR دفع محليًا من timestamp/student id.

Token behavior:

- raw token لا يخزن في DB؛ المخزن SHA-256 hash.
- TTL قصير (`WALLET_QR_TTL_SECONDS`, default 60).
- single-use.
- scope = `canteen`.
- max purchase cap server-controlled.

## Payment status

`GET /payments/{payment}`

Main status values:

- `initiated`
- `requires_action`
- `paid`
- `failed`
- `cancelled`

## Receipt

`GET /payments/{payment}/receipt`

متاح فقط بعد local payment status = `paid`.

## Provider webhook — server-to-server only

`POST /webhooks/payments/{provider}`

Flutter **لا يستدعي هذا endpoint**.

Controls:

- provider-native webhook verification;
- unique provider event ID / replay protection;
- row locking during settlement;
- amount + currency validation against server session;
- sanitized webhook persistence;
- idempotent FinancePayment creation;
- wallet credit exactly once via ledger reference;
- unknown events recorded then ignored;
- paid activity registration is confirmed after its invoice settles.

### Refund events

Provider `payment_refunded` event does **not** blindly decrement `paid_total` or wallet balance. It is recorded as `requires_reconciliation`, because local refunds must go through the existing audited finance refund workflow to prevent accounting drift.

## Flutter payment flow

1. Fetch invoice/summary.
2. `GET /payments/methods`.
3. Create payment session with UUID idempotency key.
4. Use `provider_config` with the selected provider SDK/flow.
5. Handle app callback as UX only.
6. Poll `GET /payments/{payment}` / refresh invoice.
7. Backend webhook is source of truth for paid state.
8. After `paid`, offer receipt.

Never store server secret keys in Flutter and never treat the callback alone as successful payment.
