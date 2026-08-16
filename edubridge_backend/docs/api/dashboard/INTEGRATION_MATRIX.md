# Dashboard Integration Matrix

This document maps planned dashboard screens to backend API routes, identifying readiness for integration.

---

## 💻 Screen Integration Status

| Dashboard Screen | Required API Endpoints | Implementation Status | Ready to Integrate? | Missing Backend Work |
| :--- | :--- | :---: | :---: | :--- |
| **Login Screen** | `POST /dashboard/auth/login` | **IMPLEMENTED** | ✅ Yes | None |
| **Overview Screen** | `GET /admin/dashboard/summary` | **IMPLEMENTED** | ✅ Yes | None |
| **Global Search** | `GET /admin/search` | **IMPLEMENTED** | ✅ Yes | None |
| **Academic Structure** | `GET /academic/structure` | **IMPLEMENTED** | ✅ Yes | None |
| **Manage Academic Years** | `POST\|PATCH\|DELETE /academic-years` | **IMPLEMENTED** | ✅ Yes | None |
| **Manage Factions/Subjects** | `POST\|PATCH\|DELETE /sections`, `/grade-levels`, `/subjects` | **IMPLEMENTED** | ✅ Yes | None |
| **Teacher Directory** | `GET\|POST\|PATCH\|DELETE /teachers` | **IMPLEMENTED** | ✅ Yes | None |
| **Parent Directory** | `GET\|POST\|PATCH\|DELETE /parents` | **IMPLEMENTED** | ✅ Yes | None |
| **Student Directory** | `GET\|POST\|PATCH\|DELETE /students` | **IMPLEMENTED** | ✅ Yes | None |
| **Allocations** | `GET\|POST\|PATCH\|DELETE /academic/allocations` | **IMPLEMENTED** | ✅ Yes | None |
| **Schedules & Sessions** | `GET\|POST\|PATCH\|DELETE /schedule-slots` | **IMPLEMENTED** | ✅ Yes | None |
| **Medical Excuses Review** | `GET /medical-excuses`<br>`POST /medical-excuses/{excuse}/[approve\|reject]` | **IMPLEMENTED** | ✅ Yes | None |
| **Grade Appeals Review** | `POST /grade-appeals/{appeal}/[approve\|reject]` | **IMPLEMENTED** | ✅ Yes | None |
| **Behavior Notes Audit** | `POST /behavior-notes/{note}/[publish\|reject\|resolve\|recommendations]` | **IMPLEMENTED** | ✅ Yes | None |
| **Leave Permits Review** | `POST /leave-permits/{permit}/[approve\|reject]` | **IMPLEMENTED** | ✅ Yes | None |
| **Conversations / Chat** | `GET\|POST /conversations`<br>`GET /conversations/{thread}/messages`<br>`POST /conversations/{thread}/send` | **IMPLEMENTED** | ✅ Yes | None |
| **Billing / Finance** | `GET /dashboard/finance/summary`<br>`GET\|POST\|PATCH\|DELETE /dashboard/finance/invoices`<br>`GET\|POST /dashboard/finance/payments`<br>`GET\|POST\|PATCH\|DELETE /dashboard/finance/discounts`<br>`GET /dashboard/finance/refunds`<br>`POST /dashboard/finance/payments/{payment}/refunds`<br>`GET /dashboard/finance/reports/*` | **IMPLEMENTED** | ✅ Yes | None |
| **Transport Management** | `GET /dashboard/transport/summary`<br>`GET\|POST /dashboard/transport/routes`<br>`GET\|PATCH\|DELETE /dashboard/transport/routes/{route}`<br>`GET /dashboard/transport/routes/{route}/passengers`<br>`GET /dashboard/transport/routes/{route}/events`<br>`POST /dashboard/transport/routes/{route}/assignments`<br>`PATCH\|DELETE /dashboard/transport/routes/{route}/assignments/{assignment}`<br>`POST /dashboard/transport/routes/{route}/delay-alert`<br>`POST /dashboard/transport/routes/{route}/contact-driver-log` | **IMPLEMENTED** | ✅ Yes | Dashboard token can read and write transport routes and assignments. |
| **Audit Logs** | `GET /dashboard/audit-logs`<br>`GET /dashboard/audit-logs/{auditLog}` | **IMPLEMENTED** | ✅ Yes | Mutation coverage continues to expand as new dashboard modules are added. |
| **System Settings** | `GET\|PATCH /dashboard/school/settings`<br>`GET\|PATCH\|POST /dashboard/school/integrations` | **IMPLEMENTED** | ✅ Yes | Raw integration secrets are never returned. |
| **Roles Management / RBAC** | `GET\|POST /dashboard/rbac/roles`<br>`GET /dashboard/rbac/permissions`<br>`GET\|PATCH /dashboard/rbac/matrix`<br>`GET\|POST /dashboard/admin-accounts`<br>`PATCH /dashboard/admin-accounts/{account}/[role\|status]` | **IMPLEMENTED** | ✅ Yes | Custom dashboard-login roles need an access-matrix decision before they can authenticate directly. |
| **Broadcast Notifications** | `GET\|POST /dashboard/broadcasts`<br>`GET /dashboard/broadcasts/{broadcast}`<br>`POST /dashboard/broadcasts/{broadcast}/[send\|cancel]`<br>`GET /dashboard/broadcasts/{broadcast}/deliveries` | **IMPLEMENTED** | ✅ Yes | Provider delivery workers remain environment/credentials dependent. |
| **Behavior Notes Dashboard List** | `GET /dashboard/behavior-notes`<br>`POST /behavior-notes/{note}/[publish\|recommendations\|resolve]` | **IMPLEMENTED** | ✅ Yes | Existing action endpoints now have a dashboard list source for live ids. |
| **Leave Permits Dashboard List** | `GET /dashboard/leave-permits`<br>`POST /leave-permits/{permit}/[approve\|reject]`<br>`POST /leave-permits/use-token` | **IMPLEMENTED** | ✅ Yes | Existing review/use endpoints now have a dashboard list source for live ids. |
| **Schedules Live IDs & Conflicts** | `GET /dashboard/schedules`<br>`POST /dashboard/schedules/conflicts/check` | **IMPLEMENTED** | ✅ Yes | Dashboard can read schedule_slot_id and teaching_session ids before action buttons. |
| **Calendar / Events** | `GET\|POST /dashboard/calendar/events`<br>`GET\|PATCH\|DELETE /dashboard/calendar/events/{event}` | **IMPLEMENTED** | ✅ Yes | Delete cancels the event instead of hard delete. |
| **Assessment Approval** | `GET /dashboard/assessments`<br>`GET /dashboard/assessments/{assessment}`<br>`POST /assessments/{assessment}/[approve\|publish\|lock]` | **IMPLEMENTED** | ✅ Yes | Read endpoints now provide live assessment ids, grade summaries, entries, and available action hints. |
| **Dashboard Canvas Configurator** | `GET\|PUT /dashboard/canvas-configs/{key}` | **IMPLEMENTED** | ✅ Yes | Optional persistence is available as tenant-scoped JSON with version conflict protection. |
| **Transport Route Configuration** | `POST\|PATCH\|DELETE /dashboard/transport/routes`<br>`POST\|PATCH\|DELETE /dashboard/transport/routes/{route}/assignments` | **IMPLEMENTED** | ✅ Yes | Dashboard can configure routes/assignments with dashboard token; deletes archive records. |
| **Dashboard Grade Editing** | `PUT /dashboard/assessments/{assessment}/grades` | **IMPLEMENTED** | ✅ Yes | Admin corrections are allowed before grades are parent-published or locked; stale revisions conflict. |
| **Dashboard Grade Exports** | `POST /dashboard/assessments/{assessment}/exports`<br>`GET /dashboard/reports/exports/{export}` | **IMPLEMENTED** | ✅ Yes | Export requests are queued and pollable; generated download URL is filled by the export worker later. |
| **Finance Refunds** | `POST /dashboard/finance/payments/{payment}/refunds`<br>`GET /dashboard/finance/refunds` | **IMPLEMENTED** | ✅ Yes | Refunds reverse dashboard finance payments without editing original payment records. |

---

## 💡 Frontend Integration Notes

1.  **Billing / Finance**: Use the live `/dashboard/finance/*` endpoints for invoices, payments, discounts, and reports. Keep refund-specific screens mocked until a refund dashboard slice is added.
2.  **Transport**: Use `/dashboard/transport/*` for dashboard route cards, passengers, events, delay alerts, and driver contact logs.
3.  **Active Tenant Context**: Ensure your axios instance updates its `baseURL` dynamically after the onboarding step, or includes `X-School-Code` header when querying `localhost`.
