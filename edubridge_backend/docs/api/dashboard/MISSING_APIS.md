# Planned & Missing Dashboard APIs

This document details all APIs needed by the Dashboard panel that are either not yet implemented in the backend, or require changes to the authorization matrix before integration can succeed.

---

## 1. Financial & Billing APIs (IMPLEMENTED)
Dashboard finance now has tenant-scoped billing routes under `/api/v1/dashboard/finance`.

### A. List Fees & Invoices
*   **Method**: `GET`
*   **Route**: `/api/v1/dashboard/finance/invoices`
*   **Required parameters**: `student_id`, `status` (paid, unpaid)
*   **Status**: `IMPLEMENTED`

### B. Generate Invoices
*   **Method**: `POST`
*   **Route**: `/api/v1/dashboard/finance/invoices`
*   **Status**: `IMPLEMENTED`

### C. Record Payment
*   **Method**: `POST`
*   **Route**: `/api/v1/dashboard/finance/payments`
*   **Status**: `IMPLEMENTED`

### D. Discounts & Reports
*   **Routes**:
    * `GET|POST /api/v1/dashboard/finance/discounts`
    * `PATCH|DELETE /api/v1/dashboard/finance/discounts/{discount}`
    * `GET /api/v1/dashboard/finance/reports/collections`
    * `GET /api/v1/dashboard/finance/reports/outstanding`
    * `GET /api/v1/dashboard/finance/reports/student-statement/{student}`
*   **Status**: `IMPLEMENTED`

---

## 2. Transport Management (IMPLEMENTED)
Dashboard-specific transport endpoints are implemented under `/api/v1/dashboard/transport`.

### A. Dashboard Transport Routes
*   **Status**: `IMPLEMENTED`
*   **Routes**:
    * `GET /api/v1/dashboard/transport/summary`
    * `GET /api/v1/dashboard/transport/routes`
    * `GET /api/v1/dashboard/transport/routes/{route}`
    * `GET /api/v1/dashboard/transport/routes/{route}/passengers`
    * `GET /api/v1/dashboard/transport/routes/{route}/events`
    * `POST /api/v1/dashboard/transport/routes/{route}/delay-alert`
    * `POST /api/v1/dashboard/transport/routes/{route}/contact-driver-log`

---

## 3. Audit Logs (IMPLEMENTED)
Dashboard audit log read endpoints are implemented under `/api/v1/dashboard/audit-logs`.

### A. View Activity/Audit Logs
*   **Method**: `GET`
*   **Route**: `/api/v1/dashboard/audit-logs`
*   **Status**: `IMPLEMENTED`
*   **Required fields**: `id`, `actor`, `action`, `entity_type`, `entity_id`, `summary`, `before`, `after`, `ip_address`, `request_id`, `created_at` (paginated)
*   **Filters**: `actor_id`, `action`, `entity_type`, `entity_id`, `from`, `to`, `page`, `per_page`

### B. View Audit Log Detail
*   **Method**: `GET`
*   **Route**: `/api/v1/dashboard/audit-logs/{auditLog}`
*   **Status**: `IMPLEMENTED`
*   **Security**: reads are tenant-scoped and sensitive payload keys are redacted before response.

---

## 4. School Profile & Settings (IMPLEMENTED)
Safe school settings and masked integration endpoints are implemented under `/api/v1/dashboard/school`.

### A. View/Update School Settings
*   **Method**: `GET | PATCH`
*   **Route**: `/api/v1/dashboard/school/settings`
*   **Status**: `IMPLEMENTED`
*   **Required fields**: `name`, `timezone`, `locale`, `currency`

### B. View/Update/Test Integrations
*   **Methods**: `GET | PATCH | POST`
*   **Routes**:
    * `GET /api/v1/dashboard/school/integrations`
    * `PATCH /api/v1/dashboard/school/integrations/{integration}`
    * `POST /api/v1/dashboard/school/integrations/{integration}/test`
*   **Status**: `IMPLEMENTED`
*   **Security**: raw secrets are never returned; API keys are stored as server-side `secret_ref` with masked response only.

---

## 5. Roles Management / RBAC (IMPLEMENTED)
Dashboard RBAC management endpoints are implemented under `/api/v1/dashboard/rbac` and `/api/v1/dashboard/admin-accounts`.

### A. Roles, Permissions, and Matrix
*   **Routes**:
    * `GET|POST /api/v1/dashboard/rbac/roles`
    * `GET /api/v1/dashboard/rbac/permissions`
    * `GET|PATCH /api/v1/dashboard/rbac/matrix`
*   **Status**: `IMPLEMENTED`
*   **Required Permissions**: `rbac.view`, `rbac.manage`

### B. Dashboard Admin Accounts
*   **Routes**:
    * `GET|POST /api/v1/dashboard/admin-accounts`
    * `PATCH /api/v1/dashboard/admin-accounts/{account}/role`
    * `PATCH /api/v1/dashboard/admin-accounts/{account}/status`
*   **Status**: `IMPLEMENTED`
*   **Notes**: account membership status is school-scoped in `school_user`; role changes are synchronized to tenant `user_roles` and audited.

---

## 6. Broadcast Notifications (IMPLEMENTED)
Dashboard broadcast lifecycle endpoints are implemented under `/api/v1/dashboard/broadcasts`.

