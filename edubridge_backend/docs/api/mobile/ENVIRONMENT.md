# Mobile Backend Environment

The supplied archive does not include a root `.env.example`, so these variables are documented here and must be copied into the complete deployment environment/secret manager.

```dotenv
PAYMENT_PROVIDER=moyasar
PAYMENT_CURRENCY=SAR

PAYMENT_METHOD_MADA=true
PAYMENT_METHOD_APPLE_PAY=true
PAYMENT_METHOD_VISA=true
PAYMENT_METHOD_MASTERCARD=true
PAYMENT_METHOD_STC_PAY=false
PAYMENT_METHOD_SAMSUNG_PAY=false

MOYASAR_PUBLISHABLE_KEY=
MOYASAR_SECRET_KEY=
MOYASAR_WEBHOOK_SECRET=
MOYASAR_API_URL=https://api.moyasar.com/v1
MOYASAR_CALLBACK_URL=edubridge://payments/return

PAYMENT_SESSION_TTL_MINUTES=30
WALLET_TOP_UP_MIN_MINOR=1000
WALLET_TOP_UP_MAX_MINOR=100000
WALLET_QR_MAX_PURCHASE_MINOR=50000
WALLET_QR_TTL_SECONDS=60
```

## Production rules

- Provider secrets server-side only.
- Do not commit secrets.
- Configure production callback/deep-link exactly for the Flutter apps.
- Configure provider webhook URL on a tenant-resolvable host, e.g. `https://alpha.api.edubridge.com/api/v1/webhooks/payments/moyasar`.
- `integration_settings.secret_ref` is a reference; it is not a plaintext-secret store.
- Use `PAYMENT_PROVIDER=fake` only in controlled local/test environments.
