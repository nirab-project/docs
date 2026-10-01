# NIRAB — المرحلة 6: الجودة، التواصل، الباقات، التقارير، الأمان والبنية التشغيلية

## المتطلبات السابقة
المراحل 1–5 مدمجة. هذه المرحلة تكمل متطلبات المنتج والتشغيل والإطلاق دون إعادة فتح منطق العمولات/Marketplace.

## الهدف
إكمال:
- تقييم الفني والخدمة.
- الشكاوى والضمان وإعادة الخدمة.
- الباقات/الاشتراكات الدورية للعميل والزيارات مسبقة الدفع.
- SMS سعودي وWhatsApp Business.
- إشعارات كاملة مع حماية أرقام الأطراف.
- تقارير HQ والفرع وHeatmap.
- Redis/cache/queue operational configuration.
- backups/DR hooks.
- Security/PDPL-oriented code hardening.
- database/spatial portability ومتطلبات PostgreSQL/PostGIS إن كان اعتماد العميل النهائي يفرضه.
- تحسينات scalability بالـindexes/pagination/cache بدون Load Test.

## مصادر السورس

### Backend
- `Modules/ReviewModule/`
- `Modules/ChattingModule/`
- `Modules/SMSModule/`
- `Modules/CustomerSubscriptionModule/`
- `Modules/BookingModule/`
- `Modules/CustomerModule/`
- `Modules/ProviderManagement/`
- `Modules/ServicemanModule/`
- `Modules/ZoneManagement/`
- `Modules/BusinessSettingsModule/`
- `app/Console/Kernel.php`
- `app/Console/Commands/DatabaseBackup.php`
- `config/cache.php`, `config/queue.php`, `config/database.php`

### Flutter apps
Customer:
- review, subscription, conversation, notification, booking, provider/branch, wallet-related UI.

Branch Manager:
- review, reporting, conversation, notifications, dashboard.

Serviceman:
- conversation, notifications, dashboard/profile.

## المطلوب تنفيذه

### 1. تقييم الفني والخدمة
المراجعة الحالية مرتبطة غالبًا بـService/Provider/Booking.

أضف دعم تقييم مستقل/واضح لـ:
- technician/leader.
- service.
- branch إذا تقرر الاحتفاظ به.

لا تسمح بتقييم فني غير مشارك في Booking.
Team job:
- يمكن تقييم Leader أو الفريق حسب UX المحدد، لكن يجب أن يكون هناك `serviceman_id`/target واضح يمكن أن يدخل في Dispatch score.

### 2. Low-rating Ticket
بعد Rating أقل من 3 (setting قابل للتعديل، default 3):
- افتح complaint/service ticket تلقائيًا.
- اربطه بالBooking/Customer/Branch/Technician.
- notify Branch Manager.
- idempotent: لا تفتح عدة tickets لنفس rating event.

### 3. Complaints
أنشئ workflow:
- open.
- under_review.
- resolved.
- rejected/closed.

دعم:
- customer description.
- attachments.
- internal notes.
- assigned branch/customer service.
- timestamps/SLA fields إن لزم.

Customer Service role:
- قراءة ومتابعة كل الطلبات/الشكاوى حسب scope.
- لا يستطيع تعديل الأسعار بدون permission صريح.

### 4. Warranty / Free Re-service
إذا ثبت التقصير:
- Branch Manager/HQ ينشئ warranty re-service booking مجانًا.
- مرتبط بالoriginal booking/complaint.
- لا يكرر revenue/payment.
- يمر عبر schedule/dispatch الطبيعي.
- Audit reason/approver.

أضف warranty window configurable per service أو setting عام.

### 5. تأثير التقييم على Dispatch
في Dispatch Engine من المرحلة 2:
- rating factor يدخل ranking فقط ضمن مرشحين eligible.
- لا يسمح لتقييم أعلى بتجاوز عدم التوفر/عدم المهارة.
- weights configurable، ولا hardcode formula في UI.

### 6. Customer Packages / Subscriptions
استخدم `CustomerSubscriptionModule` وRepeat Booking حيث يناسب.

دعم:
- weekly cleaning subscription.
- monthly subscription.
- prepaid visit packages مثل 4/8/12 visits.
- `remaining_visits` ledger/usage.
- discount/pricing snapshot أو policy واضح.
- renewal date.
- auto-renew flag.
- reminder before charge.

