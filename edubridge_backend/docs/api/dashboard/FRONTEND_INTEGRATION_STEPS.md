# Dashboard Frontend Integration Steps

This document records the frontend work needed to consume the dashboard APIs that were previously listed as missing and are now implemented.

## Integrated API Groups

| Frontend area | Backend routes |
| --- | --- |
| Finance | `/api/v1/dashboard/finance/*` |
| Transport | `/api/v1/dashboard/transport/*` |
| School Settings | `/api/v1/dashboard/school/settings`, `/api/v1/dashboard/school/integrations/*` |
| Audit Logs | `/api/v1/dashboard/audit-logs` |
| RBAC | `/api/v1/dashboard/rbac/*`, `/api/v1/dashboard/admin-accounts/*` |
| Broadcasts | `/api/v1/dashboard/broadcasts/*` |

## Implementation Steps

1. Add typed API functions in `edubridge_dashboard/lib/dashboardApi.ts`.
2. Add frontend mappers for backend response shapes:
   - transport routes to `BusRoute`
   - broadcasts to `BroadcastMessage`
   - RBAC matrix to the existing local permission matrix
   - admin accounts to existing settings table rows
3. Extend `DashboardContext` to fetch all live modules with `Promise.allSettled`.
4. Preserve mock fallback when no dashboard token exists or a single endpoint fails.
5. Add `/finance` page and sidebar navigation item.
6. Update `/transport` to call live passengers/events, delay alerts, and driver contact logs.
7. Update `/messages` to create and send live dashboard broadcasts.
8. Update `/settings` to show live school settings, integrations, audit logs, RBAC, and admin accounts.
9. Verify with `npm run build`.

## Verification

Frontend build verified:

```bash
npm run build
```

Expected app routes now include:

```text
/finance
/transport
/messages
/settings
```

## Notes

- Login remains unchanged and must not send `school_code` or `app_type`.
- `device_id` remains generated once on the frontend with `crypto.randomUUID()`.
- Refund UI and transport route creation/assignment remain future UI work unless explicitly requested.
- Calendar events inside `/messages` remain local because they are not covered by the broadcast API contract.

