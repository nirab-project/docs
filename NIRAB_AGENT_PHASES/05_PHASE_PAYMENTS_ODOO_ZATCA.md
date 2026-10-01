# NIRAB — المرحلة 5: بوابات الدفع السعودية، Odoo، الفوترة وZATCA

## المتطلبات السابقة
- النموذج المالي المركزي من المرحلة 1.
- Branch attribution من المرحلة 1/2.
- Quote/Booking snapshots من المرحلة 3.
- Two-stage Cash/Transfer approval + custody من المرحلة 4.

## الهدف
بناء Integration مالية قابلة للإنتاج من ناحية الكود دون تشغيل خدمات خارجية أثناء التنفيذ:
- Payment gateway abstraction سعودي.
- Tap + Tabby + Tamara حسب متطلبات NIRAB.
- Mada/Apple Pay/Cards عبر ما تدعمه البوابة المختارة/configuration.
- Webhooks + idempotency + refunds.
- Odoo integration غير متزامن.
- Odoo هو Accounting Source of Truth.
- ZATCA عبر Odoo وليس تكاملًا موازيًا داخل NIRAB.
- Sync للعملاء، الفواتير، المدفوعات المعتمدة، عهد الفني، حركات المحفظة، ومركز تكلفة الفرع.

## مهم جدًا قبل الكود
لا يوجد Odoo version/credentials موثقة داخل السورس. لذلك:
- لا hardcode endpoint خاص بإصدار واحد داخل business logic.
- أنشئ `OdooTransportInterface`/adapter بحيث يمكن تفعيل transport من config/env.
- ضع implementation واحد فعلي مناسبًا للإعداد المستهدف إذا وجد version في ملفات المشروع؛ وإلا اجعل transport قابلًا للتبديل (`json2`/legacy JSON-RPC) مع فصل serialization عن domain services.
- لا تتصل بـOdoo أثناء التنفيذ.

## مصادر السورس

### Backend
- `Modules/PaymentModule/`
- `Modules/TransactionModule/`
- `Modules/BookingModule/`
- `Modules/CustomerModule/`
- `Modules/CustomerSubscriptionModule/`
- `Modules/BusinessSettingsModule/`
- `app/Console/Kernel.php`
- `config/queue.php`, `config/cache.php`, `config/database.php`
- `.env.example`

### Customer App/Web
- `lib/feature/checkout/`
- `lib/feature/booking/`
- wallet/loyalty related features.
- subscription/payment screens.

### Branch Manager/Admin
- payment information/reporting sections.

## المطلوب تنفيذه

### 1. Payment Gateway Domain Layer
لا تضف Tap/Tabby/Tamara logic مباشرة داخل BookingController.

أنشئ interface موحدًا مثل:
- create/initiate payment.
- get/verify status.
- parse webhook.
- refund.
- normalize payment reference.

Adapters:
- `TapGateway`.
- `TabbyGateway`.
- `TamaraGateway`.

استفد من PaymentModule الموجود ولا تكرر Payment architecture.

### 2. Secrets / Config
أضف `.env.example` keys بدون قيم حقيقية، مثل أسماء منطقية:
- gateway public/secret keys.
- webhook secrets.
- mode/sandbox flag.
- Odoo base URL/database/user/api key/transport.
- queue/cache driver hints.

لا تخزن أي credential في DB plaintext إن كان المشروع يستخدم encrypted settings pattern؛ اتبع النمط الحالي.

### 3. Payment State Machine
استخدم حالات واضحة:
- initiated/pending.
- authorized إن احتاج gateway.
- paid/confirmed.
- failed/canceled.
- partially_refunded/refunded.

لا تعتمد فقط على redirect success من تطبيق العميل.

### 4. Webhooks
لكل gateway:
- endpoint مستقل أو router آمن.
- verify signature حسب adapter.
- idempotency: نفس event لا يُطبّق مرتين.
- raw event log sanitized.
- map event → payment transaction.
- update Booking payment state فقط بعد verification الصحيحة.

لا تختبر Webhooks خارجيًا؛ نفذ parser/verification code فقط.

### 5. Refunds
دعم خيارين:
- refund إلى original payment method إذا كانت البوابة/الحالة تسمح.
- refund إلى customer wallet.

يجب أن يكون القرار مسجلًا.

Original-method refund:
- async state.
- gateway refund reference.
- لا تضف Wallet balance أيضًا.

Wallet refund:
- ledger transaction واحدة idempotent.

### 6. Wallet accounting readiness
كل حركة wallet يجب أن تملك:
- type/source.
- amount.
- booking/reference.
- unique/idempotency key عند الأحداث الخارجية.
- accounting sync status.

مصادر الوثيقة:
- top-up.
- booking refund.
- cashback.
- promotion.
- partial wallet + card.

المحفظة مغلقة داخل النظام ولا تضف cash withdrawal للعميل.

### 7. Odoo Integration Module
أنشئ Module مستقلًا، مثل:
`Modules/OdooIntegration/`

