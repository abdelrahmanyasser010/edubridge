# معايير الهندسة والجودة

## PHP وLaravel

- `declare(strict_types=1);` في ملفات PHP التي ننشئها.
- PSR-12 وLaravel Pint؛ أسماء classes بالإنجليزية وأسماء المجال ثابتة.
- Enums مدعومة بعمود string؛ لا تستخدم MySQL ENUM كي تظل migrations مرنة.
- DTO/Data object عند عبور بيانات مركبة إلى Action؛ لا تمرر Request كاملًا.
- constructor injection، ولا تستخدم service locator داخل domain code.
- config من `config/*`، و`env()` داخل ملفات config فقط.
- التواريخ `CarbonImmutable` حيث تقلل الطفرات غير المقصودة.

## قاعدة البيانات

- `bigint unsigned` أو ULID يقرر مرة واحدة في `FND-004` ولا يخلط بينهما.
- foreign keys وunique/check constraints حيث يدعمها التصميم.
- بيانات المدارس التشغيلية توجد في قواعد tenant منفصلة، ولا يعتمد عزلها على filter داخل query. `school_id` لا يكرر آليًا داخل كل جدول tenant إلا لحاجة audit/domain مثبتة.
- migrations المركزية منفصلة عن migrations الـtenant، وأي tenant migration يجب أن تكون قابلة للتطبيق المتكرر على المدارس مع سجل نجاح/فشل لكل مدرسة.
- timestamps UTC و`deleted_at` فقط للكيانات التي تحتاج استعادة؛ ledger/audit لا يحذفان.
- `DECIMAL` للمال، مع `currency CHAR(3)`؛ لا تستخدم float.
- المبلغ يخزن بوحدة صغرى integer في طبقة التكامل إن كان المزود يتطلب ذلك، مع Value Object يمنع خلط الوحدات.
- indexes من use cases: foreign keys، unique business keys، وحالات/تواريخ قوائم العمل. راجع بـ`EXPLAIN` للمسارات الثقيلة.
- factories لكل model تستخدمه feature tests؛ seeders ببيانات وهمية فقط.

## API والتعامل مع الأخطاء

- Controllers invokable أو قصيرة لكل resource action.
- validation في FormRequest، authorization في Policy، serialization في Resource.
- Domain exceptions تتحول مركزيًا إلى error codes مستقرة.
- لا تعرض exception message أو SQL أو stack trace للعميل.
- استخدم eager loading صريحًا و`preventLazyLoading` خارج production/بحسب سياسة الفريق لاكتشاف N+1.

## الاختبارات

الهرم العملي:

- غالبية التغطية Feature API tests لأنها تثبت route/middleware/policy/DB/JSON معًا.
- Unit tests للقواعد الحسابية وValue Objects والحالات الانتقالية.
- Contract tests لمزودي الدفع/Push/SMS والـwebhooks.
- Smoke tests للـhealth وqueue وDB/storage في بيئة staging.

كل endpoint يحتاج على الأقل: نجاح، validation 422، بدون دخول 401، غير مصرح 403، مورد غير موجود 404، وعزل tenant/ملكية. يجب أن تثبت الاختبارات أن تبديل ID أو host أو header لا يفتح اتصال مدرسة أخرى. Collections تختبر pagination، والعمليات الحساسة تختبر التكرار والتزامن.

## Quality gates في CI

1. Composer validation وsecurity audit.
2. Pint check.
3. Static analysis (PHPStan/Larastan بالمستوى المتفق عليه، ويرفع تدريجيًا).
4. Unit + Feature tests على MySQL، وليس SQLite فقط.
5. OpenAPI lint + contract drift check.
6. migration fresh + seed smoke.

لا تخفض مستوى أداة أو تعطل test لحل failure؛ أصلح السبب أو سجل استثناءً مبررًا ومؤقتًا.

## Git والتغييرات

- commit صغير مرتبط بـTask ID.
- migrations وOpenAPI والاختبارات تسافر مع feature نفسه.
- لا تغييرات تنسيق شاملة مع feature وظيفي.
- backward compatibility داخل `/api/v1`؛ التغيير الكاسر يحتاج `/v2` أو فترة deprecation موثقة.