### A. Broadcast Lifecycle
*   **Routes**:
    * `GET|POST /api/v1/dashboard/broadcasts`
    * `GET /api/v1/dashboard/broadcasts/{broadcast}`
    * `POST /api/v1/dashboard/broadcasts/{broadcast}/send`
    * `POST /api/v1/dashboard/broadcasts/{broadcast}/cancel`
    * `GET /api/v1/dashboard/broadcasts/{broadcast}/deliveries`
*   **Status**: `IMPLEMENTED`
*   **Required Permissions**: `broadcasts.view`, `broadcasts.send`, `broadcasts.schedule`, `broadcasts.cancel`
*   **Notes**: create without `scheduled_at` stores a draft; create with `scheduled_at` schedules an outbox dispatch; send creates channel-specific notification deliveries and audit logs.

---

## 7. Dashboard Behavior Notes (IMPLEMENTED)

### A. List Behavior Notes for Dashboard Actions
*   **Method**: `GET`
*   **Route**: `/api/v1/dashboard/behavior-notes`
*   **Status**: `IMPLEMENTED`
*   **Filters**: `status`, `severity`, `student_id`, `section_id`, `from`, `to`, `page`, `per_page`
*   **Notes**: provides live note ids and available action hints for `publish`, `reject`, `recommendations`, and `resolve`.

---

## 8. Dashboard Leave Permits (IMPLEMENTED)

### A. List Leave Permits for Dashboard Actions
*   **Method**: `GET`
*   **Route**: `/api/v1/dashboard/leave-permits`
*   **Status**: `IMPLEMENTED`
*   **Filters**: `status`, `student_id`, `section_id`, `from`, `to`, `page`, `per_page`
*   **Notes**: provides live permit ids and available action hints for `approve`, `reject`, and `use-token`.

---

## 9. Dashboard Schedules Live IDs & Conflict Check (IMPLEMENTED)

### A. Live Schedule/Teaching Session IDs
*   **Method**: `GET`
*   **Route**: `/api/v1/dashboard/schedules`
*   **Status**: `IMPLEMENTED`

### B. Conflict Check
*   **Method**: `POST`
*   **Route**: `/api/v1/dashboard/schedules/conflicts/check`
*   **Status**: `IMPLEMENTED`

---

## 10. Dashboard Calendar Events (IMPLEMENTED)

### A. Calendar Events CRUD
*   **Routes**:
    * `GET|POST /api/v1/dashboard/calendar/events`
    * `GET|PATCH|DELETE /api/v1/dashboard/calendar/events/{event}`
*   **Status**: `IMPLEMENTED`
*   **Schema**: tenant `calendar_events`

---

## 11. Dashboard Assessment Approval Reads (IMPLEMENTED)

### A. Assessment List and Details
*   **Routes**:
    * `GET /api/v1/dashboard/assessments`
    * `GET /api/v1/dashboard/assessments/{assessment}`
*   **Status**: `IMPLEMENTED`
*   **Notes**: uses existing `assessments` and `grade_entries`; action buttons continue to call existing approve/publish/lock endpoints.

---

## 12. Dashboard Canvas Config Persistence (IMPLEMENTED)

### A. Canvas Config Get/Save
*   **Routes**:
    * `GET /api/v1/dashboard/canvas-configs/{key}`
    * `PUT /api/v1/dashboard/canvas-configs/{key}`
*   **Status**: `IMPLEMENTED`
*   **Schema**: tenant `dashboard_canvas_configs`
*   **Notes**: saves configurator state as tenant-scoped JSON with version conflict protection.

---

## 13. Dashboard Transport Write Management (IMPLEMENTED)

### A. Route CRUD/Archive
*   **Routes**:
    * `POST /api/v1/dashboard/transport/routes`
    * `PATCH /api/v1/dashboard/transport/routes/{route}`
    * `DELETE /api/v1/dashboard/transport/routes/{route}`
*   **Status**: `IMPLEMENTED`

### B. Route Assignments
*   **Routes**:
    * `POST /api/v1/dashboard/transport/routes/{route}/assignments`
    * `PATCH /api/v1/dashboard/transport/routes/{route}/assignments/{assignment}`
    * `DELETE /api/v1/dashboard/transport/routes/{route}/assignments/{assignment}`
*   **Status**: `IMPLEMENTED`
*   **Notes**: dashboard no longer needs a transport app token for route configuration writes.

---

## 14. Dashboard Grade Entry Editing (IMPLEMENTED)

### A. Admin Grade Corrections
*   **Route**: `PUT /api/v1/dashboard/assessments/{assessment}/grades`
*   **Status**: `IMPLEMENTED`
*   **Notes**: uses existing `grade_entries`; only dashboard admins with `grade.approve` can edit, and edits are rejected after published/locked states.

---

## 15. Dashboard Grade/Report Export (IMPLEMENTED)

### A. Assessment Grade Sheet Export
*   **Routes**:
    * `POST /api/v1/dashboard/assessments/{assessment}/exports`
    * `GET /api/v1/dashboard/reports/exports/{export}`
*   **Status**: `IMPLEMENTED`
*   **Schema**: tenant `report_exports`
*   **Notes**: generation is queued via tenant outbox; status can be polled by `export_id`.

---

## 16. Finance Refunds (IMPLEMENTED)

### A. Dashboard Finance Refunds
*   **Routes**:
    * `POST /api/v1/dashboard/finance/payments/{payment}/refunds`
    * `GET /api/v1/dashboard/finance/refunds`
*   **Status**: `IMPLEMENTED`
*   **Schema**: tenant `finance_refunds`
*   **Notes**: this is separate from provider `payment_refunds`; dashboard refunds are tied to manual/dashboard `finance_payments` and reverse invoice paid totals.
