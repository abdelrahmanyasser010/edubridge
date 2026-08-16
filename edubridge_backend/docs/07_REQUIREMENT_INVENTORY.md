# EduBridge Requirement Inventory

الحالة: `Foundation inventory complete`  
آخر مراجعة: 2026-07-13  
Task: `FND-010`

## طريقة القراءة

هذه الوثيقة لا تستبدل `openapi/openapi.yaml` ولا تنشئ endpoints جديدة. هي خريطة نقل للمتطلبات القديمة إلى خطة التنفيذ الحالية.

- `Task` يعني أن المتطلب مغطى في التاسكات الحالية أو تم إنجازه.
- `OUT-OF-SCOPE` يعني أن المتطلب غير داخل backend v1 الحالي أو يحتاج قرار منتج/مزود/ADR قبل التنفيذ.
- عند وجود تعارض في الاسم أو الـworkflow، القرار المعتمد في `docs/00_MASTER_PLAN.md` وADRs هو الحاكم.

المصادر القديمة:

- `docs/edubridge_full_system_specs.md`
- `docs/apis doc dashboard COMPLETE.md`
- `docs/apis doc teacher app.md`
- `docs/apis doc parent app.md`

## Inventory

| ID | Source | Requirement | Domain | Disposition |
|---|---|---|---|---|
| REQ-001 | dashboard, teacher, parent, full specs | Login, logout, current profile/me, device-scoped sessions | Identity | DONE: FND-005; OpenAPI baseline in FND-009 |
| REQ-002 | teacher, parent | Refresh-token endpoints | Identity | OUT-OF-SCOPE: replaced by Sanctum per-device token lifecycle; no custom refresh token without new ADR |
| REQ-003 | dashboard | Demo login | Identity | OUT-OF-SCOPE: demo credentials are not production backend behavior; use seed/dev fixtures only if a future demo task is approved |
| REQ-004 | dashboard | Switch role from dashboard | Identity/RBAC | OUT-OF-SCOPE: active membership + permissions are server evaluated; role switching UX needs a product decision and audit semantics |
| REQ-005 | dashboard, teacher, parent | User profile, language preference, avatar upload | Identity/Files | CORE-002 for profile data, FND-008 for files; language handled through API locale until profile preference task |
| REQ-006 | dashboard | RBAC matrix, admin accounts, permissions management | Identity/RBAC | FND-006 foundation; future school admin management remains covered by `identity.*`/`rbac.*` work after core profiles |
| REQ-007 | dashboard, master plan | Multi-school isolation and school-scoped data | Schools/Tenancy | DONE: DEC-002, FND-004 |
| REQ-008 | dashboard | School profile/settings | Schools | CORE-001/CORE-002 for operational school data; secrets/integration settings deferred to provider-specific tasks |
| REQ-009 | dashboard, teacher | Academic years, terms, grade levels, sections, subjects | Academic | CORE-001 |
| REQ-010 | dashboard, teacher | Use `classes` endpoints and `class_id` naming | Academic | OUT-OF-SCOPE as written: normalized to `sections` and `section_id` by master plan |
| REQ-011 | dashboard | Configurator bootstrap wizard for sections, teachers, students, bus routes | Academic/People/Transport | CORE-001, CORE-002, TRN-001; visual orchestration UI is not a separate backend domain in v1 |
| REQ-012 | dashboard | Visual configurator canvas, auto-layout, nodes, connections, snapshots | Operations | OUT-OF-SCOPE: dashboard visualization/editor feature needs product confirmation; not required for core backend data integrity |
| REQ-013 | dashboard, teacher | Teachers CRUD and teacher profiles | People | CORE-002 |
| REQ-014 | dashboard, parent | Parents CRUD/profile and guardian data | People | CORE-002 |
| REQ-015 | dashboard, parent, full specs | Students CRUD/profile and child selector | People | CORE-002, CORE-003 |
| REQ-016 | dashboard, parent | Student-parent/family linking, pickup permissions, primary guardian | People | CORE-003 |
| REQ-017 | dashboard | Import students from Excel | People/Imports | OUT-OF-SCOPE: requires import validation, async job, file retention, and rollback design; add future import task after CORE-002 |
| REQ-018 | dashboard, teacher | Teacher-section-subject allocations and quotas | Academic/Scheduling | CORE-004 |
| REQ-019 | dashboard, teacher, full specs | Schedule slots, today schedule, current session, session details | Scheduling | CORE-005 |
| REQ-020 | dashboard | Schedule conflict checks, publish, auto-generate | Scheduling | CORE-005 for manual/publish basics; auto-generate is OUT-OF-SCOPE until algorithm/constraints are approved |
| REQ-021 | dashboard | Teacher substitutions | Operations/Scheduling | OPS-003 |
| REQ-022 | teacher, full specs | Teacher attendance roster for session | Attendance | ATT-001 |
| REQ-023 | teacher, dashboard, full specs | Draft attendance and bulk submit attendance | Attendance | ATT-001, ATT-002 |
| REQ-024 | dashboard | Attendance amend/review/lock and warnings | Attendance | ATT-002 for submit base; warnings/review behavior belongs ATT-003/ATT-004 and future student-affairs slice |
| REQ-025 | parent, full specs | Parent attendance history and summaries | Attendance | ATT-003 |
| REQ-026 | parent, dashboard | Medical excuse upload, review, approve/reject, update attendance | Attendance/Files | ATT-004, FND-008 |
| REQ-027 | dashboard | Sehatty verification for medical excuses | Attendance/Integrations | OUT-OF-SCOPE: adapter disabled until official contract/credentials; DEC-005 says no real claim before provider approval |
| REQ-028 | parent, dashboard | Leave permit request, approve/reject, one-time gate token | Operations | OPS-001 |
| REQ-029 | parent | Gate pass QR/token raw storage | Operations/Security | OPS-001 but raw token storage is rejected; hash-only by docs/05_SECURITY_OPERATIONS.md and ADR-005 |
| REQ-030 | teacher, parent, full specs | Assignment create/update/list/detail/archive/publish | Assignments | ASN-001 |
| REQ-031 | teacher, parent | Assignment attachments and downloads | Assignments/Files | ASN-001, ASN-002, FND-008 |
| REQ-032 | parent | Student assignment submissions | Assignments | ASN-002 |
| REQ-033 | teacher | Assignment notify action | Assignments/Notifications | ASN-001 + NTF-001/NTF-002; notification side effect after commit only |
| REQ-034 | teacher, dashboard, parent, full specs | Behavior notes create/review/publish/acknowledge/resolve | Behavior | BEH-001, BEH-002 |
| REQ-035 | dashboard, teacher | `behavior_records` naming and arbitrary status patch | Behavior | OUT-OF-SCOPE as written: normalized to `behavior_notes` and named transitions by ADR-005 |
| REQ-036 | teacher, parent | Behavior recommendations | Behavior | BEH-002 |
| REQ-037 | teacher, parent | Open conversation/chat from behavior note | Messaging | MSG-001, MSG-002 |
| REQ-038 | teacher, parent | Conversation list, messages, read receipts, close/moderation | Messaging | MSG-001, MSG-002 |
| REQ-039 | teacher | Exams API and `exam_grades` tables | Assessment | OUT-OF-SCOPE as named: normalized to `assessments` and `grade_entries`; covered by GRD-001 |
| REQ-040 | teacher, dashboard, full specs | Grade entry, sheet submit, approval, publish, lock | Assessment | GRD-001, GRD-002 |
| REQ-041 | parent | Parent grade reports/certificates and PDF export | Assessment/Files | GRD-003, FND-008 |
| REQ-042 | parent | Grade appeal/review | Assessment | GRD-004 |
| REQ-043 | dashboard, parent, full specs | Wallet balance, ledger, QR/POS payment token | Wallet | WAL-001, WAL-002 |
| REQ-044 | dashboard, parent, full specs | Fees, payment sessions, payment webhooks, receipts | Payments | PAY-001, PAY-002, PAY-003 |
| REQ-045 | dashboard | Refund and reconciliation | Payments | PAY-003 |
| REQ-046 | dashboard, parent, full specs | Bus routes, passengers, live status, tracking events, opt-out | Transport | TRN-001, TRN-002, TRN-003 |
| REQ-047 | dashboard | Contact driver and delay alert | Transport/Notifications | TRN-003 + NTF-001 |
| REQ-048 | dashboard, teacher, parent, full specs | Notifications list, read/read-all, deliveries | Notifications | NTF-001 |
| REQ-049 | teacher, dashboard | Notification event matrix for attendance/assignment/behavior | Notifications | NTF-002 and relevant domain tasks; all events after commit per docs/AGENTS.md |
| REQ-050 | dashboard | Broadcast messages, message templates, scheduled messages, resend | Operations/Notifications | OPS-004, NTF-001 |
| REQ-051 | dashboard, parent | School events/calendar and reminders | Operations/Notifications | OPS-004 |
| REQ-052 | parent | Support tickets and replies | Operations/Messaging | OPS-004 |
| REQ-053 | dashboard | Parent summons, reminders, attended/reschedule | Operations | OPS-002 |
| REQ-054 | dashboard | Executive dashboard stats, attendance breakdown, behavior feed, teacher KPIs | Reporting | RPT-001 |
| REQ-055 | dashboard | Operational reports, exports, analytics export | Reporting | RPT-001; exports must be async/private files when large |
| REQ-056 | dashboard | Early-warning analytics, health index, interventions | Analytics | ANA-001 |
| REQ-057 | dashboard | AI/opaque analytics predictions | Analytics | OUT-OF-SCOPE: ANA-001 requires explainable rules only, no opaque AI in v1 |
| REQ-058 | dashboard | Integration settings and provider test endpoints | Integrations | DEC-005 + provider-specific tasks; real tests require sandbox credentials and contract fixtures |
| REQ-059 | dashboard, teacher, parent | Files upload/delete/download | Files | FND-008 base; domain-specific attachments in ASN/MSG/GRD/PAY/OPS tasks |
| REQ-060 | legacy docs | Accept arbitrary external attachment URLs | Files/Security | OUT-OF-SCOPE: explicitly rejected by ADR-006; only uploaded/presigned trusted objects |
| REQ-061 | dashboard | Audit logs and security history | Audit | FND-007; audit views later via `audit.view`/RPT-001 as needed |
| REQ-062 | all legacy docs | Response envelopes, errors, auth headers, pagination, rate limits | API Contract | DONE: FND-003, FND-009 |
| REQ-063 | all legacy docs | Laravel 11 assumption | Platform | OUT-OF-SCOPE: DEC-001 approved Laravel 13/PHP 8.5 path |
| REQ-064 | dashboard, teacher | `sessions` table for teaching sessions | Scheduling | OUT-OF-SCOPE as named: use `teaching_sessions`; auth/session storage remains separate |
| REQ-065 | dashboard | Platform backups, observability, runbooks, security hardening | Reliability | REL-001, REL-002, REL-003 |
| REQ-066 | all legacy docs | Archive legacy docs after inventory | Documentation | REL-004 depends on FND-010 |

## Consolidated source coverage

- Full system specs mapped to `REQ-001`, `REQ-022`-`REQ-025`, `REQ-030`, `REQ-034`, `REQ-040`, `REQ-043`, `REQ-046`, `REQ-048`.
- Dashboard complete spec mapped to `REQ-001`-`REQ-066` by module groups.
- Teacher app spec mapped to `REQ-001`, `REQ-005`, `REQ-018`-`REQ-023`, `REQ-030`-`REQ-040`, `REQ-048`, `REQ-059`.
- Parent/student app spec mapped to `REQ-001`, `REQ-015`-`REQ-017`, `REQ-025`-`REQ-028`, `REQ-032`, `REQ-034`, `REQ-037`, `REQ-041`-`REQ-048`, `REQ-052`.

## Follow-up rule

عند تنفيذ أي task لاحق، يبدأ الـAgent من `openapi/openapi.yaml` وهذا inventory، ثم يضيف/يعدل عقد الـOpenAPI الخاص بالـvertical slice داخل نفس التاسك. لا تنقل endpoint قديمًا كما هو إذا خالف naming أو workflow أو tenancy/security decisions.
