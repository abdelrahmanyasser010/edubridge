# EduBridge Dashboard APIs Detailed Contract

This document provides a exhaustive, technical reference for all endpoints exposed to the **EduBridge Dashboard**.

---

## 1. Authentication & Device Sessions

### 1.1 Login
*   **Purpose**: Authenticate dashboard users and issue a personal access token.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `POST`
*   **Full Path**: `/api/v1/dashboard/auth/login`
*   **Local Example**: `http://alpha.edubridge.test/api/v1/dashboard/auth/login`
*   **Required Authentication**: None
*   **Allowed Dashboard Roles**: `school_admin`, `academic_admin`, `student_affairs`, `finance_officer`
*   **Request Body**:
    ```json
    {
      "email": "admin@example.test",
      "password": "secret-password",
      "device_id": "browser-uuid-1234",
      "device_name": "Chrome (Windows)"
    }
    ```
*   **Validation Rules**:
    *   `email`: required, string, valid email address
    *   `password`: required, string
    *   `device_id`: required, string, max:128
    *   `device_name`: optional/nullable, string, max:128
*   **Success Response (200 OK)**:
    ```json
    {
      "data": {
        "token": "4|sanctum_personal_access_token",
        "token_type": "Bearer",
        "expires_at": null,
        "user": {
          "id": "1",
          "name": "Admin User",
          "email": "admin@example.test"
        },
        "school": {
          "id": 1,
          "public_id": "d8a1e2f7-...",
          "code": "alpha",
          "name": "Alpha School"
        }
      },
      "meta": {
        "request_id": "01J2V..."
      }
    }
    ```

### 1.2 Get Authenticated Profile
*   **Purpose**: Get details of the active user session.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/auth/me`
*   **Required Authentication**: Sanctum Bearer Token
*   **Success Response (200 OK)**:
    ```json
    {
      "data": {
        "user": {
          "id": "1",
          "name": "Admin User",
          "email": "admin@example.test"
        },
        "school": {
          "id": 1,
          "code": "alpha",
          "name": "Alpha School"
        },
        "device_session": {
          "id": 1,
          "device_id": "browser-uuid-1234",
          "device_name": "Chrome (Windows)",
          "app_type": "dashboard",
          "platform": "web",
          "last_active_at": "2026-07-21T09:00:00Z"
        }
      }
    }
    ```

### 1.3 Logout
*   **Purpose**: Terminate/revoke the current session token.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `POST`
*   **Full Path**: `/api/v1/auth/logout`
*   **Success Response**: `204 No Content`

### 1.4 List Device Sessions
*   **Purpose**: List all device sessions associated with the user.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/auth/device-sessions`
*   **Success Response (200 OK)**:
    ```json
    {
      "data": [
        {
          "id": 1,
          "device_id": "browser-uuid-1234",
          "device_name": "Chrome (Windows)",
          "app_type": "dashboard",
          "platform": "web",
          "last_active_at": "2026-07-21T09:00:00Z"
        }
      ]
    }
    ```

### 1.5 Revoke Device Session
*   **Purpose**: Terminate a specific session.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `POST`
*   **Full Path**: `/api/v1/auth/device-sessions/{deviceSession}/revoke`
*   **Path Parameters**:
    *   `deviceSession`: integer (id of session)
*   **Success Response**: `204 No Content`

### 1.6 Update FCM Push Token
*   **Purpose**: Associate a Firebase push notification token with the session.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `PUT`
*   **Full Path**: `/api/v1/auth/device/push-token`
*   **Request Body**:
    ```json
    {
      "token": "fcm-device-registration-token-string",
      "platform": "web"
    }
    ```
*   **Validation Rules**:
    *   `token`: required, string
    *   `platform`: required, string (must be one of: `android`, `ios`, `web`)
*   **Success Response**: `204 No Content`

### 1.7 School Lookup
*   **Purpose**: Resolve the school API base URL from a secure invitation token scanned/provided.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `POST`
*   **Full Path**: `/api/v1/school/lookup`
*   **Request Body**:
    ```json
    {
      "token": "encrypted-invitation-token-string"
    }
    ```