لا تعِد تفعيل Provider Subscription الذي ألغي في المرحلة 1.

### 7. Package Usage Safety
- استخدام زيارة يجب أن يكون idempotent.
- canceled booking وفق policy يعيد الزيارة إذا لم تُنفذ.
- completed booking يستهلك الزيارة.
- لا يصبح الرصيد سالبًا.
- Branch attribution موجود على booking وليس package seller.

### 8. Saudi SMS Provider Abstraction
SMSModule الحالي يحتوي مزودين عامين.

أضف Adapter سعودي configurable مثل:
- Unifonic.
- Taqnyat.

لا hardcode مزود واحد في OTP business logic.

Interface موحد:
- send OTP.
- send transactional message إن احتجنا.
- normalize response/error.

أضف ENV/Business Settings بدون credentials حقيقية.

لا ترسل SMS حقيقي أثناء التنفيذ.

### 9. WhatsApp Business API
أضف integration abstraction لإرسال:
- booking confirmation.
- appointment reminder.
- invoice ready/delivery link.

لا تستخدم روابط `wa.me` كبديل عن API المطلوب.

أنشئ:
- template mapping/settings.
- outbox/jobs/retry.
- delivery status إذا provider يدعمه.
- opt-in/consent fields إن كانت السياسة التشغيلية تتطلب.

لا تتصل بخدمة حقيقية أثناء التنفيذ.

### 10. Notification Matrix
كل تغيير مهم في Booking يرسل push/in-app حسب existing notification architecture:
- assigned.
- technician accepted/rejected/reassigned.
- on the way.
- arrived.
- work finished/completed.
- quote ready/expiring.
- payment approved/rejected.
- invoice ready.

امنع duplicate spam عبر event keys/idempotency حيث مناسب.

### 11. إخفاء أرقام الجوال
راجع API serializers/models/chat/customer/serviceman cards.

العميل والفني يتواصلان عبر Chat دون كشف phone number إلا لدور/حالة مصرح بها.
- لا يكفي إخفاء الرقم في UI؛ لا ترسله API إن لم يكن مسموحًا.
- Branch/HQ permissions منفصلة.

### 12. HQ Analytics
Admin dashboard/reports:
- branch revenue/value.
- orders by branch.
- most requested services.
- revenue/orders by neighborhood/service area.
- completion/cancellation.
- quote conversion.
- payment methods.
- cash custody exposure.

لا تعتمد على `provider_earning/admin_commission` القديمة.

### 13. Branch Analytics
Branch Manager:
- technician productivity.
- jobs per technician/team.
- cancellation rate.
- average assignment time.
- average arrival time.
- average actual service duration.
- repeat customers.
- pending payment approvals/custody.

يجب تطبيق branch scope backend-side.

### 14. Demand Heatmap
أنشئ API/report يجمع Bookings حسب coordinates/geo area/time range.

UI Admin:
- Heatmap أو map layer للطلبات.
- filters date/service/status/branch.
- لا ترسل مئات آلاف points raw؛ دعم aggregation/grid/area buckets حسب zoom/range.

### 15. Indexes / Query Hardening
راجع الجداول الجديدة والكبيرة وأضف indexes للحقول المستخدمة في:
- booking status + branch + schedule.
- technician assignment/status.
- location latest lookup.
- payment approval status.
- Odoo sync status.
- quote status/expires_at.
- audit entity/time.
- wallet/accounting references.

استخدم pagination في lists ولا تحمل relations ضخمة بلا حاجة.

### 16. Redis / Cache / Locks
اجعل الكود يدعم production config:
- Redis cache.
- Redis queue.
- distributed locks للحجز/slot/idempotency عند الحاجة.

لا تجعل Redis شرطًا لتشغيل development إن كان يمكن fallback بشكل آمن، لكن production docs/config يجب أن تكون واضحة.

لا تشغّل Redis أثناء التنفيذ.

### 17. Backups
المشروع يملك backup command/package لكن scheduler الحالي يحتاج استكمال.

أضف:
- scheduled daily database backup command.
- configurable time.
- retention config.
- support off-site/object storage via existing storage abstraction إذا أمكن.
- logging/notification عند failure.

