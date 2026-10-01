# NIRAB — المرحلة 3: الخدمات، الحقول الديناميكية، التسعير الطبقي وعروض الأسعار

## المتطلبات السابقة
- المرحلة 1: Branch Model والمال المركزي.
- المرحلة 2: Branch Assignment + Teams + Availability/Dispatch foundations.

## الهدف
تحويل Catalog وخط التسعير ليخدم نوعين بوضوح:
1. **Fixed Price**: العميل يرى السعر ويحجز مباشرة.
2. **Quote Required**: العميل يرسل تفاصيل/صور/قياسات، ومدير الفرع يصدر عرض سعر صالح 48 ساعة وقد يطلب معاينة مجانية أو مدفوعة.

مع بناء Layered Pricing Engine يدعم:
- Base service price.
- Branch factor.
- Neighborhood factor.
- Peak factor.
- Discounts/Coupons.
- خدمة متاحة/غير متاحة حسب منطقة جغرافية.

## مصادر السورس

### Backend
- `Modules/ServiceManagement/`
- `Modules/CategoryManagement/`
- `Modules/ZoneManagement/`
- `Modules/PromotionManagement/`
- `Modules/CartModule/`
- `Modules/BookingModule/`
- `Modules/BidModule/`

معروف حاليًا:
- Service Variations لها `zone_id` وprice.
- Cart يدعم quantity/variant.
- BidModule يحتوي `posts`, `post_bids`, `offered_price`, `provider_note`, `service_description`, additional information.
- BidModule الحالي مصمم لتنافس Providers، وهذا يجب تحويله إلى Quotation من فرع NIRAB.

### Customer App/Web
- `lib/feature/service/`
- `lib/feature/cart/`
- `lib/feature/checkout/`
- `lib/feature/create_post/`
- `lib/feature/my_post/`
- `lib/feature/booking/`
- `lib/feature/provider/`

### Branch Manager App
- `lib/feature/custom_post/`
- `lib/feature/service_details/`
- `lib/feature/booking_requests/`
- `lib/feature/booking_details/`

## المطلوب تنفيذه

### 1. Pricing Type على الخدمة
أضف حقلًا واضحًا:
- `fixed`
- `quote`

يمكن إضافة `starting_from` لاحقًا فقط إذا كان له استخدام فعلي، ولا توسع النطاق بلا حاجة.

سلوك backend:
- Fixed: السعر يحسب من Pricing Engine ثم يمكن الحجز/الدفع.
- Quote: لا يسمح بإنشاء Booking مدفوع بسعر 0 أو سعر وهمي؛ ينشأ Quote Request أولًا ثم Booking بعد قبول العرض.

### 2. مدة الخدمة والتنفيذ
اربط Service ببيانات التشغيل التي تحتاجها المرحلة 2:
- estimated duration.
- execution type default: single/team.
- min/default/max technicians عند Team إن كان المستوى الأنسب على Service/Variation.

اجعل Override على Quote ممكنًا لأن المهمة الكبيرة قد تحتاج عدد فنيين مختلفًا.

### 3. Dynamic Service Fields
أنشئ بنية مرنة، مثل:
- `service_fields`
- `service_field_options`
- `booking/quote_field_values`

الأنواع المطلوبة على الأقل:
- text
- textarea
- number/decimal
- select
- multi_select
- radio
- checkbox
- boolean
- measurement + unit
- date/time عند الحاجة
- image/file

لكل field:
- service_id
- label translations إن كان المشروع يدعم translation table/naming pattern.
- type
- required
- sort_order
- validation rules/min/max إن لزم
- unit/options
- active

Customer App يبني النموذج من API ديناميكيًا ولا hardcode أسئلة لكل خدمة.

### 4. Service Availability حسب الفرع/المنطقة
استخدم علاقات Branch/Service الحالية (`subscribed_services` إن كانت مناسبة) لكن غيّر معناها وظيفيًا إلى **Branch Service Availability**.

أضف إمكانية:
- خدمة مفعلة للفرع.
- خدمة مفعلة فقط في neighborhood/service area معين.
- إيقاف خدمة في منطقة بضغطة واحدة.

لا تستخدم مفهوم “Provider اشترى/اشترك في الخدمة”.

### 5. Layered Pricing Engine
أنشئ Service مركزيًا يعيد Breakdown واضحًا.

ترتيب الحساب المطلوب:
```text
Base Price
→ Branch adjustment/factor
→ Neighborhood adjustment/factor
→ Peak-time adjustment/factor
→ Discount/Campaign/Coupon
→ Tax/other existing booking calculations in the correct existing order
```

لا تكرر المنطق بين Cart وCheckout وBooking. كلهم يجب أن يستخدموا نفس Pricing Engine/DTO.

### 6. قواعد التسعير
أضف data model مناسبًا مثل:
- `pricing_rules`
أو جداول منفصلة إذا أوضح.

يجب دعم:
- branch rule.
- neighborhood/area rule.
- peak rule بوقت بداية/نهاية وأيام/تواريخ محددة.
- percentage أو fixed adjustment إذا لزم.
- priority.
- active/date range.

احتفظ بالـVariation base prices الموجودة واستفد منها بدل نسخ الأسعار.