*   **Success Response (200 OK)**:
    ```json
    {
      "data": {
        "school_name": "Alpha School",
        "school_code": "alpha",
        "api_base_url": "http://alpha.edubridge.com"
      }
    }
    ```

---

## 2. Dashboard Overview & Search

### 2.1 Dashboard Summary Statistics
*   **Purpose**: Get quick summary counts of active entities.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/admin/dashboard/summary`
*   **Required Token Ability**: `app:dashboard`
*   **Success Response (200 OK)**:
    ```json
    {
      "data": {
        "teachers": 12,
        "parents": 32,
        "students": 95,
        "sections": 6
      }
    }
    ```

### 2.2 Global Search
*   **Purpose**: Unified search across teachers, parents, and students.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/admin/search`
*   **Query Parameters**:
    *   `q` (required, string, min: 2, max: 80)
    *   `type` (optional, default: `all`, enum: `all`, `teachers`, `parents`, `students`)
    *   `per_page` (optional, default: 25)
*   **Success Response (200 OK)**:
    ```json
    {
      "data": [
        {
          "type": "student",
          "id": "1",
          "label": "John Doe",
          "secondary": "ADM-0012"
        }
      ],
      "meta": {
        "per_page": 25,
        "returned": 1
      }
    }
    ```

---

## 3. Academic Structure

### 3.1 Get Academic Structure
*   **Purpose**: Fetch all academic years, terms, grade levels, sections, and subjects in one call.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/academic/structure`
*   **Success Response (200 OK)**:
    ```json
    {
      "data": {
        "academic_years": [
          {
            "id": "1",
            "name": "2025/2026",
            "starts_on": "2025-09-01",
            "ends_on": "2026-06-30",
            "status": "active",
            "terms": []
          }
        ],
        "grade_levels": [],
        "subjects": []
      }
    }
    ```

### 3.2 Create Academic Year
*   **Purpose**: Add a new academic year.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `POST`
*   **Full Path**: `/api/v1/academic-years`
*   **Request Body**:
    ```json
    {
      "name": "2026/2027",
      "starts_on": "2026-09-01",
      "ends_on": "2027-06-30"
    }
    ```
*   **Success Response (201 Created)**:
    ```json
    {
      "data": {
        "id": "2",
        "name": "2026/2027",
        "starts_on": "2026-09-01",
        "ends_on": "2027-06-30",
        "status": "pending",
        "terms": []
      }
    }
    ```

### 3.3 Create Subject
*   **Purpose**: Add a new subject to the school curriculum.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `POST`
*   **Full Path**: `/api/v1/subjects`
*   **Request Body**:
    ```json
    {
      "code": "MATH-101",
      "name": "Mathematics 101"
    }
    ```
*   **Success Response (201 Created)**:
    ```json
    {
      "data": {
        "id": "1",
        "code": "MATH-101",
        "name": "Mathematics 101"
      }
    }
    ```

### 3.4 Manage Teacher/Subject Allocations
*   **Purpose**: Allocate a teacher to a section for a specific subject.
*   **Endpoints**:
    *   `GET /api/v1/academic/allocations`
    *   `POST /api/v1/academic/allocations`
    *   `PATCH /api/v1/academic/allocations/{allocation}`
    *   `DELETE /api/v1/academic/allocations/{allocation}`
*   **Request Body (POST)**:
    ```json
    {
      "teacher_id": 1,
      "section_id": 2,
      "subject_id": 3
    }
    ```
*   **Success Response (201 Created)**:
    ```json
    {
      "data": {
        "id": "1",
        "teacher_id": "1",
        "section_id": "2",
        "subject_id": "3"
      }
    }
    ```

---

## 4. User Profiles (Teachers, Parents, Students)

### 4.1 Create Student
*   **Purpose**: Register a new student.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `POST`
*   **Full Path**: `/api/v1/students`
*   **Request Body**:
    ```json
    {
      "central_user_id": 2,
      "admission_number": "ST-9008",
      "full_name": "Alexander Connor",
      "date_of_birth": "2015-05-12",
      "gender": "male",
      "grade_level_id": 1,
      "section_id": 1
    }
    ```
*   **Success Response (201 Created)**:
    ```json
    {
      "data": {
        "id": "1",
        "admission_number": "ST-9008",
        "full_name": "Alexander Connor",
        "grade_level_id": "1",
        "section_id": "1"
      }
    }
    ```

### 4.2 List Students
*   **Purpose**: Retrieve all students (paginated).
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/students`
*   **Success Response (200 OK)**:
    ```json
    {
      "data": [
        {
          "id": "1",
          "admission_number": "ST-9008",
          "full_name": "Alexander Connor",
          "grade_level_id": "1",
          "section_id": "1"
        }
      ],
      "meta": {
        "current_page": 1,
        "per_page": 25,
        "total": 1
      }
    }
    ```

