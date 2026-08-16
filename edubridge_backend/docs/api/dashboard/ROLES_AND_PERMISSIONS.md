# Dashboard Roles and Permissions Matrix

This document maps dashboard roles to their active permissions inside the tenant system, extracted from `SystemRoleCatalog` and `ApplicationAccessMatrix`.

---

## 1. Dashboard Application Access
Only users with one of the following roles are authorized to log in through the Dashboard login endpoint (`/api/v1/dashboard/auth/login`). Any other role (such as `teacher` or `parent`) will trigger `403 APP_ACCESS_DENIED`.

*   `school_admin`
*   `academic_admin`
*   `student_affairs`
*   `finance_officer`

---

## 2. Permission Matrix (by Role)

| Permission Key | school_admin | academic_admin | student_affairs | finance_officer |
| :--- | :---: | :---: | :---: | :---: |
| **School Profile (`school.*`)** | ✅ | ❌ | ❌ | ❌ |
| **RBAC Config (`rbac.*`)** | ✅ | ❌ | ❌ | ❌ |
| **Academic Years & Terms (`academic.*`)** | ✅ | ✅ | ❌ | ❌ |
| **Manage Profiles (`people.manage`)** | ✅ | ❌ | ❌ | ❌ |
| **View Profiles (`people.view`)** | ✅ | ✅ | ✅ | ❌ |
| **Schedules & Sessions (`schedule.*`)** | ✅ | ✅ | ❌ | ❌ |
| **Grade Management (`grade.*`)** | ✅ | ✅ | ❌ | ❌ |
| **View Attendance (`attendance.view`)** | ✅ | ❌ | ✅ | ❌ |
| **Amend Attendance (`attendance.amend`)** | ✅ | ❌ | ✅ | ❌ |
| **Approve Attendance Excuse (`attendance.review_excuse`)** | ✅ | ❌ | ✅ | ❌ |
| **Behavior Notes Management (`behavior.*`)** | ✅ | ❌ | ✅ | ❌ |
| **Approve Leave Permits (`operations.leave_review`)** | ✅ | ❌ | ✅ | ❌ |
| **Parent Summons (`operations.summons_manage`)** | ✅ | ❌ | ✅ | ❌ |
| **Canteen & Wallet Settings (`wallet.*`)** | ✅ | ❌ | ❌ | ✅ |
| **Payments & Refunds (`payment.*`)** | ✅ | ❌ | ❌ | ✅ |
| **General Reports (`report.view`)** | ✅ | ✅ | ❌ | ✅ |
| **Export Finance Reports (`report.export`)** | ✅ | ❌ | ❌ | ✅ |
| **Audit Logs (`audit.view`)** | ✅ | ❌ | ❌ | ❌ |

---

## 3. Discrepancies & Backend Implementation Gaps

> [!WARNING]
> While `SystemRoleCatalog` contains permissions for `finance_officer` (such as `payment.*` and `wallet.*`), **there are currently no active routes or controllers in the codebase** for financial transactions (e.g. fees, invoicing, wallet management). These routes are planned for future vertical slices and must be mocked on the frontend for now.
