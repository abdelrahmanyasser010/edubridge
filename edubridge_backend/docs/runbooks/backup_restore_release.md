# Backup, restore, and release rehearsal

## Backup policy

- Central database and every tenant database are backed up separately.
- Backups are encrypted, access-controlled, and tagged by environment, school id, and timestamp.
- Restore drills must not use production PII in shared/dev environments.

## Restore rehearsal

Record:

- backup identifier;
- target environment;
- started_at and completed_at;
- measured restore duration;
- migration version after restore;
- smoke results.

## Smoke after restore

- `/health`
- auth login
- tenant context resolution
- parent ownership read
- queue/outbox insert
- payment webhook fake verification

## Rollback rehearsal

- Identify last known good version.
- Confirm migrations with destructive changes have restore path.
- Stop workers before rollback if duplicate side effects are possible.
- Preserve audit/outbox/payment evidence.