### 7. Bulk Pricing
Admin/HQ يستطيع:
- تعديل معامل/زيادة فرع كامل.
- تعديل حي/منطقة كاملة.
- إنشاء Peak rule لفترة.

الهدف ألا يضطر لتعديل كل Service row يدويًا.

### 8. Price Audit
كل تغيير في Pricing Rule/Base mapping يجب أن يسجل:
- actor.
- old value.
- new value.
- entity/rule.
- timestamp.
- optional reason.

يمكن استخدام Audit foundation من المرحلة 4 لاحقًا، لكن إذا لم تكن موجودة بعد أنشئ Price history صغيرًا قابلًا للدمج لاحقًا ولا تؤجل ميزة مطلوبة.

### 9. تحويل BidModule إلى Quotation Workflow
لا تحذف BidModule إن كان يوفر أساسًا جيدًا.

غيّر المعنى:
```text
Old:
Customer Post → Multiple Providers Bid → Customer chooses provider

New:
Customer Quote Request → Assigned Branch → Branch Manager prepares one active official quotation → Customer Accept/Reject/Expire
```

في Branch Mode:
- لا تُظهر الطلب لجميع الفروع للمنافسة.
- Quote request مرتبط بفرع واحد يحدده Branch Assignment أو اختيار العميل.
- يمكن الاحتفاظ بـ`post_bids` داخليًا مؤقتًا، لكن امنع multiple competing branch bids.

### 10. صور ووصف طلب عرض السعر
Quote Request يدعم:
- description.
- dynamic field values.
- multiple images/files.
- customer address/location.
- requested schedule/window إن توفر.

تأكد من file validation والحجم وفق helper الموجود في المشروع.

### 11. المعاينة Inspection
مدير الفرع يختار:
- Quote directly from submitted info/photos.
- Inspection required.

Inspection:
- free أو paid.
- amount إذا paid.
- schedule/booking-like visit.
- status.
- إذا paid inspection وتم قبول final quote، تخصم قيمة المعاينة من final invoice/booking amount **مرة واحدة فقط** وبشكل auditable.

لا تستخدم Additional Charge بشكل مبهم إذا كان سيصعب التتبع؛ أضف field/type واضحًا.

### 12. Quotation Entity/Fields
وسّع Post/Bid أو أنشئ Quotation entity نظيفة حسب أقل تغيير مخاطرة.

يجب توفر:
- quote number/readable id.
- branch id.
- offered price.
- note/terms.
- estimated duration.
- required technicians/team size override.
- inspection deduction.
- issued_at.
- expires_at = 48 hours افتراضيًا.
- accepted_at/rejected_at/expired_at.
- status.

### 13. Expiration 48 Hours
أضف Command/Job لمعالجة العروض المنتهية أو احسب status عند القراءة مع عملية cleanup مناسبة.

- بعد 48 ساعة يصبح Expired ولا يمكن دفعه/قبوله.
- يمكن للفرع إصدار نسخة/Revision جديدة بسجل تاريخي.

نفذ الكود فقط ولا تشغّل scheduler/queue.

### 14. قبول العرض وتحويله إلى Booking
Customer Accept:
- تحقق أن Quote active وغير منتهٍ.
- ثبّت السعر snapshot فلا يتغير بعد قبول العرض بسبب تغيير pricing rule لاحق.
- أنشئ Booking مرتبطًا بالQuote.
- branch_id/provider_id ثابت.
- service details/dynamic answers محفوظة/snapshotted.
- يذهب العميل للدفع أو إلى payment flow المناسب.

لا تسمح بإعادة القبول وإنشاء حجوزات مكررة؛ استخدم idempotency/unique relation.

### 15. واجهات العميل
Service Card/Details:
- Fixed: يعرض السعر/starting breakdown حسب الموجود ويتيح Add to cart/book.
- Quote: يعرض “طلب عرض سعر” ويظهر dynamic form + images.

My Quote Requests:
- Submitted.
- Under Review.
- Inspection Required/Scheduled.
- Quote Ready.
- Accepted.
- Rejected.
- Expired.

### 16. واجهة مدير الفرع
- قائمة Quote Requests لفرعه فقط.
- فتح الصور والبيانات.
- Direct quote أو Inspection.
- تحديد السعر والمدة وعدد الفنيين/الفريق.
- إصدار Revision.
- رؤية expiration/status.

## معايير قبول المرحلة
- Fixed Service ما زالت تحجز طبيعيًا عبر Pricing Engine المركزي.
- Quote Service لا تدخل Cart كخدمة سعرها صفر.
- Dynamic fields تظهر من API وتُحفظ قيمها.
- Branch/Neighborhood/Peak pricing قابل للتكوين ويعطي breakdown ثابتًا وقابلًا للتدقيق.
- لا يوجد bidding تنافسي بين الفروع في Branch Mode.
- Quote تنتهي بعد 48 ساعة منطقيًا ولا يمكن قبول المنتهي.
- Inspection free/paid مدعومة مع خصم paid inspection من الفاتورة النهائية عند القبول مرة واحدة.
- قبول Quote ينشئ Booking واحدًا بسعر snapshot صحيح.


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