---

## 5. Operations & Workflows

### 5.1 Approve Medical Excuse
*   **Purpose**: Approve a medical excuse uploaded by a parent.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `POST`
*   **Full Path**: `/api/v1/medical-excuses/{excuse}/approve`
*   **Success Response (200 OK)**:
    ```json
    {
      "data": {
        "id": "1",
        "status": "approved"
      }
    }
    ```

### 5.2 Approve Grade Appeal
*   **Purpose**: Approve an appeal on student grade entries.
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `POST`
*   **Full Path**: `/api/v1/grade-appeals/{appeal}/approve`
*   **Success Response (200 OK)**:
    ```json
    {
      "data": {
        "id": "1",
        "status": "approved"
      }
    }
    ```

---

## 6. Communications

### 6.1 List Conversations
*   **Purpose**: List message threads the logged-in admin belongs to.
*   **Implementation status**: `IMPLEMENTED` (Shared with teacher/parent/student)
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/conversations`
*   **Success Response (200 OK)**:
    ```json
    {
      "data": []
    }
    ```

---

## 7. Finance & Billing

### 7.1 Summary
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/dashboard/finance/summary`
*   **Required Permission**: `finance.view`

### 7.2 Invoices
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/finance/invoices`
    *   `POST /api/v1/dashboard/finance/invoices`
    *   `GET /api/v1/dashboard/finance/invoices/{invoice}`
    *   `PATCH /api/v1/dashboard/finance/invoices/{invoice}`
    *   `DELETE /api/v1/dashboard/finance/invoices/{invoice}`
*   **Notes**: delete cancels the invoice; it does not hard-delete financial records.

### 7.3 Payments
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/finance/payments`
    *   `POST /api/v1/dashboard/finance/payments`
    *   `GET /api/v1/dashboard/finance/payments/{payment}`
*   **Required Permission for create**: `finance.payments.record`

### 7.4 Discounts and Reports
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET|POST /api/v1/dashboard/finance/discounts`
    *   `PATCH|DELETE /api/v1/dashboard/finance/discounts/{discount}`
    *   `GET /api/v1/dashboard/finance/reports/collections`
    *   `GET /api/v1/dashboard/finance/reports/outstanding`
    *   `GET /api/v1/dashboard/finance/reports/student-statement/{student}`

---

## 8. Dashboard Transport

### 8.1 Summary and Routes
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/transport/summary`
    *   `GET /api/v1/dashboard/transport/routes`
    *   `GET /api/v1/dashboard/transport/routes/{route}`
*   **Required Permission**: `transport.view`

### 8.2 Passengers and Events
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/transport/routes/{route}/passengers`
    *   `GET /api/v1/dashboard/transport/routes/{route}/events`

### 8.3 Delay Alert and Driver Contact Log
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `POST /api/v1/dashboard/transport/routes/{route}/delay-alert`
    *   `POST /api/v1/dashboard/transport/routes/{route}/contact-driver-log`
*   **Notes**: delay alerts notify active route parents and write audit logs; driver contact attempts are logged for operational traceability.

---

## 9. School Settings & Integrations

### 9.1 School Settings
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/school/settings`
    *   `PATCH /api/v1/dashboard/school/settings`
*   **Required Permissions**: `settings.view`, `settings.manage`

