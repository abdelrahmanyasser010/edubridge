# Teacher Mobile API

جميع read/discovery APIs الجديدة تقيد النتائج بالمدرس الحالي وactive teacher allocations.

## 1. Dashboard summary

`GET /teacher/dashboard/summary`

يعيد:

- teacher summary
- today classes count
- pending attendance count
- draft assignments count
- pending grading count
- pending substitutions count
- unread notifications count

## 2. My Classes

`GET /teacher/classes?academic_term_id=&page=1&per_page=25`

كل row يحتوي allocation + section + grade level + subject + term + students count.

`GET /teacher/classes/{section}`

يعيد تفاصيل section والـ allocations التي يملكها المدرس داخلها.

`GET /teacher/classes/{section}/students?per_page=50`

يعيد طلاب الفصل فقط بعد التحقق أن المدرس لديه active allocation في الفصل.

## 3. Schedule / attendance session discovery

`GET /teacher/schedule?date=YYYY-MM-DD`

يعيد `session_id` المطلوب لشاشة الحضور مع section/subject/time/room/status.

Attendance workflow الحالي:

- `GET /teacher/attendance/sessions/{session}/roster`
- `PUT /teacher/attendance/sessions/{session}/draft`
- `POST /teacher/attendance/sessions/{session}/submit`

## 4. Assignments

- `GET /teacher/assignments`
- `POST /teacher/assignments`
- `PATCH /teacher/assignments/{assignment}`
- `DELETE /teacher/assignments/{assignment}`
- `POST /teacher/assignments/{assignment}/publish`

## 5. Assessments / grades

### List
`GET /teacher/assessments?status=&allocation_id=&academic_term_id=&page=1&per_page=25`

### Create/workflow
- `POST /teacher/assessments`
- `GET /teacher/assessments/{assessment}/roster`
- `PUT /teacher/assessments/{assessment}/grades`
- `POST /teacher/assessments/{assessment}/submit`

### Gradebook
`GET /teacher/classes/{section}/gradebook`

يعيد assessments وطلاب الفصل والـ grade-entry ids/scores/feedback/revision.

## 6. Behavior

`POST /teacher/behavior-notes`

## 7. Substitutions

- `GET /teacher/substitutions`
- `POST /teacher/substitutions/{substitution}/accept`
- `POST /teacher/substitutions/{substitution}/decline`

## 8. Messaging / notifications / support

استخدم Shared APIs في `SHARED.md`.
