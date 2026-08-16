# Dashboard Backend Gap Status

This file supersedes the older dashboard missing-API notes.

## Current Status

No blocking backend gaps remain for the dashboard integration round after the latest backend additions were verified and wired in the frontend.

The frontend now has API clients, context actions, and UI entry points for the four items that were previously listed as missing:

1. Dashboard transport write management.
2. Dashboard grade entry editing.
3. Dashboard grade/report export.
4. Finance refunds.

## Newly Verified Backend Routes

### Transport Write Management

- `POST /api/v1/dashboard/transport/routes`
- `PATCH /api/v1/dashboard/transport/routes/{route}`
- `DELETE /api/v1/dashboard/transport/routes/{route}`
- `POST /api/v1/dashboard/transport/routes/{route}/assignments`
- `PATCH /api/v1/dashboard/transport/routes/{route}/assignments/{assignment}`
- `DELETE /api/v1/dashboard/transport/routes/{route}/assignments/{assignment}`

### Grade Editing And Export

- `PUT /api/v1/dashboard/assessments/{assessment}/grades`
- `POST /api/v1/dashboard/assessments/{assessment}/exports`
- `GET /api/v1/dashboard/reports/exports/{export}`

### Finance Refunds

- `POST /api/v1/dashboard/finance/payments/{payment}/refunds`
- `GET /api/v1/dashboard/finance/refunds`

## Frontend Wiring Completed

The dashboard frontend now calls these routes from:

- `lib/dashboardApi.ts`
- `context/DashboardContext.tsx`
- `app/transport/page.tsx`
- `app/grades/page.tsx`
- `app/finance/page.tsx`

## Remaining Non-Blocking Notes

- Local/demo rows without numeric backend ids still do not call live APIs.
- The frontend still falls back to mock data when no dashboard token exists.
- Optional future product work, such as richer refund approvals or export download lifecycle screens, can be planned separately. These are not backend blockers for the current dashboard integration.

## Verification

Frontend build verification:

```bash
npm run build
```

Status: passed.
