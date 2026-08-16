# Dashboard Frontend Action Integration Round

This note records what the dashboard frontend now consumes from the backend after the available dashboard APIs were supplied, verified in `routes/api.php`, and wired in the frontend.

## Newly Consumed Backend APIs

- Behavior notes list: `GET /api/v1/dashboard/behavior-notes`
- Leave permits list: `GET /api/v1/dashboard/leave-permits`
- Schedule slots list: `GET /api/v1/dashboard/schedules`
- Schedule conflict check: `POST /api/v1/dashboard/schedules/conflicts/check`
- Calendar events list/create: `GET|POST /api/v1/dashboard/calendar/events`
- Calendar event update/delete client support: `PATCH|DELETE /api/v1/dashboard/calendar/events/{id}`
- Dashboard assessments list/detail client support: `GET /api/v1/dashboard/assessments`, `GET /api/v1/dashboard/assessments/{id}`
- Dashboard assessment grade editing: `PUT /api/v1/dashboard/assessments/{assessment}/grades`
- Dashboard assessment grade exports:
  - `POST /api/v1/dashboard/assessments/{assessment}/exports`
  - `GET /api/v1/dashboard/reports/exports/{export}`
- Canvas config load/save: `GET|PUT /api/v1/dashboard/canvas-configs/{key}`
- Finance mutations:
  - `POST /api/v1/dashboard/finance/invoices`
  - `DELETE /api/v1/dashboard/finance/invoices/{invoice}`
  - `POST /api/v1/dashboard/finance/payments`
  - `POST|PATCH /api/v1/dashboard/finance/discounts`
  - `GET /api/v1/dashboard/finance/refunds`
  - `POST /api/v1/dashboard/finance/payments/{payment}/refunds`
- Transport mutations:
  - `POST /api/v1/dashboard/transport/routes`
  - `PATCH /api/v1/dashboard/transport/routes/{route}`
  - `DELETE /api/v1/dashboard/transport/routes/{route}`
  - `POST /api/v1/dashboard/transport/routes/{route}/assignments`
  - `PATCH /api/v1/dashboard/transport/routes/{route}/assignments/{assignment}`
  - `DELETE /api/v1/dashboard/transport/routes/{route}/assignments/{assignment}`
- RBAC lifecycle:
  - `POST /api/v1/dashboard/rbac/roles`
  - `PATCH /api/v1/dashboard/admin-accounts/{account}/status`
- Broadcast lifecycle:
  - `POST /api/v1/dashboard/broadcasts/{broadcast}/cancel`
  - `GET /api/v1/dashboard/broadcasts/{broadcast}/deliveries`

## Existing Action APIs Also Used

- Academic section creation: `POST /api/v1/sections`
- Academic subject creation: `POST /api/v1/subjects`
- Parent creation: `POST /api/v1/parents`
- Student creation: `POST /api/v1/students`
- Teacher creation: `POST /api/v1/teachers`
- Medical excuses review:
  - `POST /api/v1/medical-excuses/{id}/approve`
  - `POST /api/v1/medical-excuses/{id}/reject`
- Behavior transitions:
  - `POST /api/v1/behavior-notes/{id}/publish`
  - `POST /api/v1/behavior-notes/{id}/recommendations`
  - `POST /api/v1/behavior-notes/{id}/resolve`
- Parent summons: `POST /api/v1/parent-summons`
- Leave permit approval: `POST /api/v1/leave-permits/{id}/approve`
- Teacher substitution: `POST /api/v1/teacher-substitutions`
- Broadcast send/schedule:
  - `POST /api/v1/dashboard/broadcasts`
  - `POST /api/v1/dashboard/broadcasts/{id}/send`
- Assessment transitions:
  - `POST /api/v1/assessments/{id}/approve`
  - `POST /api/v1/assessments/{id}/publish`
  - `POST /api/v1/assessments/{id}/lock`

## Frontend Behavior

- The frontend does not call undocumented routes.
- Local fallback remains active only when not authenticated or when a demo row has no real backend id.
- Teacher substitutions only become live when `/dashboard/schedules` provides a `teaching_session_id`.
- Grade approval chooses a live assessment for the selected section only when the dashboard assessment list exposes an available action.
- Grade editing needs a live dashboard assessment id and backend student ids in the assessment section.
- Grade exports are queued first and refreshed through the report export endpoint.
- Finance refunds need a live backend payment id.
- Transport route and assignment writes need live backend route/student/assignment ids.

## Remaining Backend Work

No blocking backend work remains for this dashboard integration round.

See `FRONTEND_REMAINING_BACKEND_GAPS.md` for the final gap status.

## Verification

Frontend verification completed with `npm run build`.
