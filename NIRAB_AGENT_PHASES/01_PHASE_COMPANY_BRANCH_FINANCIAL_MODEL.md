# NIRAB — المرحلة 1: تحويل نموذج Marketplace إلى شركة متعددة الفروع

## الهدف
تحويل المنطق الأساسي للمشروع من **Marketplace بمزودين مستقلين** إلى **شركة NIRAB واحدة ذات فروع**، مع الحفاظ على كيان `Provider` داخليًا في هذه المرحلة لتقليل مخاطر كسر المشروع، لكن اعتباره وظيفيًا `Branch` في الواجهات والصلاحيات والمنطق.

النموذج النهائي لهذه المرحلة:

```text
NIRAB / HQ Admin
├── Branch (internally Provider)
│   ├── Branch Manager
│   └── Technicians
├── Branch
└── Branch

100% of customer revenue belongs to NIRAB.
A branch is an operational/cost/revenue attribution unit, not an independent seller.
```

## مصادر السورس المطلوب مراجعتها قبل التعديل

### Backend / Admin
- `Modules/ProviderManagement/`
- `Modules/BookingModule/`
- `Modules/TransactionModule/`
- `Modules/BusinessSettingsModule/`
- `Modules/UserManagement/`
- `Modules/ServiceManagement/`
- `Modules/ZoneManagement/`
- `Modules/ReviewModule/`
- `Modules/ChattingModule/`
- `app/Http/Middleware/SubscriptionModalMiddleware.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Lib/Constant.php`

نقاط حالية معروفة في السورس يجب التعامل معها:
- `Booking.php` يضع `is_paid = 1` عند `completed` ويستدعي `update_admin_commission(...)`.
- `TransactionModule/Lib/Transaction.php` يستخدم `account_payable/account_receivable` لتسوية Admin/Provider.
- `Provider` مرتبط بـ `bookings`, `subscribed_services`, `servicemen`, `reviews` ولذلك **لا يُحذف تقنيًا الآن**.
- Middleware وإعدادات Business Plan تدعم Commission/Provider Subscription.
- توجد Withdraw flows وصلاحيات ومكونات UI مرتبطة بها.

### Customer App/Web
- `lib/feature/provider/`
- `lib/feature/booking/`
- `lib/feature/checkout/`
- `lib/feature/home/`
- `lib/feature/favorite/`
- `lib/feature/review/`
- `lib/feature/location/`

### Provider App
- `lib/feature/dashboard/`
- `lib/feature/booking_requests/`
- `lib/feature/booking_details/`
- `lib/feature/serviceman/`
- `lib/feature/reporting/`
- `lib/feature/payement_information/`
- `lib/feature/profile/`

## المطلوب تنفيذه

### 1. تعريف Branch Mode
أنشئ إعدادًا مركزيًا واضحًا مثل `nirab_branch_mode` أو ما يتوافق مع نمط BusinessSettings الحالي، ويكون هو الوضع الفعّال للمشروع.

عند تفعيله:
- `Provider` يُعامل كفرع داخلي.
- لا Commission بين الإدارة والفرع.
- لا Provider subscription business model.
- لا Provider wallet/withdrawal كمستحقات بائع.
- الإيراد 100% للشركة.

لا تخلط ذلك مع **CustomerSubscriptionModule**؛ اشتراكات العميل ستبقى لاستخدام الباقات والخدمات الدورية لاحقًا.

### 2. تحويل Provider وظيفيًا إلى Branch بدون حذف الكيان
لا تنفذ global rename متهورًا للجداول/classes.

المطلوب:
- إبقاء `providers` و`provider_id` داخليًا في هذه المرحلة.
- إضافة aliases/Resources/DTO/API fields عند الحاجة مثل `branch` و`branch_id` مع إبقاء `provider/provider_id` للتوافق.
- تحويل المصطلحات الظاهرة للمستخدم والإدارة من Provider إلى Branch/فرع حيث يخص NIRAB.
- Provider App يصبح وظيفيًا **Branch Manager App**.
- صفحات العميل الخاصة بالـProvider تتحول إلى صفحات **فرع NIRAB** وليست صفحات بائع مستقل.

إذا احتاج الفرع حقولًا إضافية، أضف migration مناسبة مثل:
- `branch_code` unique nullable أثناء الانتقال.
- `branch_priority`.
- `auto_assignment_enabled`.
- أي حقول تشغيلية لازمة لا توجد حاليًا، مع إعادة استخدام `zone_id`, coordinates/location والبيانات الحالية قبل إنشاء تكرار.

### 3. إزالة منطق Commission من دورة الحجز
ابحث عن جميع استدعاءات ومخرجات:
- `update_admin_commission`
- `admin_commission`
- `provider_earning`
- commission percentage/status

في Branch Mode:
- لا تُنشئ عمولة.
- لا تُنشئ provider earning كالتزام للشركة.
- إن كانت أعمدة `booking_details_amounts.admin_commission/provider_earning` لازمة للتوافق، اتركها بقيمة `0` واعتبرها deprecated، ولا تحذفها في هذه المرحلة.
- التقارير الجديدة لا تعتمد عليها.

### 4. إزالة التسويات المالية Admin ↔ Provider
راجع كل وظائف TransactionModule عند:
- Place booking.
- Complete booking.
- Digital payment.
- Cash after service.
- Partial payment.
- Repeat booking.
- Switch COD to digital.
- Refund/removal flows.

