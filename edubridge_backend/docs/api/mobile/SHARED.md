# Shared Mobile APIs

كل المسارات هنا تتطلب Bearer token مناسبًا حسب route group.

## Notifications

- `GET /notifications`
- `POST /notifications/{delivery}/read`
- `POST /notifications/read-all`
- `GET /notification-preferences`
- `PATCH /notification-preferences`

التطبيق يجب أن يتعامل مع empty list كحالة طبيعية، وليس خطأ.

## Conversations / Chat

- `GET /conversations`
- `POST /conversations`
- `GET /conversations/{thread}/messages`
- `POST /conversations/{thread}/send`
- `POST /conversations/{thread}/messages` — alias لنفس send action.
- `POST /conversations/{thread}/read`

Authorization موجود على الـ conversation ownership/participants؛ لا يعتمد التطبيق على إخفاء thread محليًا فقط.

## Support tickets — Parent / Teacher / Student

### Categories
`GET /support/categories`

Keys:

- `general`
- `technical`
- `academic`
- `attendance`
- `transport`
- `finance`

### List
`GET /support/tickets?page=1&per_page=20`

### Create
`POST /support/tickets`

```json
{
  "category_key": "technical",
  "subject": "مشكلة في شاشة الحضور",
  "message": "تفاصيل المشكلة"
}
```

### Detail
`GET /support/tickets/{ticket}`

### Reply
`POST /support/tickets/{ticket}/messages`

```json
{
  "message": "متابعة التذكرة"
}
```

Ticket states: `open`, `pending`, `answered`, `resolved`, `closed`.

المستخدم يرى تذاكره فقط. School admin لديه dashboard endpoints للمتابعة والرد وتغيير الحالة.

## Private files

`GET /files/{publicId}/download`

الرابط يحتاج authorization + signed URL. لا تعتمد على public storage path.
