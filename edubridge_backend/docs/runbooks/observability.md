# Observability and operations runbook

## Signals

- API error rate by route and status code.
- Queue depth and oldest pending outbox age.
- Failed notification delivery attempts.
- Payment webhook forbidden/conflict counts.
- Slow database queries over the agreed threshold.

## Alerts

- Critical: payment webhook failures spike, queue oldest age over 10 minutes, tenant database unavailable.
- Warning: notification push failure rate over baseline, report export backlog growing, repeated auth throttling.

## Incident flow

1. Identify tenant, request_id, route/job name, and deployment version.
2. Stop unsafe workers only if duplicate side effects are suspected.
3. Preserve audit/outbox/payment webhook rows before retrying.
4. Apply fix or replay idempotent jobs.
5. Write post-incident note with impact, root cause, and prevention.

## Deploy checklist

- Migrations reviewed and reversible where possible.
- `composer format:test`, `composer analyse`, targeted tests, and OpenAPI lint pass for touched API slices.
- Queue workers restarted after deploy.
- Smoke: `/health`, auth login, tenant context, notification outbox, payment webhook fake.
