# ADR-008: إعادة هيكلة نظام تسجيل الدخول والـ App Context

- الحالة: Accepted
- التاريخ: 2026-07-21
- يكمّل: ADR-004 (Authorization Matrix)، ADR-007 (Database-per-Tenant)
- يؤثر على: FND-005، وكل Task يستخدم `loginAndReturnToken` في اختباراته

---

## السياق

التصميم الحالي لتسجيل الدخول يطلب من العميل إرسال:

```json
{
  "email": "...",
  "password": "...",
  "school_code": "alpha",
  "device_id": "...",
  "app_type": "teacher"
}
```

بعد مراجعة معمارية كاملة للكود تبين ما يلي:

1. `school_code` يُرسل من العميل لكن يمكن تحديد المدرسة تلقائيًا من host/subdomain أو slug محفوظ محليًا.
2. `app_type` يُرسل من العميل ولا يوجد في الكود أي فحص role مقابله — معلم يستطيع Login بـ `app_type=dashboard` ويحصل على token.
3. `TenantConnectionResolver::resolveByHost()` موجودة وجاهزة منذ FND-004 ولم تُستخدم في auth flow.
4. `device_tokens` جدول موجود في tenant DB لكن لا endpoint لتحديث FCM token.
5. `app_type` مفيد كـ server-derived metadata في token وليس كـ client input.

---

## القرار

### 1. Tenant Resolution

#### للويب / Dashboard

المدرسة تُحدد من الـ host header:

```
Host: alpha.edubridge.com
→ TenantConnectionResolver::resolveByHost('alpha.edubridge.com')
→ يقرأ من school_domains جدول موجود
→ يجد المدرسة ويفتح الـ tenant connection
```

#### للمحمول (Flutter apps)

التطبيق يرتبط بالمدرسة أول مرة عبر:
- **QR Code** يحتوي invitation token قصير العمر.
- **Invitation Link** مثل `https://join.edubridge.com/t/eyJ...`.
- **Deep Link** يحمل tenant_slug.

الـ link يُعيد للتطبيق:

```json
{
  "school": {
    "name": "Alpha International School",
    "logo_url": "https://...",
    "primary_color": "#1A2B3C",
    "locale": "ar"
  },
  "tenant_slug": "alpha",
  "api_base_url": "https://alpha.api.edubridge.com"
}
```

التطبيق يحفظ `api_base_url` محليًا في Secure Storage، ثم يرسل جميع الطلبات إليه مباشرةً. بعدها يحدد المدرسة من host كما يفعل الويب.

> **ملاحظة:** Flutter لا تقييد عليه في استخدام dynamic subdomains — المشكلة الفعلية هي:
> - كيف يعرف التطبيق الـ URL أول مرة.
> - إعداد wildcard DNS + wildcard SSL.
> - التحقق من أن الرابط تابع للنظام قبل حفظه.
> لذلك الـ QR/invitation flow هو الحل للـ onboarding وليس قيودًا في Flutter نفسه.

### 2. App Type: من route لا من body

نضيف endpoints منفصلة لكل تطبيق:

```
POST /api/v1/dashboard/auth/login
POST /api/v1/teacher/auth/login
POST /api/v1/parent/auth/login
POST /api/v1/student/auth/login
POST /api/v1/transport/auth/login
```

Middleware جديد `SetAppTypeFromRoute` يقرأ الـ prefix ويضع `app_type` في `$request->attributes`، فلا يرسله العميل أبدًا.

**مهم:** نقل `app_type` إلى route **ليس حماية أمنية وحده**. أي شخص يستطيع استدعاء أي endpoint بـ Postman. الحماية الكاملة تتطلب ثلاثة مستويات معًا:

#### المستوى الأول: Route Context
```
POST /api/v1/teacher/auth/login
→ SetAppTypeFromRoute middleware يضع app_type = 'teacher'
```

#### المستوى الثاني: Role Check في LoginUser
```php
private const ALLOWED_ROLES = [
    'teacher'   => ['teacher'],
    'parent'    => ['parent'],
    'student'   => ['student'],
    'transport' => ['transport_supervisor'],
    'dashboard' => ['school_admin', 'academic_admin', 'student_affairs', 'finance_officer'],
];

// يفحص school_user.role_key مقابل ALLOWED_ROLES[$appType]
```

#### المستوى الثالث: Token Abilities
```php
$abilities = match ($appType) {
    'teacher'   => ['app:teacher'],
    'parent'    => ['app:parent'],
    'student'   => ['app:student'],
    'transport' => ['app:transport'],
    'dashboard' => ['app:dashboard'],
};
$user->createToken($tokenName, $abilities, $expiresAt);
```

بهذا لا يكفي أن يكون المستخدم authenticated؛ يجب أن يكون الـ token صادرًا للتطبيق الصحيح وأن يحمل الـ ability الصحيحة. middleware الخاص بكل resource group يتحقق من `app:*` ability عند الحاجة.

### 3. app_type يبقى في token كـ server-derived metadata

نحذف `app_type` من **request body** ونبقيه في:
- `personal_access_tokens.app_type` — قيمته تأتي من السيرفر (route).
- `device_tokens.app_type` — نفس المبدأ.

يفيد في:
- معرفة الجلسة صادرة لأي تطبيق (device sessions list).
- تسجيل الخروج من تطبيق محدد.
- routing الإشعارات الصحيح.
- audit logs.
- منع استخدام token صادر لـ teacher في endpoint خاص بـ dashboard.