بنية مقترحة:
- Contracts.
- DTOs.
- Transports/Client.
- Mappers.
- Jobs.
- Services.
- Models/SyncLog/Outbox.
- Controllers/Admin monitoring.
- Routes.

لا تجعل Controllers الخاصة بالحجز تستدعي Odoo HTTP مباشرة.

### 8. Async Outbox / Queue
قاعدة ثابتة:
**الحجز لا ينتظر Odoo.**

عند حدث محاسبي:
1. transaction المحلية تنجح.
2. يسجل integration outbox/sync record.
3. Job مستقل يرسل Odoo.
4. Retry مع backoff.
5. بعد max attempts تصبح failed/requires_attention.
6. Admin يستطيع Retry يدويًا.

لا تفقد event إذا تعطل queue بعد DB commit؛ استخدم outbox/DB record قبل dispatch عند الحاجة.

### 9. Queue/Redis Configuration
أضف production-ready configuration/documentation لـ:
- Redis queue.
- cache.
- locks إن احتاج idempotency.

لا تغيّر environment الفعلي بالقوة.
حدّث `.env.example` فقط، والكود يجب أن يعمل مع config.

لا تشغل Redis/worker في هذه المرحلة.

### 10. Odoo Entity Mapping
أنشئ mapping واضحًا على الأقل:

**Customer**
- NIRAB customer ↔ Odoo partner id.

**Branch**
- provider/branch ↔ Odoo branch أو analytic/cost-center identifier configurable.

**Service**
- service/variation ↔ product/service identifier إذا احتاج invoice lines.

**Booking/Quote**
- external reference/readable id.
- invoice reference.

**Payment**
- gateway/method/reference.

احفظ `odoo_*_id` أو integration mapping table بطريقة لا تربط domain model بإصدار Odoo بقوة.

### 11. ما الذي يرسل إلى Odoo
على الأقل:
- customers.
- invoices/final service amount.
- confirmed electronic payments.
- cash/bank transfer فقط بعد Branch Manager + Accountant approvals من المرحلة 4.
- technician custody movements اللازمة محاسبيًا حسب mapping.
- wallet transactions.
- refunds/credit notes.
- branch cost center/analytic attribution.

### 12. لا ترسل غير المعتمد
قواعد صريحة:
- Cash `submitted` → لا sync.
- Branch verified فقط → لا sync.
- Accountant approved → eligible for sync.
- Electronic gateway verified → eligible مباشرة.

### 13. Invoice lifecycle
افصل:
- Booking completed.
- Invoice issued/posted.
- Payment paid/due.

دعم حالة خدمة مكتملة وفاتورة مستحقة.

### 14. ZATCA
لا تنفذ ZATCA API مباشرة داخل NIRAB إذا Odoo هو النظام المحاسبي المركزي.

المطلوب في NIRAB:
- استقبال/حفظ invoice metadata القادمة من Odoo: invoice number, UUID/reference, status, PDF/URL/reference, QR data إن لزم.
- إظهار الفاتورة للعميل.
- event/notification عند توفرها.

Odoo مسؤول عن Saudi localization/ZATCA clearance/reporting وفق بيئة العميل.

### 15. Invoice Delivery
Customer App/Web:
- قائمة/تفاصيل فاتورة مرتبطة بالحجز.
- عرض/تنزيل PDF عبر URL/API آمن.
- QR إذا مررته Odoo.

WhatsApp delivery ينفذ في المرحلة 6.

### 16. Odoo Monitoring Dashboard
Admin فقط:
- pending.
- processing.
- synced.
- failed.
- entity type/id.
- attempts.
- last error sanitized.
- last attempt.
- retry button.

لا تعرض request payload الذي يحتوي Secrets.

### 17. Idempotency
كل integration event يجب أن يملك key ثابتًا، مثل:
- payment confirmation per gateway transaction.
- invoice creation per booking/version.
- approved cash collection per collection id.
- wallet transaction per ledger id.

Retry لا ينشئ invoice/payment duplicate في Odoo حسب قدرات transport/mapping.

### 18. Branch Accounting Attribution
كل invoice/payment event يرسل branch/cost-center attribution.

لا توجد Provider commission أو payable.
100% revenue = NIRAB؛ branch = analytics/cost center/organizational attribution.

### 19. Existing gateway code
السورس يحتوي إشارات/إعدادات لبعض gateways مثل Tap/HyperPay. افحص هل التنفيذ الكامل موجود فعلًا قبل إنشاء implementation مكرر.
- أعد استخدام الكود الكامل السليم.
- إذا configuration فقط بدون adapter فعلي، أكمل adapter الجديد وفق architecture الموحدة.

## معايير قبول المرحلة
- Tap/Tabby/Tamara لديها adapters وعقود موحدة بدون logic مبعثر في Controllers.
- Webhook handling idempotent وموقعه واضح.
- Refund original/wallet مدعوم كـworkflow.
- Odoo لا يستدعى synchronous من Booking request/completion.
- يوجد outbox/sync log/retry/admin monitor.
- Cash/transfer لا يرسل قبل المرحلتين؛ electronic verified يرسل تلقائيًا.
- branch cost-center attribution موجود.
- NIRAB لا ينفذ ZATCA بالتوازي؛ يستقبل نتيجة/فاتورة Odoo ويعرضها.
- لا توجد credentials حقيقية في السورس.


