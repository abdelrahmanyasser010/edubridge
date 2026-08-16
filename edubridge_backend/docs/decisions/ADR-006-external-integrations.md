# ADR-006: مزودو التكامل وحدودهم

- الحالة: Accepted
- التاريخ: 2026-07-12
- Task: DEC-005

## المزودون الافتراضيون

| القدرة | المزود الأول | التطوير/الاختبار |
|---|---|---|
| Push | Firebase Cloud Messaging HTTP v1 | `FakePushGateway` يسجل الرسائل دون شبكة |
| SMS | Unifonic SMS API | `FakeSmsGateway` وlog منقح بلا أرقام كاملة |
| الملفات | Amazon S3 private bucket عبر Laravel Filesystem | local private disk + fake storage |
| الدفع | Moyasar Payments API | fake provider + sandbox عند توفر credentials |
| الخرائط/ETA | Google Maps Platform Routes API | fake ثابت؛ GPS ingestion لا يعتمد على الخريطة |
| البريد | SMTP/SES adapter بحسب الاستضافة | array/log mailer |

تكاملات Sehatty وNoor تبنى كـports معطلة افتراضيًا ولا تدعي تحققًا حقيقيًا قبل توفير عقد رسمي وcredentials واعتماد الجهة.

## العقود البرمجية

توجد interfaces في `Infrastructure` مثل `PushGateway`, `SmsGateway`, `PaymentGateway`, `RouteEstimator`. الـDomain لا يستورد SDK أو response class لمزود.

كل adapter:

- يحول request/response إلى DTOs داخلية.
- يحدد connect/read timeout صريحًا.
- يصنف الأخطاء إلى retryable وpermanent.
- يسجل provider request ID وcorrelation ID بلا secrets أو PII حساسة.
- يدعم fake وcontract tests محفوظة fixtures بعد تنقيحها.

## الإشعارات

- Push payload يحمل notification ID وaction code ومعرفًا غير حساس فقط؛ التطبيق يجلب التفاصيل المصرح بها من API.
- invalid device tokens تعطل ولا يعاد إرسالها بلا نهاية.
- SMS قناة محدودة للتنبيهات/OTP الحرجة حسب سياسة المدرسة، مع rate limit وconsent/template audit.

## الملفات

- bucket خاص افتراضيًا، object keys مولدة ولا تستخدم الاسم الأصلي.
- upload/download عبر URLs قصيرة العمر أو backend stream بعد authorization.
- metadata/checksum/scan status تحفظ قبل السماح بالتنزيل.
- لا يقبل النظام URL خارجيًا كمرفق موثوق.

## الدفع

- Moyasar هو adapter الأول لأنه يدعم SAR ووسائل مثل mada وApple Pay، لكن تفعيل production مشروط بعقد التاجر والـsandbox acceptance.
- redirect/callback من العميل لا يعتمد كإثبات دفع؛ الخادم يجلب العملية ويتحقق من id/status/amount/currency، ويعالج webhook موثقًا وidempotent.
- الأسرار server-side فقط، ولا تخزن بيانات البطاقة.
- كل refund/capture/void عبر Action محكومة بالصلاحية وaudit.

## الخرائط وGPS

- GPS events تدخل من credential خاص بجهاز/تطبيق السائق مع rate limits وretention.
- آخر موقع يحسب من بياناتنا؛ Google Routes يستخدم فقط لحساب ETA/route عند الحاجة وبـcache يحمي التكلفة والquota.
- تعطل مزود الخرائط لا يمنع حفظ GPS، بل يرجع ETA غير متاح.

## التغيير والفشل

لا يوجد fallback تلقائي بين مزودي دفع. تغيير المزود يضيف adapter وcontract tests وADR/تهيئة، ولا يغير واجهات الـDomain. Jobs تستخدم exponential backoff وjitter، ولا تعيد permanent validation/auth failures.

## المراجع

- https://firebase.google.com/docs/cloud-messaging
- https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-presigned-url.html
- https://docs.unifonic.com/reference/sms-api
- https://docs.moyasar.com/category/payments-api
- https://developers.google.com/maps/documentation/routes

