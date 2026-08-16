# عقد وتصميم REST API

## 1. مصدر الحقيقة

بعد `FND-009` يكون `openapi/openapi.yaml` هو المرجع الوحيد للمسارات والـschemas. هذه الوثيقة تحدد القواعد العامة فقط. لا تنسخ قائمة endpoints كاملة هنا.

## 2. المسارات

- base path: `/api/v1`.
- استخدم أسماء موارد جمع وkebab-case عند تعدد الكلمات.
- persona prefix عندما تختلف الصلاحية أو representation/workflow فعلًا: `/teacher`, `/parent`, `/admin`, `/integration`.
- استخدم nested route لإظهار ownership الضروري فقط، مثل `/parent/students/{student}/attendance`.
- actions غير CRUD صريحة: `/assignments/{assignment}/publish`, `/behavior-notes/{note}/acknowledge`.
- لا تستخدم verbs عامة مثل `/save`, ولا endpoint يغير حالة عبر GET.

نثبت `sections` بدل `classes`، و`behavior-notes` بدل `behavior/behavior-records`، و`teaching-sessions` بدل `sessions`.

## 3. المصادقة

- Dashboard first-party SPA: Sanctum stateful cookie + CSRF إذا كان على نطاق موثوق.
- Mobile apps: Sanctum Bearer token لكل جهاز مع expiry/revocation policy.
- Integration endpoints: credentials/scopes مستقلة، signature وIP/rate controls حيث يلزم.
- `X-App-Type` metadata مساعد وليس مصدر authorization.

## 4. Envelope موحد

نجاح مورد واحد:

```json
{
  "data": {"id": "123", "type": "assignment", "attributes": {}},
  "meta": {"request_id": "01J..."}
}
```

نجاح collection:

```json
{
  "data": [],
  "links": {"next": null, "prev": null},
  "meta": {"request_id": "01J...", "per_page": 25}
}
```

خطأ:

```json
{
  "message": "The given data was invalid.",
  "code": "VALIDATION_FAILED",
  "errors": {"records.0.status": ["The selected status is invalid."]},
  "meta": {"request_id": "01J..."}
}
```

`message` قابل للترجمة وغير مناسب للمنطق داخل العميل؛ العميل يعتمد على `code` وحقول البيانات.

## 5. HTTP semantics

- `200` قراءة/تعديل ناجح، `201` إنشاء، `202` job غير متزامن، `204` حذف أو action بلا body.
- `400` طلب غير صالح نحويًا، `401` بلا مصادقة، `403` ممنوع، `404` غير موجود/مخفي بالسياسة، `409` تعارض حالة، `422` validation، `429` rate limit.
- `PATCH` لتعديل جزئي و`PUT` للاستبدال الكامل فقط.
- optimistic concurrency عبر `version` أو `If-Match` للدرجات والحضور عند احتمال التعديل المتزامن.

## 6. الصيغ العامة

- JSON keys: `snake_case`.
- الوقت ISO-8601 UTC مثل `2026-07-12T18:30:00Z`؛ التاريخ المحلي `YYYY-MM-DD` مع timezone المدرسة معروفًا.
- المال: `{ "amount": "150.00", "currency": "SAR" }` ولا يرسل float غير مضبوط.
- IDs تعامل strings في العقد حتى إن كانت bigint لتجنب فقد الدقة في بعض العملاء.
- boolean حقيقي، وnull لا يعني empty string.
- `Accept-Language: ar|en` للرسائل والعناوين المترجمة، وليس لتغيير enum values.

## 7. القوائم والاستعلام

- default `per_page=25` وحد أقصى `100`.
- cursor pagination للرسائل وGPS والfeeds، page pagination لشاشات الإدارة التي تحتاج عدد صفحات.
- filters مسماة ومستقرة، sorting allowlist مثل `sort=-created_at,name`.
- includes allowlist فقط، مع منع include عميق يسبب استعلامات غير محدودة.
- search مدخل منفصل `q`، ولا يبنى SQL خام من العميل.

## 8. الملفات والعمليات الطويلة

- upload عبر multipart أو presigned flow مع `file_id`؛ لا يقبل العميل URL اعتباطيًا كمرفق موثوق.
- download من signed URL قصير العمر بعد authorization.
- imports/exports/auto schedule الكبيرة ترجع `202` و`job_id` مع endpoint حالة.

## 9. Idempotency وrate limits

- `Idempotency-Key` إلزامي في wallet deduct/top-up، payment creation/refund، bulk attendance submission عند إعادة المحاولة، وwebhooks.
- نفس المفتاح + payload مختلف => `409`.
- limits منفصلة لتسجيل الدخول، OTP، chat، uploads، GPS، والعمليات المالية.
- تعرض headers القياسية للحد وإعادة المحاولة حيث يدعمها gateway.

## 10. أول عقود يجب كتابتها

`FND-009` يغطي common schemas/auth/errors/pagination، ثم يضاف عقد كل vertical slice داخل التاسك نفسه. لا ننقل مئات endpoints من الوثائق القديمة دفعة واحدة قبل تثبيت business rules.

