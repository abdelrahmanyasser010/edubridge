# EduBridge Mobile API — Integration Guide

هذه الحزمة هي مرجع ربط تطبيقات EduBridge بالمشروع الحالي بعد استكمال فجوات تطبيق Parent والجزء المرفوع من Teacher App بتاريخ 2026-08-08.

## التطبيقات

- Parent: تمت مراجعة UI الفعلي واستكمال الـ backend المطلوب للربط.
- Teacher: تمت مراجعة الجزء الموجود داخل `lib/teatcher/` واستكمال read/discovery APIs المطلوبة بجانب workflows الموجودة.
- Student: الوثيقة تسجل الـ APIs الموجودة بالفعل فقط؛ لم يصل سورس Student App مستقل في العينة التي بُنيت عليها هذه الجولة.
- Transport: الوثيقة تسجل الـ APIs الموجودة بالفعل فقط؛ لم يصل سورس Driver/Transport App مستقل في العينة.

## Base URL

Local example:

```text
http://alpha.edubridge.test:8000/api/v1
```

Production pattern:

```text
https://{school-subdomain}.api.edubridge.com/api/v1
```

المدرسة تُحل من الـ Host / domain context. لا يرسل تطبيق Flutter `school_code` أو `app_type` في login.

## ملفات المرجع

- `AUTHENTICATION.md` — onboarding/login/session/device/FCM.
- `SHARED.md` — notifications, conversations, support.
- `PARENT.md` — Parent APIs كاملة.
- `TEACHER.md` — Teacher APIs كاملة.
- `STUDENT.md` — الـ Student routes الحالية المؤكدة.
- `TRANSPORT.md` — الـ Transport routes الحالية المؤكدة.
- `PAYMENTS_AND_WALLET.md` — Saudi payments + invoices + wallet + QR + webhook.
- `ERRORS.md` — error envelope والتصرف المطلوب من Flutter.
- `ENVIRONMENT.md` — server configuration المطلوبة.
- `INTEGRATION_MATRIX.md` — screen → endpoint readiness.
- `MISSING_APIS.md` — ما لم يتم تعريفه لأن UI غير متاح أو لأنه يحتاج قرار business إضافي.
- `VERIFICATION.md` — حدود التحقق لهذه النسخة.
- `openapi-mobile-v1.yaml` — OpenAPI 3.1.
- `EduBridge-Mobile.postman_collection.json` — Postman smoke/integration collection.

## Standard success envelope

```json
{
  "data": {},
  "meta": {
    "request_id": "01..."
  }
}
```

Paginated endpoints return pagination inside `meta.pagination` in mobile additions.

## Standard error envelope

```json
{
  "message": "The given data was invalid.",
  "code": "VALIDATION_FAILED",
  "errors": {
    "field": ["..."]
  },
  "meta": {
    "request_id": "01..."
  }
}
```

Flutter logic must branch on HTTP status / stable `code`, not on translated `message` text.
