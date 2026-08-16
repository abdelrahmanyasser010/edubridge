# الأمان، الخصوصية، والتشغيل

## الأمان التطبيقي

- deny by default في Policies، مع Policy لكل مورد حساس.
- تحقق متسلسل: TenantContext موثق يفتح قاعدة المدرسة الصحيحة، ثم Policy تتحقق من علاقة المستخدم بالمورد (معلم-شعبة، ولي أمر-طالب، مشارك-thread).
- لا يقبل `tenant_id`/اسم قاعدة/بيانات اتصال من body أو query. الـhost/header المسموح مجرد مدخل resolver ويطابق عضوية موثقة.
- workers وOctane/العمليات طويلة العمر تنظف اتصال وسياق tenant بعد كل request/job لمنع تسرب مدرسة إلى التالية.
- لا تثق في role أو student/school داخل token payload وحده؛ تحقق من الحالة الحالية في الخادم.
- password hashing بإعداد Laravel الآمن ومراجعة rehash عند الدخول.
- throttling للدخول وOTP واستعادة كلمة المرور؛ MFA للحسابات الإدارية الحساسة.
- session/token rotation والإبطال عند تغيير كلمة المرور أو تعطيل الحساب.
- CORS allowlist وsecure cookies وCSRF للـSPA؛ لا wildcard مع credentials.

## حماية البيانات

- اجمع أقل قدر من PII، وحدد retention لكل: audit، GPS، chat، support، files.
- encryption at rest للبنية/النسخ الاحتياطية، وتشفير application-level للحقول شديدة الحساسية عند الحاجة.
- logs تستخدم IDs وrequest_id؛ تمنع passwords, tokens, national IDs, medical text, payment payloads الكاملة.
- audit before/after يمر عبر redaction allowlist.
- الملفات private افتراضيًا، MIME يفحص من المحتوى، size/type allowlist، malware scan، وsigned download.
- gate/QR/reset tokens تخزن hash، قصيرة العمر، أحادية الاستخدام.

## المدفوعات والمحفظة

- تحقق signature وtimestamp وevent id للwebhook، واحفظ raw payload مشفرًا/مقيد الوصول عند الحاجة للتحقيق.
- لا تخزن بيانات البطاقة؛ استخدم hosted page/tokenization من المزود.
- ledger immutable، reconciliation job، وتنبيه عند اختلاف cached balance.
- transaction + row lock + idempotency لكل خصم/إضافة.
- refund عملية عكسية لا تعديل للسجل الأصلي.

## الاعتمادية

- queues منفصلة بالأولوية: `critical`, `notifications`, `imports`, `reports`.
- retry مع backoff وtimeout وحد محاولات وdead-letter/failed jobs review.
- external calls لها connect/read timeout وcircuit/backoff مناسب؛ لا request بلا timeout.
- health endpoints: liveness بسيط، readiness يفحص الاعتماديات الضرورية بحذر.
- scheduler يعمل بمثيل واحد للمهام غير المتكررة مع locks.

## الرصد

- request/correlation ID من أول middleware حتى job/provider log.
- structured logs، error tracking، metrics للlatency/error rate/queue age/DB connections.
- تنبيهات: فشل payment webhook، تراكم critical queue، فشل backups، DB saturation، ارتفاع 5xx/429، GPS stale.
- dashboards تفصل business metrics عن operational metrics.

## النسخ الاحتياطي والتعافي

- backups مشفرة ومجدولة لكل tenant على حدة وللقاعدة المركزية، مع retention ونسخة خارج نفس failure domain واختبار استعادة مدرسة منفردة.
- اختبارات restore دورية؛ وجود backup من دون restore test ليس ضمانًا.
- يحدد قبل الإنتاج RPO/RTO ومسؤول الاستجابة للحوادث.
- migrations production بنهج expand/contract للتغييرات الكاسرة ونسخة احتياطية/rollback plan.

## النشر

- environments: local, test, staging, production؛ أسرار مستقلة.
- `APP_DEBUG=false` في production، cache config/routes/events، وتشغيل workers بإدارة process.
- deploy آلي: build immutable، tests، migrate آمن، reload workers، smoke test، rollback.
- least-privilege لحساب DB وstorage/provider credentials مع rotation.

## بوابة ما قبل الإنتاج

- threat model لأهم flows: auth، parent ownership، grades publish، files، gate pass، wallet/payment، webhooks.
- authorization matrix آلية ومختبرة.
- load test لأكثر endpoints استخدامًا وbulk attendance/notifications.
- disaster restore rehearsal.
- dependency/security scan بلا high/critical غير مقبول.
- privacy/retention review وتوثيق incident runbook.
