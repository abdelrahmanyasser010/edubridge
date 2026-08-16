# Performance and security hardening checklist

## Dependency scan

- Run Composer audit before release.
- Review abandoned packages and security advisories.
- No provider secret in `.env.example`, logs, fixtures, docs, or API responses.

## Query review

- Check every new report/list endpoint for tenant scope and pagination.
- Review indexes for foreign keys, status filters, and date filters.
- Investigate repeated queries in high-volume endpoints before caching.

## Load and abuse checks

- Smoke load login, notifications, parent reports, payment webhook, and wallet deduct.
- Rate-limit login, uploads, webhooks, GPS ingestion, broadcasts, and POS deduct.
- Verify idempotency on payment and wallet mutations.

## Threat checks

- Parent/student ownership enforced server-side.
- Webhooks verify signature and event replay.
- Files require scan clean before download/use.
- Audit redacts secrets and tokens.