### 9.2 Integrations
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/school/integrations`
    *   `PATCH /api/v1/dashboard/school/integrations/{integration}`
    *   `POST /api/v1/dashboard/school/integrations/{integration}/test`
*   **Security**: responses return `masked_api_key` only. Raw API keys/passwords/tokens/secrets are not returned and are not stored in tenant config JSON.

---

## 10. Audit Logs

### 10.1 List Audit Logs
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/dashboard/audit-logs`
*   **Required Permission**: `audit.view`
*   **Query Parameters**: `actor_id`, `action`, `entity_type`, `entity_id`, `from`, `to`, `page`, `per_page`
*   **Notes**: returns paginated, tenant-scoped audit records with central actor details resolved safely outside tenant joins.

### 10.2 Show Audit Log
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/dashboard/audit-logs/{auditLog}`
*   **Required Permission**: `audit.view`
*   **Security**: sensitive payload keys such as passwords, tokens, API keys, secrets, full national IDs, and card data are redacted before response.

---

## 11. RBAC & Admin Accounts

### 11.1 Roles
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/rbac/roles`
    *   `POST /api/v1/dashboard/rbac/roles`
*   **Required Permissions**: `rbac.view`, `rbac.manage`

### 11.2 Permissions and Matrix
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/rbac/permissions`
    *   `GET /api/v1/dashboard/rbac/matrix`
    *   `PATCH /api/v1/dashboard/rbac/matrix`
*   **Notes**: matrix updates replace permissions only for the submitted roles and reject unknown role/permission keys.

### 11.3 Admin Accounts
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/admin-accounts`
    *   `POST /api/v1/dashboard/admin-accounts`
    *   `PATCH /api/v1/dashboard/admin-accounts/{account}/role`
    *   `PATCH /api/v1/dashboard/admin-accounts/{account}/status`
*   **Notes**: dashboard admin roles are limited to roles allowed by the dashboard login matrix; status changes are scoped to the active school membership.

---

## 12. Broadcasts

### 12.1 List/Create Broadcasts
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/broadcasts`
    *   `POST /api/v1/dashboard/broadcasts`
*   **Required Permissions**: `broadcasts.view`, `broadcasts.send`, `broadcasts.schedule`
*   **Target Types**: `all`, `grade_level`, `section`, `students`, `parents`, `teachers`, `roles`, `custom_users`

### 12.2 Broadcast Lifecycle
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/broadcasts/{broadcast}`
    *   `POST /api/v1/dashboard/broadcasts/{broadcast}/send`
    *   `POST /api/v1/dashboard/broadcasts/{broadcast}/cancel`
    *   `GET /api/v1/dashboard/broadcasts/{broadcast}/deliveries`
*   **Notes**: delivery tracking reports queued/sent/failed/read counts from notification deliveries when the broadcast has been sent.

---

## 13. Dashboard Behavior Notes

### 13.1 List Behavior Notes
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/dashboard/behavior-notes`
*   **Required Permission**: `behavior.view`
*   **Query Parameters**: `status`, `severity`, `student_id`, `section_id`, `from`, `to`, `page`, `per_page`
*   **Notes**: returns live backend ids plus `available_actions` so the dashboard can call existing publish/recommendations/resolve endpoints without local-only ids.

---

## 14. Dashboard Leave Permits

### 14.1 List Leave Permits
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/dashboard/leave-permits`
*   **Required Permission**: `operations.leave_review`
*   **Query Parameters**: `status`, `student_id`, `section_id`, `from`, `to`, `page`, `per_page`
*   **Notes**: returns live backend ids plus `available_actions` for existing approve/reject/use-token endpoints.

---

## 15. Dashboard Schedules

### 15.1 List Schedules
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `GET`
*   **Full Path**: `/api/v1/dashboard/schedules`
*   **Required Permission**: `schedule.view`
*   **Query Parameters**: `academic_term_id`, `section_id`, `teacher_id`, `weekday`, `from`, `to`, `page`, `per_page`
*   **Notes**: returns `schedule_slot_id`, `allocation_id`, and nested `sessions` with live `teaching_session` ids.

### 15.2 Check Schedule Conflicts
*   **Implementation status**: `IMPLEMENTED`
*   **HTTP Method**: `POST`
*   **Full Path**: `/api/v1/dashboard/schedules/conflicts/check`
*   **Required Permission**: `schedule.manage`
*   **Notes**: returns `has_conflict` plus conflicting slot details before the frontend attempts create/update.

