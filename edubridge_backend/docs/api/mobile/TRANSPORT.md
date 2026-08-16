# Transport Mobile API — Current Verified Backend Scope

لم يتم توفير سورس Driver/Transport App مستقل في العينة المراجعة، لذلك هذه الوثيقة لا تخمن read APIs غير موجودة.

الموجود حاليًا تحت `app:transport`:

- `POST /transport/auth/login`
- `POST /transport/routes`
- `POST /transport/routes/{route}/assignments`
- `POST /transport/routes/{route}/trips`
- `POST /transport/trips/{trip}/tracking-events`
- `POST /transport/routes/{route}/alerts`
- Shared auth/notifications/conversations where allowed.

ملاحظة: تطبيق سائق فعلي غالبًا يحتاج read/current-trip/roster/stops endpoints، لكن **لم تتم إضافتها دون UI/spec** حتى لا يصبح العقد تخمينًا.
