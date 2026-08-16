# المعمارية المعتمدة

## 1. النمط

نستخدم **Modular Monolith** بنشر واحد، مع **Database-per-Tenant**. كل المجال داخل التطبيق له models وuse cases وسياسات وأحداث خاصة به؛ توجد قاعدة مركزية صغيرة للمدارس والهوية والتوجيه، وقاعدة مستقلة لكل مدرسة للبيانات التشغيلية. الفصل المنطقي قابل للاستخراج لاحقًا من دون تحويل النظام إلى microservices مبكرًا.

## 2. اتجاه الاعتماد

```text
HTTP/Console
    -> FormRequest + Policy
    -> Application Action / Query
    -> Domain Models + Rules
    -> Infrastructure (DB, Queue, Storage, Providers)
    -> API Resource / Response
```

- Controller ينسق فقط ولا يحتوي business rules.
- Action يمثل use case واحدًا مثل `SubmitAttendance`.
- Query object يستخدم للاستعلامات المركبة والتقارير.
- Eloquent هو persistence الافتراضي؛ لا ننشئ Repository interface لكل Model. يستخدم adapter/interface عند حدود مزود خارجي أو عند وجود بديل حقيقي.
- Model لا يصبح “God object”. invariants الصغيرة قريبة منه، وworkflow داخل Actions/Services مسماة.

## 3. هيكل Laravel المقترح

```text
app/
  Domain/
    Identity/
    Schools/
    Academic/
    People/
    Scheduling/
    Attendance/
    Assignments/
    Behavior/
    Messaging/
    Assessment/
    Transport/
    Wallet/
    Payments/
    Operations/
    Notifications/
    Files/
    Reporting/
    Shared/
      Actions/ Data/ Enums/ Exceptions/ ValueObjects/
  Http/
    Controllers/Api/V1/{Admin,Teacher,Parent,Integration}/
    Requests/Api/V1/
    Resources/Api/V1/
  Infrastructure/
    Payments/ Push/ Sms/ Storage/ Maps/
routes/
  api.php
  api/admin.php
  api/teacher.php
  api/parent.php
  api/integration.php
tests/
  Feature/Api/V1/
  Unit/Domain/
openapi/
  openapi.yaml
```

يمكن لكل domain تنظيم `Actions`, `Models`, `Policies`, `Events`, `Listeners`, `Jobs`, `Queries`, `Enums`, و`Data` حسب الحاجة؛ لا تنشئ مجلدًا فارغًا لمجرد مطابقة الرسم.

## 4. حدود المجالات

- `Identity`: المستخدم، الجلسة/token، الدور والصلاحية والجهاز.
- `Schools`: المدرسة، إعداداتها، التقويم والمنطقة الزمنية.
- `Academic`: العام والترم والمرحلة والشعبة والمادة.
- `People`: ملفات المعلم والطالب وولي الأمر وروابط الأسرة.
- `Scheduling`: الجدول والحصة الفعلية والبديل.
- `Attendance`: الرصد والمسودة والعذر والإنذار.
- `Assignments`: الواجب والمرفق والتسليم والنشر.
- `Behavior`: الملاحظة، الاعتماد، الإقرار، التوصية، timeline.
- `Messaging`: threads/messages والتعاميم، مع authorization مستقل.
- `Assessment`: التقييم والدرجات والاعتماد والنشر والاستئناف.
- `Operations`: إذن الخروج والاستدعاء والإجراءات اليومية.
- `Transport`: المسار والركاب والرحلة وGPS والتنبيه وopt-out.
- `Wallet`: ledger والحدود وQR/checkout token.
- `Payments`: sessions, provider callbacks, reconciliation, refunds.
- `Notifications`: إشعار منطقي وتسليم متعدد القنوات.
- `Reporting`: read models وتجميعات فقط؛ لا يملك الحقيقة الأصلية.

## 4.1 حدود الـTenancy وقواعد البيانات

- `central`: المدارس، النطاقات، حالة الاشتراك، الهوية العامة، العضويات، ومراجع secrets/اتصالات tenant المشفرة.
- `tenant`: الأشخاص، الهيكل الأكاديمي، الحضور، الدرجات، السلوك، النقل، المال، الرسائل وaudit الخاص بالمدرسة.
- `TenantResolver` يحدد المدرسة من domain/app context وعضوية المستخدم الموثقة، ثم `TenantConnectionManager` يفتح اتصال المدرسة قبل route model binding.
- يمنع foreign key أو join بين `central` و`tenant` أو بين tenant وآخر. تنقل المعرفات المركزية كقيم موثقة عند الحاجة.
- queues تحمل `tenant_id` فقط، وتعيد تهيئة الاتصال قبل تنفيذ الـJob ثم تنظفه في `finally` لمنع تسرب الاتصال في workers طويلة العمر.
- cache keys وfilesystem paths وlocks وrate-limit buckets تبدأ بـtenant identifier.
- migrations تنقسم إلى `database/migrations/central` و`database/migrations/tenant`، وتطبق tenant migrations على كل مدرسة مع tracking وfailure isolation.

## 5. التواصل بين المجالات

- الاستدعاء المباشر لـAction مسموح داخل نفس العملية عندما يلزم رد فوري.
- الأحداث تستخدم للآثار الجانبية: إشعار بعد الغياب، نشر درجة، إنشاء audit.
- الأحداث/Jobs التي تعتمد على بيانات committed تستخدم after-commit.
- integration provider خلف interface خاص بالبنية، مع fake للاختبارات.
- يمنع استدعاء Controller من Controller أو قراءة Request داخل Domain.

## 6. الاتساق والتزامن

- transaction قصيرة داخل قاعدة واحدة وحول invariant واحد؛ لا distributed transaction بين central وtenant.
- `lockForUpdate()` أو atomic lock عند خصم المحفظة، اعتماد نفس الطلب، وتوليد مورد وحيد.
- `Idempotency-Key` للمدفوعات والخصم وbulk submit القابل لإعادة الإرسال.
- unique constraints هي خط الدفاع النهائي، وليست validation فقط.
- يستخدم outbox pattern قبل production لأي حدث خارجي لا يجوز فقده بعد commit.

## 7. قرارات ممنوعة مبكرًا

- microservices، event sourcing شامل، CQRS شامل، أو Kubernetes من دون متطلبات قياس واضحة.
- generic BaseRepository/BaseService أو helpers غير محددة المجال.
- observers لعمليات business حرجة مخفية.
- JSON لأعمدة يجب البحث أو الربط عليها؛ JSON للmetadata المتغيرة فقط.
