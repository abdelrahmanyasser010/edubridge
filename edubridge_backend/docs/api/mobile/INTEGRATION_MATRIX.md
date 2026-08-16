# Mobile Integration Matrix

| App / Screen | Backend contract | Status |
|---|---|---|
| Parent onboarding/login/session | school lookup + parent login + auth lifecycle | READY IN CODE |
| Parent Home | `/parent/students`, `/overview` | READY IN CODE |
| Parent Profile | `/parent/profile` GET/PATCH | READY IN CODE |
| Parent Attendance | existing parent attendance | READY IN CODE |
| Parent Assignments | existing assignment/submission/download | READY IN CODE |
| Parent Behavior | list + acknowledge | READY IN CODE |
| Parent Grade reports | recent + terms + term detail + certificate + appeal | READY IN CODE |
| Parent Activities | list/detail/register/cancel + paid invoice handoff | READY IN CODE |
| Parent Support | shared support ticket lifecycle | READY IN CODE |
| Parent Finance | summary/invoices/payment status/receipt | READY IN CODE |
| Parent Wallet | balance/transactions/top-up/server QR token | READY IN CODE |
| Parent Transport | rich live status + opt-out | READY IN CODE |
| Notifications | list/read/read-all/preferences | READY IN CODE |
| Chat | shared conversations | READY IN CODE |
| Teacher Home | summary | READY IN CODE |
| Teacher My Classes | classes/detail/students | READY IN CODE |
| Teacher Schedule | daily schedule with session IDs | READY IN CODE |
| Teacher Attendance | existing roster/draft/submit | READY IN CODE |
| Teacher Assignments | existing CRUD/publish | READY IN CODE |
| Teacher Grades | assessment list/create/roster/save/submit + gradebook | READY IN CODE |
| Teacher Behavior | existing create | READY IN CODE |
| Teacher Substitutions | existing list/accept/decline | READY IN CODE |
| Student app beyond assignments | UI not supplied | NEEDS UI REVIEW |
| Driver/Transport read/current-trip app | UI not supplied | NEEDS UI REVIEW |
| Live ETA calculation | no real route estimator wired to parent payload | RETURNS NULL, NO FAKE ETA |
| Provider refund automatic ledger reconciliation | deliberately not automatic | ADMIN RECONCILIATION REQUIRED |

`READY IN CODE` means route/controller/action/schema has been added or already existed. See `VERIFICATION.md`: the supplied archive lacks the root Laravel runtime, so full `artisan test`/migration execution could not be performed in this delivery sandbox.
