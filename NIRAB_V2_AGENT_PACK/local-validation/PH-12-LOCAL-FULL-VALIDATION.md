# PH-12 — المرحلة النهائية المستقلة: البيئة والاختبارات المحلية الكاملة
**لا تبدأ قبل إغلاق PH-00..PH-11 كودياً.** هذه أول مرحلة يسمح فيها install/build/Docker/database/runtime/browser. الإذن محلي فقط، ولا يتوسع إلى VPS أو خدمات حقيقية. لا يعد الإكمال المحلي نشراً.

## بوابة الدخول
وجود CODE-READY، migrations/seeders/tests مكتوبة، route map نهائي، protected manifest draft، لا TODO core، AppEnvironment يمنع production fallback، external integrations fake، الملفات/الbranches غير ناشرة. اجمع commit IDs للمشاريع الأربعة. أي نقص في هذا الفحص يصلح محلياً قبل تشغيل stack.

## L0 — عزل البيئة قبل boot
- workspace مستقل؛ `.env.v2.local` جديد من مثال بلا أسرار إنتاج. APP_KEY وPassport keys ومستخدمو الإدارة **محليون جدد**، لا key:generate على نسخة VPS.
- MySQL باسم `nirab_v2_local` وحساب/كلمة عشوائيين، حجم تخزين جديد باسم `nirab-v2-local_mysql`؛ لا volume قديم من برنامج آخر. sentinel DB يثبت local generation قبل seed/reset الاختباري.
- Runtime network داخلي وports على 127.0.0.1؛ outbound allowlist فارغ لتطبيقات العمل. تمنع APIs production وGoogle/Firebase/SMTP/SMS/Odoo/payment/analytics الحقيقية في backend وفي browser/Flutter. مراقبة network assertions ليست مجرد env flag.
- Install/build downloads يسمح بها من registries/packages اللازمة فقط في مرحلة البناء، دون مشاركة مفاتيح الإنتاج. بعدها worker/runtime لا يحتاج internet. لا privileged containers ولا mount Docker socket في التطبيق.
- سجل OS/CPU/RAM/Docker/Compose/PHP/Composer/Flutter/Dart/Java/Android SDK وimage digests وlockfile hashes. استخدم locks المرفوعة، ولا تسمِ نتيجة package upgrade غير موثق «نفس الإصدار».

## L1 — topology المطلوبة
| الخدمة | الإعداد |
|---|---|
| backend web | PHP متوافق مع composer.lock، extensions curl/gd/json/mysqli/openssl/pdo_mysql وغيرها حسب lock، HTTP :8080 loopback |
| mysql | MySQL 8 مع spatial/SRID 4326 وREAD COMMITTED، charset utf8mb4، strict sql mode، :33067 loopback عند الحاجة |
| queue-worker | نفس code/env generation؛ database queue مستقل؛ no external adapters |
| scheduler | نفس code؛ jobs تحت fake clock عند tests، ثم actual local clock لتجربة scheduler smoke |
| storage | local disk أو S3-compatible test bucket، لا R2 live؛ media/private upload paths منفصلة |
| mail sink | mailbox محلي مثل Mailpit، لا forward خارجي |
| fake gateways/Odoo/SMS/FCM/maps | adapters قابلة لمحاكاة success/failure/timeout/retry/duplicate/late callbacks |
| Customer Flutter Web | build/local dev :8081؛ API base صريح localhost؛ asset cache isolated |
| browser E2E | Chromium/Playwright أو tool مماثل متاح، artifacts/screenshots، test users synthetic |

الملفات التي يجب أن يكون الوكيل كتبها في PH11: `dev/compose.v2.local.yml`, `dev/.env.v2.local.example`, bootstrap helpers وtest runner script. يحدد PH00 مسارات mount للمشاريع الأربعة. لا تستخدم paths Windows الحقيقية للمستخدم افتراضياً.

## L2 — إقلاع نظيف قابل للإعادة
1. تحقق static للCompose/env/secrets/ports؛ provision keys محلياً.
2. ثبت الاعتمادات من lock ثم build local images وابدأ mysql والخدمات المحلية.
3. migration discovery للroot+Modules؛ سجل `migrate:status/plan` واختبر fresh local install بالكامل. لا تتخطَّ migrations فاشلة وتعلن نجاحاً. أصلح ordering/compatibility التاريخي عبر bootstrap محلي واضح أو corrective schema strategy، لا تعديل legacy تاريخ VPS اعتباطياً.
4. شغل seed local profile فقط. أنشئ fixture المدينتين الصناعيتين وفروعهما/actors/ورديات/مهارات/الخدمات التسع. سجّل identities غير الحساسة؛ passwords محلية خارج Git.
5. تحقق readiness وFKs وisolation ومحددات حدود المدن؛ الـsentinel يمنع نفس script من العمل على production DB.
6. أعد seed للمرة الثانية: لا duplicate users/offers/roles/permissions ولا reset تعديل أدمن لا يملكه seeder.
7. افتح admin/provider web وCustomer web؛ لا 500/bootstrap errors. كل empty states ذات معنى.

