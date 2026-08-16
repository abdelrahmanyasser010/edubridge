# EduBridge Backend — الخطة الرئيسية ومصدر الحقيقة

الحالة: `Approved for implementation planning`  
آخر مراجعة: 2026-07-12

## 1. القرار: وثيقة واحدة أم أربع؟

لا نستخدم ملفًا ضخمًا واحدًا، ولا نترك أربع وثائق متساوية السلطة. نعتمد **بوابة واحدة + وثائق متخصصة**:

- هذا الملف يحدد الرؤية، القرارات، الحدود، وترتيب القراءة.
- الوثائق المتخصصة تملك تفاصيل مجال واحد فقط.
- `openapi/openapi.yaml`، عند إنشائه في `FND-009`، هو مصدر الحقيقة لمسارات الـAPI والـschemas والأخطاء.
- migrations هي مصدر الحقيقة النهائي لهيكل قاعدة البيانات.
- ADRs في `docs/decisions/` تفسر القرارات المعمارية المهمة.

بهذا نمنع نسخ endpoint أو اسم جدول في أكثر من مرجع قابل للتعارض.

## 2. نتيجة تحليل الوثائق الأربع

المحتوى الحالي قوي كجمع متطلبات، لكنه غير جاهز للتنفيذ المباشر للأسباب التالية:

| الموضوع | التعارض الحالي | القرار المعتمد |
|---|---|---|
| المصادقة | JWT وrefresh token مقابل Sanctum | Sanctum لتطبيقات الطرف الأول، token لكل جهاز، وإبطال مستقل. لا refresh token مخصص قبل وجود حاجة مثبتة |
| إصدار Laravel | الوثائق تفترض Laravel 11 | مشروع جديد: Laravel 13 + PHP 8.5؛ راجع القرار قبل scaffold إن كانت بيئة الاستضافة أو الحزم لا تدعم ذلك |
| الفصل | `class_id` و`section_id` | `section_id` فقط؛ `grade_levels` للصفوف و`sections` للشعب |
| ولي الأمر/الطالب | `student_parent` و`student_family_links` و`parent_id` داخل الطالب | pivot واحد `student_parent`، ولا يوضع parent وحيد داخل `students` |
| السلوك | `behavior_records` و`behavior_notes` وحالات مختلفة | `behavior_notes`: `draft, pending_review, published, acknowledged, resolved, rejected` |
| الجلسة الدراسية | `sessions` و`teaching_sessions` | `teaching_sessions`؛ اسم `sessions` محجوز لجلسات الدخول إن استخدمت |
| الإشعارات | جداول خاصة بكل تطبيق وجداول مشتركة | `notifications`, `notification_deliveries`, `device_tokens` مشتركة |
| الاستجابة | `status=success` مقابل `success=true` وأشكال بلا envelope | عقد موحد موضح في `04_API_CONTRACT.md` |
| الحضور | يومي أحيانًا وحصة أحيانًا | سجل لكل `teaching_session + student`، والتقارير اليومية مشتقة منه |
| الدرجات | exams مقابل templates/sheets | `assessments`, `grade_entries` مع workflow نشر واضح |
| المحفظة | خصم رصيد مباشر | ledger ثابت + transaction + lock + idempotency؛ الرصيد قيمة مشتقة/مخزنة مع reconciliation |
| تعدد المدارس | غير موثق | Database-per-Tenant: قاعدة مركزية للتوجيه والهوية فقط، وقاعدة مستقلة لكل مدرسة لكل البيانات التشغيلية |

## 3. النطاق

المستهلكون:

- Dashboard للإدارة.
- Teacher mobile app.
- Parent/Student mobile app.
- نقاط بيع المقصف، تتبع الحافلات، ومزودو الدفع كتكاملات موثقة.

المجالات:

`Identity`, `Schools`, `Academic`, `People`, `Scheduling`, `Attendance`, `Assignments`, `Behavior`, `Messaging`, `Assessment`, `Transport`, `Wallet`, `Payments`, `Operations`, `Notifications`, `Files`, `Reporting`, `Audit`.

## 4. مبادئ غير قابلة للتفاوض