لا تشغّل backup فعليًا.

### 18. Disaster Recovery Hooks/Documentation
أضف ملف تشغيل داخل المشروع مثل `docs/DISASTER_RECOVERY.md` يوضح:
- DB backup source.
- object storage backup/versioning assumption.
- restore order.
- required secrets/config (names only).
- queue recovery.
- Odoo sync replay/idempotency.

لا تنفذ Restore فعليًا.

### 19. Database Target: PostgreSQL/PostGIS
وثيقة العميل تنص على PostgreSQL + PostGIS بينما السورس الحالي يستخدم MySQL Spatial وMySQL-specific paths.

نفّذ هذه المهمة **فقط إذا كان اعتماد المشروع النهائي يفرض PostGIS**؛ لا تغيّر المحرك خلسة.

إذا المطلوب PostGIS:
- اعزل Spatial operations وراء `GeoRepository/GeoService` إن لم تكن معزولة.
- أزل MySQL-specific raw SQL من domain logic أو وفر driver-specific implementation.
- حدّث database migrations/spatial types لتعمل على PostgreSQL/PostGIS.
- حدّث backup driver/command الذي يفترض MySQL.
- حدّث composer dependency إذا كانت spatial library الحالية لا تدعم PostGIS، بعد فحص توافقها مع Laravel 12/PHP 8.2.
- أضف `.env.example` target config.
- أنشئ migration/runbook document للانتقال من MySQL إلى PostgreSQL، لكن **لا تشغّل migration بيانات فعلية** في هذه المرحلة.

إذا لم يكن PostGIS معتمدًا نهائيًا:
- لا تنفذه؛ اترك MySQL 8 Spatial واعمل فقط abstraction/portability الضرورية.

### 20. PDPL-oriented Hardening
هذه ليست شهادة امتثال قانوني، لكن نفذ code safeguards:
- least-data API serialization.
- location access only للأدوار والحجز المناسب.
- do not expose exact technician/customer location outside active need.
- storage access/private URLs where possible.
- audit privileged data access/changes حيث مناسب.
- retention settings/hooks للlocation/evidence logs بدل الاحتفاظ غير المحدود بلا سياسة.
- redact secrets/tokens/payment data from logs.

### 21. هدف 100k–500k مستخدم
لا تدّعي إثبات القدرة بدون Load Test؛ المستخدم لا يريد Load Tests الآن.

نفذ فقط code-level readiness:
- pagination.
- indexes.
- queue jobs للأعمال الثقيلة.
- caching appropriate reference data.
- no N+1 في endpoints الحرجة قدر الإمكان.
- chunking for background batch jobs.
- aggregated heatmap/report queries.
- idempotent async integrations.

أضف `docs/SCALING_NOTES.md` يوضح ما يحتاج اختبار أداء قبل الإنتاج، **بدون تشغيله**.

### 22. الاستضافة داخل المملكة
لا تحاول provisioning cloud من السورس.
أضف `docs/DEPLOYMENT_REQUIREMENTS_SA.md` يحتوي أسماء المتطلبات:
- Saudi region hosting requirement.
- DB/Redis/object storage.
- TLS.
- backups.
- monitoring.
- secrets manager.
- worker/scheduler topology.

لا تضع vendor افتراضيًا إلا إذا كان المشروع اختاره صراحة.

## معايير قبول المرحلة
- Rating يمكن أن يستهدف الفني ويؤثر على Dispatch ranking.
- أقل من 3 يفتح ticket تلقائيًا مرة واحدة.
- Warranty re-service مدعومة بدون revenue جديد.
- Customer subscriptions/packages لا تعيد Provider Subscription.
- Unifonic/Taqnyat abstraction موجودة وWhatsApp Business outbox موجود بدون اتصالات حقيقية.
- phone numbers محمية Backend-side.
- HQ/Branch reports لا تعتمد على commission/provider earning.
- Heatmap تستخدم aggregation وليس raw unlimited points.
- daily backup schedule/config موجود بدون تشغيله.
- Redis/queue/cache production config جاهز.
- PostGIS requirement إما منفذ كطبقة توافق/كود إن تم اعتماده، أو موثق بوضوح كقرار غير مفعّل؛ لا migration مفاجئ.
- توجد docs للDR/scaling/deployment.


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