أمثلة أوامر لا تستخدم إلا بعد أن يكتب الوكيل ملفاتها الفعلية ويثبت help:
```sh
# داخل Backend workspace المحلي فقط
# لا توجد هذه الملفات بالضرورة في الأرشيف الأصلي.
docker compose -p nirab-v2-local -f dev/compose.v2.local.yml --env-file dev/.env.v2.local config
docker compose -p nirab-v2-local -f dev/compose.v2.local.yml --env-file dev/.env.v2.local up --build -d
```
لا `down -v` عام ولا حذف volumes خارج اسم هذا المشروع. تنظيف قاعدة الاختبار صراحة بعد attestation local، وليس `migrate:fresh` على connection يمكن تبديلها بلا تحقق.

## L3 — الاختبارات الآلية
نفذ بالترتيب: lint/syntax/static analyzer، unit domain، JSON schemas/OpenAPI contract، integration real MySQL، HTTP/RBAC، concurrency متعدد connections، fake gateways/outbox workers/scheduler، browser E2E، Flutter models/widgets/integration، builds.

معايير إلزامية: كل golden pricing case ينجح بالهللة؛ FK/schema constraints؛ يوم/فترة/fixed scheduling/recurrence والـlast-slot race؛ wallet last balance race؛ double confirm/refund/quote accept/redemption؛ protected data unchanged after reset rehearsal؛ old callbacks quarantine؛ لا outbound traffic حقيقي؛ لا auth/role bypass.

راجع `acceptance/02-AUTOMATED-TEST-PLAN.md` و`04-REQUIREMENTS-TRACEABILITY.md`. Coverage نسبها مساعدة، لا بديل عن نجاح الحالات الحرجة. لا تضع threshold وهمياً لتخطي مجلدات المال/الجدولة. failures لها issue ID وسبب وإصلاح وإعادة اختبار.

## L4 — الويب واللوحات
من browser حقيقي: admin creates new service not seeded → publish locally → city enable → customer config/cart/checkout → branch assign → technician equivalent API/UI → completion/refund/report. أدخل كل نوع من الخدمات التسع ببيانات مختلفة لا screenshot واحدة فقط. اختبار responsive 360px/768px/1440px وRTL/EN وrefresh/deeplinks/keyboard/errors. assert no production domains requested وno sensitive values in console/network artifacts.

Provider Blade اختبارات scoped action؛ Customer Flutter Web checkout/payment return، fake gateway redirects local. PDFs/invoices/exports يجب فتحها والتحقق من totals/Arabic scope لا مجرد HTTP200.

## L5 — التطبيقات الأصلية
`flutter analyze` و`flutter test` الثلاثة، `flutter build web` للعميل و`flutter build apk --debug` لكل التطبيقات إذا toolchain متاح. لا release signing/live Play/App Store upload. اختبار native على Android emulator/device محلي موصول متاح: الدخول/المواعيد/الصور/حالات الشبكة/الفني/الفرع/deeplinks. إن تعذر جهاز بعد محاولة setup معقولة، ضع `NATIVE_RUNTIME_PENDING` وخطوات مالك محددة؛ build نجاح ليس runtime نجاح.

iOS للفني الموجود يتطلب macOS/Xcode؛ Customer/Provider iOS absent في اللقطة. لا تولد مجلد iOS جديد وتعتبر معرفات التوقيع مهيأة؛ وثق الفجوة، ويمكن إعداد code المشتركة فقط. الاختبارات المدعومة تنفذ كلها ولا يتوقف backend/web بسبب غياب Mac.

## L6 — بروفة التصفير والحفظ والاستعادة
على DB **محلية صناعية ثانية**: seed protected config/secret placeholders/مدن/فروع/users داخليين/مفاتيح local + بيانات تجارية legacy وV2 تجريبية، واحفظ protected digest؛ inventory→plan→invalid apply refuses→approved apply→verify→seed reference. اختبر unknown table/عميل referenced من city approval/crash resume/late webhook/foreign objects. تحقق أن مؤهلات الفنيين تحتاج mapping ولا تمنح تلقائياً.

استعد backup المحلي في DB ثالثة وتحقق من consistency؛ لا تكتب فوق DB الرئيسية لمجرد تجربة restore. مثال الأسرار في التقرير مقنّع؛ لا يختبر هذه الخطوة بنسخ أسرار إنتاج حقيقية.

## L7 — الجاهزية والتسليم
النجاح: LOCAL_VALIDATED لجميع الحالات الحرجة القابلة للتنفيذ، وصفر P0/P1 مفتوح؛ الاختبارات التي تعتمد خارجياً/أجهزة توثق pending وليست pass. سجل runtime failures والإصلاحات وإعادة regression بعد آخر commit. سلم final diff لكل مشروع، image/lock versions، API نهائي، schema reset manifest/runbook مع help outputs، artifacts، دليل manual، ونقاط owner config.

يظل PRODUCTION_NOT_TOUCHED. لا auto-push/main/Dokploy/SSH/production migrate بعد هذا التقرير. الاختبار الحقيقي للبوابات/FCM/Maps/Odoo يبقى تفعيل وقبول منفصل للمالك؛ fake نجاح لا يثبت نجاح المزود الحي.