## قواعد تنفيذ إلزامية للوكيل

هذه الوثيقة **مواصفة تنفيذ** وليست تقريرًا أو اقتراحات. المطلوب تعديل السورس الفعلي وإنهاء نطاق هذه المرحلة بالكامل، مع الحفاظ على التوافق قدر الإمكان.

### قواعد عامة
1. افحص السورس الحالي قبل أي تعديل ولا تفترض أسماء ملفات أو جداول غير موجودة.
2. استخدم البنية الحالية Modular Laravel + Flutter، ولا تعِد بناء المشروع من الصفر.
3. حافظ على API backward compatibility كلما أمكن. إذا لزم تغيير عقد API، أضف الحقول الجديدة أولًا وحافظ على القديمة لفترة انتقالية بدل كسر التطبيقات فجأة.
4. لا تحذف جداول/أعمدة قديمة مستخدمة لمجرد أنها لم تعد ظاهرة وظيفيًا؛ علّمها Deprecated واتركها بقيم آمنة إن كانت إزالتها ستكسر أجزاء قديمة.
5. أي Migration جديدة يجب أن تكون قابلة للتراجع `down()` وأن تحتوي indexes/foreign keys المناسبة قدر الإمكان.
6. لا تضع Secrets أو مفاتيح حقيقية في الكود أو ملفات المثال. أضف أسماء متغيرات البيئة فقط عند الحاجة.
7. اجعل النصوص الجديدة قابلة للترجمة ولا تكتب نصوصًا عربية/إنجليزية ثابتة داخل منطق الباكند أو Widgets إن كان المشروع يستخدم ملفات ترجمة.
8. لا تغيّر حزم/Dependencies إلا عند الضرورة. إن أضفت Dependency فوثّق السبب في ملخص التنفيذ.
9. لا تُدخل كودًا تجريبيًا، TODOs غير منفذة، endpoints وهمية، أو stubs تُظهر الميزة وكأنها مكتملة.
10. عند وجود منطق قديم متعارض، افصله خلف Service/Policy/Feature flag مناسب بدل نسخ نفس المنطق في عدة Controllers.

### ممنوع أثناء التنفيذ
- ممنوع تشغيل السيرفر محليًا (`php artisan serve` أو ما شابهه).
- ممنوع تشغيل Docker/Compose أو بيئة كاملة.
- ممنوع تشغيل Emulator/Simulator أو فتح التطبيقات على جهاز.
- ممنوع تشغيل `php artisan migrate`, `migrate:fresh`, seeders أو تعديل قاعدة بيانات فعلية.
- ممنوع تشغيل PHPUnit/Pest/Flutter tests/E2E/Integration tests/Load tests/Stress tests.
- ممنوع الاتصال الحقيقي بـ Odoo أو بوابات الدفع أو SMS/WhatsApp أو أي خدمة خارجية أثناء التنفيذ.
- ممنوع إجراء اختبارات أداء ثقيلة أو إنشاء بيانات ضخمة.

### التحقق المسموح والمطلوب في نهاية المرحلة فقط
المطلوب **Build/Compile verification فقط** بدون تشغيل النظام:

**Laravel / PHP**
- شغّل `php -l` على ملفات PHP التي تم تعديلها/إنشاؤها.
- شغّل `composer validate` إن كانت Composer متاحة.
- يمكن تشغيل `composer dump-autoload -o --no-scripts` للتحقق من autoload فقط إذا كانت dependencies متاحة بالفعل.
- لا تشغّل أوامر Artisan التي تحتاج DB أو boot كامل للتطبيق.

**Flutter**
- لا تشغّل التطبيق.
- إذا كانت Flutter toolchain وdependencies متاحة: نفّذ Build فقط للتطبيقات التي تغيرت، مثل `flutter build apk --debug`، ولتطبيق العميل/الويب `flutter build web` إذا تغير جزء الويب.
- لا تبنِ iOS لأن ذلك قد يتطلب signing/macOS.
- إذا كانت البيئة لا تسمح بالبناء، لا تحاول تجهيز بيئة كاملة؛ اذكر بوضوح أن Build لم يُنفذ والسبب.

**Admin assets**
- إن تم تعديل JS/CSS المجمّع، يمكن تنفيذ build للأصول فقط إذا كانت node_modules/toolchain متاحة مسبقًا. لا تشغّل web server.

### تسليم الوكيل في نهاية المرحلة
أرفق ملخصًا قصيرًا يحتوي:
- الملفات التي تم تعديلها/إنشاؤها.
- migrations الجديدة.
- endpoints أو عقود API الجديدة/المتغيرة.
- أي إعدادات/ENV جديدة.
- نتيجة Build/Lint فقط.
- أي نقطة تعذر بناؤها بسبب عدم توفر toolchain، بدون تشغيل بيئة بديلة.