لا تغيير على schema — فقط مصدر القيمة يتغير من client إلى server.

### 4. Device ID: Installation ID لا Hardware ID

نستخدم **Installation ID** وليس hardware identifier:

```dart
// أول تشغيل للتطبيق:
final installationId = const Uuid().v4();
// يُحفظ في: Android Keystore / iOS Keychain / Flutter Secure Storage
```

المبررات:
- Hardware IDs (androidId, identifierForVendor) قابلة للتغيير وتثير مشكلات خصوصية.
- Installation ID ثابت طوال عمر التثبيت، ويتغير فقط عند إعادة التثبيت — وهذا مقبول (يعني جلسة جديدة).
- Browser: UUID يُحفظ في IndexedDB أو localStorage (وليس fingerprint).
- اسم العمود في DB يبقى `device_id` لتجنب migration غير ضرورية.

### 5. school_code: إزالته هدفه UX لا أمان أساسي

`school_code` ليس ثغرة أمنية كبيرة في حد ذاته — نقله إلى subdomain لا يمنع تخمين subdomains بنفس الطريقة. الحماية الحقيقية من tenant enumeration تأتي عبر:
- رسائل خطأ عامة (لا فرق بين مدرسة غير موجودة ومستخدم غير موجود).
- Rate limiting على login.
- عدم كشف بيانات المدرسة قبل التحقق من الهوية.
- Invitation tokens غير قابلة للتخمين عند onboarding.
- مراقبة محاولات discovery.

هدف إزالة `school_code` هو: **UX أفضل، Branding أفضل، إخفاء تعقيد multi-tenancy عن المستخدم**.

### 6. FCM Token: endpoint منفصل

```http
PUT /api/v1/auth/device/push-token
Authorization: Bearer {token}
{ "fcm_token": "...", "platform": "android" }
```

يُرسل بعد login مباشرةً وعند تجديد الـ FCM token من Firebase. يستخدم جدول `device_tokens` الموجود في tenant DB.

---

## Login Body النهائي (بعد الـ refactor)

```json
{
  "email": "teacher@alpha.edu",
  "password": "secret",
  "device_id": "installation-uuid-v4"
}
```

لا يُرسل:
- `school_code` ← يأتي من host.
- `app_type` ← يأتي من route.
- `fcm_token` ← endpoint منفصل.

---

## تدفق تسجيل الدخول الكامل

```
1. التطبيق مرتبط بمدرسة مسبقًا عبر QR / invitation.
2. التطبيق يعرف api_base_url (محفوظ محليًا).
3. نوع التطبيق معروف من endpoint (route prefix).
4. المستخدم يدخل email + password فقط.
5. التطبيق يرسل installation_id داخليًا كـ device_id.
6. السيرفر يتحقق من:
   - المدرسة فعالة (من host).
   - العضوية فعالة (school_user).
   - tenant_connection فعالة.
   - الدور مسموح لهذا التطبيق (role_key vs app_type).
   - الجهاز غير محظور (مستقبلًا).
7. السيرفر يُبطل token قديم لنفس device_id في نفس المدرسة.
8. السيرفر يُصدر token يحمل:
   - school_id
   - device_id
   - app_type (server-derived)
   - app-specific abilities (app:teacher, app:dashboard ...)
9. التطبيق يرسل FCM token في endpoint منفصل.
```

---

## قواعد صارمة

1. لا يُرسل `app_type` في request body — قيمته دائمًا من السيرفر.
2. لا يُرسل `school_code` في login body بعد Phase 4 — يأتي من host.
3. `device_id` هو Installation UUID لا hardware identifier.
4. الـ Token Abilities يجب فحصها عند أي endpoint حساس يخص تطبيقًا بعينه.
5. FCM token لا يمر عبر login أبدًا.
6. رسالة الخطأ عند فشل login لا تفرق بين "مدرسة غير موجودة" و"مستخدم غير موجود" للحماية من enumeration.

---

## الملفات المتأثرة

### Phase 1 (غير كاسر)
- `app/Actions/Auth/LoginUser.php` — إضافة role_key vs app_type check.
- توثيق هذا ADR.

### Phase 2 (endpoints الجديدة)
- `routes/api.php` — إضافة route prefixes.
- `app/Http/Middleware/SetAppTypeFromRoute.php` — **جديد**.
- `app/Http/Middleware/PreLoginTenantResolver.php` — **جديد** (يستخدم resolveByHost الموجودة).
- `app/Http/Requests/Auth/LoginRequest.php` — حذف app_type، school_code اختياري.
- `app/Http/Controllers/Api/Auth/UpdateDevicePushTokenController.php` — **جديد**.

### Phase 3 (تحديث العملاء والاختبارات)
- جميع test helpers (30+ ملف) تستخدم `loginAndReturnToken`.
- `openapi/openapi.yaml`.
- Postman collections.

### Phase 4 (حذف القديم)
- حذف legacy login endpoint.
- حذف school_code + app_type من LoginRequest.

---

## البدائل المرفوضة

| البديل | السبب |
|---|---|
| إبقاء app_type في body مع validation | يُمكّن التلاعب؛ لا enforcement حقيقي |
| single endpoint موحد | يبقي app_type مشكلة body، ولا يعطي route-level context |
| hardware ID كـ device_id | مشكلات خصوصية، يتغير، غير موثوق عبر platforms |
| FCM في login | يظهر في logs، يتغير بشكل مستقل عن الجلسة |
| Full refactor فوري | يكسر 30+ test + clients موجودة بدون فائدة مقابلة |
