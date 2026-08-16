# Parent Mobile API

كل student-scoped endpoint يتحقق في السيرفر من علاقة ولي الأمر النشطة بالطالب. محاولة استخدام ID لطالب غير مرتبط ترجع 404 ولا تكشف وجود الطالب.

## 1. My Students

`GET /parent/students`

يعيد الأبناء المرتبطين بولي الأمر مع grade/section/relationship/default selection.

## 2. Parent profile

- `GET /parent/profile`
- `PATCH /parent/profile`

Editable fields الحالية:

```json
{
  "full_name": "محمد أحمد",
  "phone": "+9665..."
}
```

لا يغير endpoint البريد/الهوية/role.

## 3. Home overview

`GET /parent/students/{student}/overview`

Endpoint مجمع للـ Home cards ويعيد:

- student summary
- attendance summary
- pending assignments count
- unread notifications count
- latest published behavior note
- latest published/locked assessment
- transport summary
- outstanding invoices count/amount
- wallet balance

الغرض تقليل عدد requests عند فتح Home، وليس استبدال detail screens.

## 4. Attendance

`GET /parent/students/{student}/attendance`

## 5. Medical excuse

`POST /parent/students/{student}/medical-excuses`

راجع OpenAPI للـ multipart/file contract الحالي إن وُجد attachment.

## 6. Assignments

- `GET /parent/students/{student}/assignments`
- `POST /parent/students/{student}/assignments/{assignment}/submissions`
- `GET /parent/students/{student}/assignments/{assignment}/attachments/{file}/download`

## 7. Behavior

- `GET /parent/students/{student}/behavior-notes`
- `POST /parent/behavior-notes/{note}/acknowledge`

## 8. Leave permits

`POST /parent/students/{student}/leave-permits`

## 9. Parent summons

- `GET /parent/students/{student}/summons`
- `POST /parent/summons/{summons}/respond`

## 10. Grades / reports / appeals

- `GET /parent/students/{student}/reports/recent-assessments`
- `GET /parent/students/{student}/reports/terms`
- `GET /parent/students/{student}/reports/terms/{term}`
- `POST /parent/students/{student}/reports/certificate`
- `POST /parent/grade-entries/{entry}/appeals`

Term report يعيد grade-entry IDs حتى تستطيع شاشة الاعتراض إرسال appeal على الدرجة الصحيحة بدل ID وهمي.

## 11. Activities

### List
`GET /parent/students/{student}/activities?status=upcoming|past&page=1&per_page=20`

Fields الأساسية:

- `id`, `title`, `description`
- `starts_at`, `ends_at`, `location`, `organizer`
- `capacity`, `remaining_seats`
- registration open/close times
- `fee_amount_minor`, `currency`
- `status`
- `registration`

### Detail
`GET /parent/students/{student}/activities/{activity}`

### Register
`POST /parent/students/{student}/activities/{activity}/register`

Free activity → `confirmed`.
Paid activity → `awaiting_payment` + `invoice_id` يتم دفعه من Finance flow.

### Cancel
`DELETE /parent/students/{student}/activities/{activity}/registration`

إذا الفاتورة مدفوعة لا يتم عمل refund صامت؛ الطلب يرفض ويحتاج refund workflow إداري.

## 12. Finance / wallet

راجع `PAYMENTS_AND_WALLET.md`.

Main endpoints:

- `GET /parent/students/{student}/finance/summary`
- `GET /parent/students/{student}/invoices`
- `GET /parent/students/{student}/invoices/{invoice}`
- `GET /parent/students/{student}/wallet`
- `GET /parent/students/{student}/wallet/transactions`
- `POST /parent/students/{student}/wallet/payment-token`
- `POST /parent/students/{student}/wallet/top-up-sessions`
- `POST /parent/students/{student}/invoices/{invoice}/payment-sessions`
- `GET /payments/methods`
- `GET /payments/{payment}`
- `GET /payments/{payment}/receipt`

## 13. Transport live status

`GET /parent/students/{student}/transport/live-status`

الاستجابة المحدثة تحتوي قدر المتاح من:

- route id/name/code
- bus plate
- driver name/contact policy data
- trip id/status
- latitude/longitude/speed
- last updated timestamp
- stale flag
- ETA field

**إذا لا يوجد route estimator حقيقي، `eta_minutes` يبقى null ولا يتم اختراع زمن وصول.**

Opt-out:

`POST /parent/students/{student}/transport/opt-outs`