1. Modular Monolith بحدود مجال واضحة، وليس microservices في البداية.
2. API-first: لا يبدأ endpoint قبل تعريف عقده واختبارات قبوله.
3. كل authorization على الخادم: role/permission + ownership + school scope.
4. لا side effect خارجي داخل transaction؛ يرسل event/job بعد commit.
5. المال والدرجات والحضور بيانات حساسة لها audit trail ولا hard delete.
6. UTC في التخزين والنقل، ومنطقة المدرسة للعرض والحسابات المحلية.
7. أقل قدر من البيانات في responses وlogs، مع pagination لكل collection كبيرة.
8. كل تغيير schema عبر migration قابلة للرجوع حيثما أمكن.
9. الاختبارات جزء من التاسك وليست مرحلة لاحقة.
10. الأداء يقاس؛ لا cache أو index عشوائي بلا query/use case واضح.
11. لا توجد علاقات أو joins أو تقارير تجمع بيانات مدارس مختلفة؛ كل request/job يعمل في TenantContext واحد.

## 5. التقنية المعتمدة

- Laravel 13، PHP 8.5، Composer 2.
- MySQL 8.4 LTS، InnoDB، `utf8mb4`.
- Redis للـcache والqueues والlocks، وHorizon لمراقبة queues.
- Laravel Sanctum لمستخدمي الطرف الأول.
- Pest أو PHPUnit؛ اختر واحدًا في scaffold ولا تخلط أسلوبين داخل نفس الوحدة.
- OpenAPI 3.1 لعقد الـAPI.
- تخزين S3-compatible للملفات؛ القرص المحلي للتطوير فقط.

PHP 8.5 هو الافتراضي لمشروع جديد لطول نافذة الدعم، وLaravel 13 يتطلب PHP 8.3 على الأقل. يحسم `DEC-001` التوافق الفعلي مع الاستضافة والحزم قبل الـscaffold؛ وإذا فرضت البيئة إصدارًا أقدم مدعومًا، يسجل ADR ويظل باقي التصميم كما هو.

مراجع الاختيار الرسمية: [سياسة إصدارات Laravel 13](https://laravel.com/docs/13.x/releases)، [إصدارات PHP المدعومة](https://www.php.net/supported-versions.php)، [MySQL 8.4 Reference Manual](https://dev.mysql.com/doc/refman/8.4/en/)، و[Laravel Sanctum](https://laravel.com/docs/13.x/sanctum).

## 6. ترتيب التنفيذ

1. تثبيت القرارات والعقد الأساسي.
2. Scaffold، CI، quality gates، قاعدة البيانات، tenancy.
3. Identity/RBAC/audit/files/notifications.
4. الهيكل الأكاديمي والأشخاص والجدول.
5. الحضور والواجبات.
6. السلوك والمحادثات والعمليات.
7. التقييمات والدرجات.
8. النقل.
9. المحفظة والمدفوعات.
10. التقارير والتحليلات ثم hardening والإطلاق.

لا تنفذ Analytics قبل استقرار مصادر بياناتها، ولا Payments قبل إقرار threat model وwebhook contract.

## 7. Definition of Done العامة

أي Feature لا تعد مكتملة إلا إذا تحقق الآتي:

- عقد OpenAPI محدث ومتسق.
- migration/model/factory/seeder عند الحاجة.
- FormRequest وPolicy وAction وResource واضحون.
- Feature tests: happy path، validation، unauthenticated، forbidden، cross-school isolation.
- اختبارات race/idempotency للعمليات الحساسة.
- لا N+1 في المسار، والـindexes مرتبطة بالاستعلامات.
- audit وevents/notifications المطلوبة تعمل بعد commit.
- formatter + static analysis + test suite ناجحة.
- لا أسرار ولا PII في logs.

## 8. إدارة الوثائق القديمة

الملفات التالية لا تحذف الآن لأنها تحفظ المتطلبات الأصلية:

- `docs/legacy/edubridge_full_system_specs.md`
- `docs/legacy/apis doc dashboard COMPLETE.md`
- `docs/legacy/apis doc teacher app.md`
- `docs/legacy/apis doc parent app.md`

تستخدم فقط أثناء نقل المتطلبات إلى OpenAPI والتاسكات. بعد اكتمال `FND-009` و`FND-010` تنقل إلى `docs/legacy/` وتضاف لها علامة `Deprecated` في commit مستقل.
