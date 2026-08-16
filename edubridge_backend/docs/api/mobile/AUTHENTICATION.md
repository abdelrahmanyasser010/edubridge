# Mobile Authentication

## School onboarding

`POST /school/lookup`

يُستخدم invitation/QR flow للحصول على مدرسة المستخدم والـ API base URL. بعد نجاح onboarding يخزن التطبيق الـ base URL الموثوق ولا يطلب `school_code` من المستخدم في كل login.

## App-specific login

| App | Endpoint | Ability issued |
|---|---|---|
| Parent | `POST /parent/auth/login` | `app:parent` |
| Teacher | `POST /teacher/auth/login` | `app:teacher` |
| Student | `POST /student/auth/login` | `app:student` |
| Transport | `POST /transport/auth/login` | `app:transport` |

Request:

```json
{
  "email": "user@example.com",
  "password": "secret-password",
  "device_id": "stable-installation-uuid",
  "device_name": "EduBridge Parent - Android"
}
```

Rules:

- `email`: required email.
- `password`: required string.
- `device_id`: required, max 128; **installation UUID** ثابت، وليس hardware fingerprint.
- `device_name`: optional/nullable, max 128.
- لا ترسل `school_code`.
- لا ترسل `app_type`.
- لا ترسل FCM token داخل login.

Success includes:

- `token`
- `token_type = Bearer`
- `expires_at`
- `user`
- `school`
- `device_session`

## Session lifecycle

- `GET /auth/me`
- `GET /auth/device-sessions`
- `POST /auth/logout`
- `POST /auth/device-sessions/{deviceSession}/revoke`
- `DELETE /auth/device-sessions/{deviceSession}/revoke` (compatibility alias)

Send on protected routes:

```http
Authorization: Bearer <token>
Accept: application/json
```

## FCM token

`PUT /auth/device/push-token`

```json
{
  "token": "<FCM_TOKEN>",
  "platform": "android"
}
```

يُرسل بعد login وعند Firebase token refresh. الـ FCM token منفصل عن `device_id`.

## 401 vs 403

- `401 UNAUTHENTICATED`: امسح session وارجع login.
- `403 APP_ACCESS_DENIED`: الحساب صحيح لكنه غير مسموح له بهذا التطبيق.
- `403 FORBIDDEN`: المستخدم داخل التطبيق لكن العملية غير مسموح بها؛ لا تمسح الـ token تلقائيًا.