---

## 16. Dashboard Calendar Events

### 16.1 Calendar Events CRUD
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/calendar/events`
    *   `POST /api/v1/dashboard/calendar/events`
    *   `GET /api/v1/dashboard/calendar/events/{event}`
    *   `PATCH /api/v1/dashboard/calendar/events/{event}`
    *   `DELETE /api/v1/dashboard/calendar/events/{event}`
*   **Required Permissions**: `schedule.view`, `schedule.manage`
*   **Notes**: delete cancels the event; it does not hard-delete calendar records.

---

## 17. Dashboard Assessments

### 17.1 Assessment Approval Reads
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/assessments`
    *   `GET /api/v1/dashboard/assessments/{assessment}`
*   **Required Permission**: `grade.view`
*   **Query Parameters**: `status`, `academic_term_id`, `teacher_id`, `section_id`, `subject_id`, `type`, `from`, `to`, `page`, `per_page`
*   **Notes**: list returns live assessment ids, teacher/section/subject display fields, grade summaries, and `available_actions`; show also returns the section roster with grade entries for approval screens.

---

## 18. Dashboard Canvas Configs

### 18.1 Canvas Persistence
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `GET /api/v1/dashboard/canvas-configs/{key}`
    *   `PUT /api/v1/dashboard/canvas-configs/{key}`
*   **Required Permissions**: `settings.view` for read, `settings.manage` for save
*   **Notes**: `GET` returns an empty state when the key has not been saved yet; `PUT` upserts tenant-scoped JSON payloads and supports optional `expected_version` conflict protection.

---

## 19. Dashboard Transport Write Management

### 19.1 Route Management
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `POST /api/v1/dashboard/transport/routes`
    *   `PATCH /api/v1/dashboard/transport/routes/{route}`
    *   `DELETE /api/v1/dashboard/transport/routes/{route}`
*   **Required Permission**: `transport.manage`
*   **Notes**: delete archives the route and archives its active assignments; it does not hard-delete transport records.

### 19.2 Route Assignments
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `POST /api/v1/dashboard/transport/routes/{route}/assignments`
    *   `PATCH /api/v1/dashboard/transport/routes/{route}/assignments/{assignment}`
    *   `DELETE /api/v1/dashboard/transport/routes/{route}/assignments/{assignment}`
*   **Required Permission**: `transport.manage`
*   **Notes**: assignment writes enforce active route state, route capacity, and no overlapping active assignment for the same student.

---

## 20. Dashboard Grade Editing

### 20.1 Admin Grade Corrections
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoint**: `PUT /api/v1/dashboard/assessments/{assessment}/grades`
*   **Required Permission**: `grade.approve`
*   **Payload**: `entries[]` with `student_id`, optional `score`, optional `feedback` or `note`, and optional `revision`
*   **Notes**: edits are blocked after grades are published to parents or locked; score cannot exceed the assessment `max_score`; stale `revision` returns conflict.

---

## 21. Dashboard Grade Exports

### 21.1 Assessment Grade Sheet Export
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `POST /api/v1/dashboard/assessments/{assessment}/exports`
    *   `GET /api/v1/dashboard/reports/exports/{export}`
*   **Required Permission**: `grade.view`
*   **Notes**: export requests create a tenant `report_exports` row with `status=queued` and enqueue `report.grade_sheet_export_requested` through outbox; `download_url` remains `null` until an export worker attaches the generated file.

---

## 22. Dashboard Finance Refunds

### 22.1 Payment Refunds
*   **Implementation status**: `IMPLEMENTED`
*   **Endpoints**:
    *   `POST /api/v1/dashboard/finance/payments/{payment}/refunds`
    *   `GET /api/v1/dashboard/finance/refunds`
*   **Required Permissions**: `payment.refund` for create, `finance.view` for list
*   **Notes**: refunds are immutable `finance_refunds` rows linked to `finance_payments`; creating a refund decreases invoice `paid_total`, recalculates invoice status, prevents over-refund, and replays duplicate `reference` safely.
