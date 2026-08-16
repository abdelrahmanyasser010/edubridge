# Remaining Mobile API Gaps

بعد مقارنة الـ Parent UI والجزء الموجود من Teacher UI، الفجوات التي كانت ظاهرة فيهما أضيفت في هذه الجولة.

المتبقي **ليس فجوة مؤكدة من التطبيق المرفوع**:

## Student App

لم يتم توفير سورس Student App مستقل. الموجود في backend الآن يغطي assignments + shared APIs. لا يتم اختراع attendance/grades/schedule endpoints قبل مراجعة UI/spec الحقيقي.

## Driver / Transport App

لم يتم توفير سورس Driver App مستقل. توجد write/tracking routes، لكن read/current-trip/stops/roster APIs لا تُعرّف من التخمين.

## ETA

Parent transport payload يعيد آخر GPS وحالة stale. `eta_minutes` لا يتم اختراعه إذا لم يوجد RouteEstimator حقيقي. إضافة Google Routes/مزود آخر تحتاج integration task منفصل وcredentials/cost policy.

## Provider refunds

Dashboard finance refund workflow موجود. Provider refund webhooks التي تحدث خارج هذا workflow تُعلّم `requires_reconciliation` بدل تغيير ledger تلقائيًا. إذا قررت الشركة السماح بrefund من provider مباشرة، يلزم reconciliation job/action صريح يطابق provider refund IDs/amounts ويطبق finance/wallet compensating transactions.

## Attachments for support tickets

Support text lifecycle مضاف. إذا كان التصميم النهائي يتطلب صور/ملفات داخل التذكرة، اربطها بالـ private file subsystem الحالي في task منفصل بدل public paths.

## Canteen / POS QR redemption

Parent Mobile can now request a secure, short-lived, single-use wallet token, and `WalletLedger::deductByToken()` already contains the atomic deduction primitive. The supplied mobile source did **not** include a Canteen/POS application or its authentication model, and no API route currently exposes that primitive.

Before a real canteen scanner goes live, define its client/app context and add an authenticated redemption endpoint (for example under a dedicated canteen/POS context) requiring `wallet.deduct`. Do not expose QR redemption as an unauthenticated public endpoint.

## Per-tenant payment merchant credentials

The current payment adapter reads credentials from server environment variables, which is suitable when the EduBridge deployment uses one platform merchant account. The existing `integration_settings.secret_ref` stores only a secret reference and the supplied archive does not contain a secret-resolver implementation.

If every school will own a separate Moyasar/PSP merchant account, add a real server-side secret manager/resolver and resolve the provider credentials from tenant context before production. Do not store provider secret keys in tenant JSON/config rows or return them to mobile clients.

## Paid activity seat hold policy

Paid activity registrations currently remain `awaiting_payment` and reserve capacity until they are paid/cancelled. If the business wants a short temporary seat hold instead, define the expiry policy and add an expiration job/action before launch. This is a business-policy decision rather than a Flutter integration blocker.
