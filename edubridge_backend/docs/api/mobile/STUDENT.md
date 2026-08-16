# Student Mobile API — Current Verified Backend Scope

لم يتم توفير سورس Student App مستقل ضمن تطبيق Flutter الذي تمت مراجعته، لذلك لا تضيف هذه الوثيقة APIs افتراضية.

الموجود حاليًا:

- `POST /student/auth/login`
- `GET /student/assignments`
- `POST /student/assignments/{assignment}/submissions`
- `GET /student/assignments/{assignment}/attachments/{file}/download`
- Shared notifications / conversations / support / auth lifecycle.

إذا كان Student App النهائي يحتوي attendance/grades/schedule/behavior/transport screens، يجب مراجعة سورسه أو مواصفاته قبل تعريف vertical slices جديدة.