في Branch Mode يجب ألا ينتج:
- Provider `account_receivable` بسبب تنفيذ الخدمة.
- Admin `account_payable` للفرع.
- payable/receivable commission settlement بين HQ والفرع.

حافظ على ledger المطلوب لمدفوعات العميل إن كان مستخدمًا، لكن افصل **Customer payment accounting state** عن **Provider settlement**.

يفضل إنشاء Service واضح مثل:
- `NirabBookingFinancialService`
أو اسم يتوافق مع أسلوب المشروع، بحيث يصبح قرار الحساب المالي في مكان واحد وليس موزعًا في Observers/Models/Controllers.

### 5. فصل Completion عن Paid
السلوك الحالي يجعل `completed` يغيّر `is_paid` إلى 1. صحح ذلك.

المطلوب:
- `booking_status` = حالة تنفيذ الخدمة.
- `payment_status` أو الحالة الموجودة حاليًا = حالة الدفع.
- لا تجعل إكمال الخدمة يعني الدفع تلقائيًا.
- حافظ على `is_paid` للتوافق إن كان مستخدمًا، لكن حدّثه فقط من منطق الدفع الحقيقي وليس من Completion.
- تأكد أن العميل المؤسسي/الدفع الآجل يمكن أن يكون `completed` وغير مدفوع.

### 6. تعطيل Provider Subscription Business Model
في Branch Mode:
- لا تعرض Commission/Subscription plan للفرع.
- لا تمنع مدير الفرع من العمل بسبب Package/Subscription expiry.
- عطل/تجاوز `SubscriptionModalMiddleware` للفرع الداخلي بشكل واضح.
- أخفِ business-plan selectors من Admin/Branch UI.
- لا تحذف `CustomerSubscriptionModule`.

### 7. تعطيل Withdraw وBank Settlement الخاص بالبائع
المطلوب:
- إخفاء Withdraw من Branch Manager App والويب.
- منع API routes/Actions الخاصة بالسحب في Branch Mode بطريقة آمنة ومفهومة.
- إخفاء bank settlement/account payable/withdrawable amount من Dashboard الفرع.
- لا تحذف الكود القديم إن كان ذلك سيكسر upgrade/serialization؛ افصله خلف Branch Mode.

### 8. Dashboard الفرع
حوّل المؤشرات المالية من:
- Provider earning.
- Commission payable.
- Withdrawable balance.

إلى مؤشرات تشغيلية:
- إجمالي قيمة الحجوزات للفرع.
- المدفوع/غير المدفوع.
- عدد الطلبات حسب الحالة.
- عدد العملاء.
- عدد الفنيين النشطين.
- إيراد منسوب للفرع **للتقرير فقط**، وليس رصيدًا قابلًا للسحب.

لا تبنِ التقارير المتقدمة النهائية هنا؛ فقط صحح المعنى الحالي للداشبورد.

### 9. الصلاحيات والنطاق
طبّق قاعدة:
- HQ/Super Admin: جميع الفروع.
- Branch Manager: فرعه فقط.
- لا يستطيع Branch Manager قراءة/تعديل حجوزات وفنيي وتقارير فرع آخر.

استخدم Gates/Policies/scopes الحالية بدل الاعتماد فقط على إخفاء UI.

### 10. تجربة العميل مع الفروع
**لا تخفِ الفروع بالكامل.** المطلوب:
- العميل يرى فروع NIRAB القريبة أو داخل مدينته.
- يمكنه فتح صفحة الفرع والتواصل معه والحجز منه مباشرة.
- لا يرى الفروع على أنها بائعون مستقلون لهم عمولات/اشتراكات/سياسات منفصلة.
- Favorite Provider إذا بقي، أعد تسميته/معناه إلى Favorite Branch أو عطله إن لم يعد له قيمة، بدون كسر API فجأة.

اختيار الفرع التلقائي الذكي يُنفذ في المرحلة 2، لكن هذه المرحلة يجب أن تجعل نموذج البيانات والـAPI جاهزًا له.

## متطلبات عدم الكسر
- لا تحذف `provider_id` من Booking أو Cart أو Chat أو Review.
- لا تحذف Provider model/table.
- لا تغيّر Customer Subscription إلى Branch Subscription.
- لا تحذف PaymentModule أو TransactionModule؛ صحح المسارات المتعارضة فقط.
- Repeat Booking يجب أن يتبع نفس منطق Branch Mode.
- Guest bookings يجب ألا تتعطل.

## معايير قبول المرحلة
- لا يوجد مسار فعّال في Branch Mode ينشئ Commission أو Provider payable/receivable بسبب إتمام الطلب.
- إكمال الطلب لا يغيّر Paid تلقائيًا.
- Branch Manager لا يرى Withdraw/Subscription/Commission business UI.
- صفحات Provider الظاهرة للعميل أصبحت فروع NIRAB بالمصطلحات الصحيحة.
- HQ يرى الجميع، ومدير الفرع محصور في فرعه Backend-side.
- الخدمات والحجوزات الحالية ما زالت تعتمد على نفس Provider IDs داخليًا دون كسر علاقاتها.
- Customer Subscription لم يتأثر.


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
