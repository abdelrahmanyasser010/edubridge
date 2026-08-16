# ADR-004: الأدوار والصلاحيات والملكية

- الحالة: Accepted
- التاريخ: 2026-07-12
- Task: DEC-003

## النموذج

السماح النهائي يساوي: `authenticated + active membership + permission + tenant match + resource relationship + state rule`.

الأدوار تجمع permissions للراحة فقط؛ الكود يفحص permission وPolicy ولا يكتب شروطًا متناثرة من نوع `role == admin`.

## الأدوار النظامية

| الدور | النطاق | المسؤوليات الأساسية |
|---|---|---|
| `platform_super_admin` | كل المنصة | إدارة المدارس والدعم التشغيلي المقيد والمراجع العامة |
| `school_admin` | مدرسة | إعداد المدرسة والحسابات ومنح الأدوار واعتماد العمليات |
| `academic_admin` | مدرسة | الهيكل والجدول والتقييمات والنشر الأكاديمي |
| `student_affairs` | مدرسة | الحضور والأعذار والسلوك والاستدعاءات وأذونات الخروج |
| `finance_officer` | مدرسة | الرسوم والمدفوعات والتسويات دون الاطلاع الأكاديمي غير اللازم |
| `transport_supervisor` | مدرسة | المسارات والرحلات والركاب والتنبيهات |
| `teacher` | علاقاته فقط | حصصه وطلابه وواجباته وملاحظاته ودرجات مواده |
| `parent` | أبناؤه فقط | بيانات الأبناء والطلبات والإقرارات والدفع والمراسلة المصرح بها |
| `student` | ذاته فقط | واجباته ودرجاته المنشورة وجدوله حسب سياسة المدرسة |
| `canteen_operator` | نقطة البيع | التحقق والخصم فقط، بلا كشف ملف الطالب أو الرصيد الكامل إلا للضرورة |
| `integration_client` | scopes محددة | endpoints تكاملية فقط، بلا جلسة مستخدم بشرية |

## مجموعات الصلاحيات

- `school.*`, `identity.*`, `rbac.*`
- `academic.view|manage|publish`
- `people.view|manage|export`
- `schedule.view|manage|publish`
- `attendance.view|draft|submit|amend|review_excuse`
- `assignment.view|create|update|publish|archive`
- `behavior.view|create|review|publish|acknowledge|resolve`
- `message.view|send|moderate|broadcast`
- `grade.view|enter|approve|publish|lock|appeal_review`
- `operations.leave_review|summons_manage|substitution_manage`
- `transport.view|manage|track|alert`
- `wallet.view|limit_manage|deduct`, `payment.view|collect|refund|reconcile`
- `report.view|export`, `audit.view`

تخزن permissions كقيم دقيقة. wildcard notation للتنظيم والتوثيق، ولا يعني wildcard تلقائيًا إلا إذا نفذ واختبر صراحة.

## قواعد ملكية أساسية

- المعلم: assignment/session/student/grade يجب أن يرتبط بـ`teacher_section_subject` ساري في الترم، مع استثناء بديل ساري للحصة.
- ولي الأمر: `student_parent` سارية، ونوع الإجراء يحترم flags مثل `can_pickup` وكونه primary عند الحاجة.
- الطالب: `student.user_id` يطابق المستخدم، والبيانات منشورة ومسموح إظهارها.
- موظفو المدرسة: TenantContext لنفس المدرسة وصلاحية الوحدة؛ البيانات المالية والطبية لا تمنح ضمنيًا لمجرد كونه admin أكاديميًا.
- `platform_super_admin`: الوصول العابر للمدارس عملية audited وبسبب support reason، وليس default scope.

## منح الصلاحيات

- الأدوار النظامية seeded وغير قابلة للحذف، ويمكن تخصيص نسخ مدرسية لاحقًا.
- لا يستطيع مستخدم منح permission لا يملك حق إدارتها.
- تغييرات RBAC تسجل audit وتبطل/تعيد تقييم الجلسات الحساسة.
- deny by default، ولا توجد صلاحية من `X-App-Type` أو بيانات العميل.
