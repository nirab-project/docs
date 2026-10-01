# NIRAB — المرحلة 2: الفروع الجغرافية، الفرق، السعة والإسناد الذكي

## المتطلب السابق
يجب أن تكون المرحلة 1 مدمجة: `Provider` أصبح وظيفيًا Branch، ولا توجد عمولات/سحوبات/اشتراك بائع فعّال.

## الهدف
بناء الطبقة التشغيلية الأساسية لـNIRAB:
- تحديد الفرع المسؤول جغرافيًا.
- السماح بالحجز المباشر من فرع أو الاختيار التلقائي.
- فني فردي أو فريق.
- مسؤول فريق.
- جداول/مناوبات/إجازات وسعة.
- ترتيب الفنيين حسب المسافة والتوفر والتقييم.
- إسناد يدوي سريع للمدير ثم Auto Assignment بعد 10 دقائق.
- قبول/رفض الفني وإعادة التوجيه.
- Live location وETA كأساس لتتبع العميل.

## مصادر السورس

### Backend
- `Modules/ZoneManagement/`
- `Modules/ProviderManagement/`
- `Modules/BookingModule/`
- `Modules/ServicemanModule/`
- `Modules/UserManagement/Entities/Serviceman.php`
- `Modules/ReviewModule/`
- `app/Console/Kernel.php`

معروف حاليًا:
- Zone يستخدم Polygon/Spatial ويحدد موقع العميل.
- Booking يحتوي `provider_id` و`serviceman_id` مفردًا.
- Provider لديه `service_capacity_per_day` لكنه ليس Capacity per technician/team.
- Provider app يستطيع Assign `serviceman_id` يدويًا.
- Serviceman الحالي لا يحتوي Live Location state كطبقة Dispatch.

### Customer App/Web
- `lib/feature/location/`
- `lib/feature/area/`
- `lib/feature/provider/`
- `lib/feature/booking/`
- `lib/feature/checkout/`
- `lib/feature/home/`

### Branch Manager App
- `lib/feature/booking_requests/`
- `lib/feature/booking_details/`
- `lib/feature/serviceman/`
- `lib/feature/dashboard/`
- `lib/feature/location/`

### Serviceman App
- `lib/feature/booking_request/`
- `lib/feature/booking_details/`
- `lib/feature/dashboard/`
- `lib/feature/profile/`
- `lib/feature/notifications/`

## المطلوب تنفيذه

### 1. طبقة المناطق التابعة للفروع
لا تعتمد فقط على اسم المدينة.

استخدم Zone الحالية كطبقة جغرافية واسعة، وأضف إن لزم بنية فرعية قابلة للرسم مثل:
- `branch_service_areas`
  - `id`
  - `provider_id` (branch)
  - `zone_id`
  - `name`
  - `area_type` (`primary`, `neighborhood`, `overflow` أو ما يلزم)
  - polygon/spatial geometry وفق محرك DB الحالي
  - `priority`
  - `is_active`

لا تغيّر محرك DB في هذه المرحلة. استمر على Spatial الموجود حاليًا، واعزل عمليات spatial داخل Repository/Service حتى يمكن تبديل المحرك لاحقًا.

### 2. Branch Assignment Engine
أنشئ Service مركزيًا يحدد الفرع بالترتيب التالي:
1. فرع تغطي polygon الخاصة به موقع العميل.
2. الخدمة متاحة في الفرع.
3. الفرع نشط وفي وقت عمل مناسب.
4. يوجد Capacity للموعد المطلوب.
5. إن تطابق أكثر من فرع: رتب حسب الأولوية ثم ETA/distance ثم capacity/load.
6. إذا الموقع خارج كل المناطق أو بين المناطق: استخدم أقرب فرع مؤهل داخل Zone/نطاق مسموح.

يجب أن يدعم مسارين:
- `branch_id/provider_id` يختاره العميل مباشرة من صفحة الفرع → احترمه إن كان صالحًا للخدمة والموقع.
- لا يرسل العميل فرعًا → النظام يحدد تلقائيًا.

لا تعد إلى نموذج إرسال الطلب لجميع الفروع وانتظار أول قبول.

### 3. الفرق
أضف نماذج/جداول واضحة مثل:
- `teams`
- `team_members`
- `booking_assignments` أو `booking_servicemen`

الحد الأدنى:
- Team تابع لفرع واحد.
- Team لديه Leader واحد فقط.
- أعضاء الفريق من فنيي نفس الفرع.
- عدد الفريق الافتراضي 2 عندما ينشأ فريق جديد ما لم يحدد غير ذلك.
- يمكن تعطيل فريق دون حذف تاريخه.

أضف على الخدمة/الحجز ما يلزم لدعم:
- `execution_type = single | team`
- `required_technicians` أو min/default/max حسب التصميم.

حافظ على `bookings.serviceman_id` كـLead/Primary assignee مؤقتًا للتوافق، لكن مصدر الحقيقة لأعضاء الفريق يصبح جدول assignments.

### 4. Skills / Eligibility
لا يكفي أن يكون الفني قريبًا.

أضف علاقة Skills/Service eligibility تسمح بمعرفة الفني المؤهل للخدمة، مثل:
- `serviceman_skills`
- service/subcategory mapping حسب أنسب مستوى للمشروع.

Eligibility يجب أن تشمل:
- نفس الفرع.
- active.
- on shift.
- skill مناسب.
- لا يوجد تعارض زمني.
- لا يتجاوز capacity.
- cash eligibility لاحقًا في المرحلة 4.

### 5. جداول الفنيين والفرق
أضف:
- Shifts/working hours.
- Leaves/time off.
- Booked intervals.
- Team schedule derives from members أو leader وفق التصميم.

الخدمة يجب أن تملك مدة تقديرية (إن لم تكن موجودة أضفها الآن كأساس، ويمكن إدارة UI التفصيلي في المرحلة 3).

عند احتساب slot:
- مدة الخدمة.
- Travel buffer قابل للإعداد.
- حجوزات أخرى.
- إجازة/مناوبة.

### 6. Availability API للعميل
بدل عرض مواعيد نظرية فقط:
- العميل يرى slots المتاحة فعليًا في الفرع المحدد/المختار تلقائيًا.
- لا يسمح backend بحجز slot أصبح ممتلئًا حتى لو كانت UI قديمة.
- أضف locking/transaction مناسب لمنع double booking قدر الإمكان بدون الاعتماد على UI.

### 7. Smart Dispatch للمدير
عند وصول Booking لفرع:
- اعرض قائمة فنيين/فرق مؤهلين مرتبة حسب:
  1. availability
  2. distance/ETA عندما يكون الطلب فوريًا
  3. rating
  4. workload/capacity
- يعرض سبب الاستبعاد أو مؤشر الحالة بوضوح.
- مدير الفرع يعتمد المرشح بضغطة واحدة.

لا تجعل UI هي التي تحسب الترتيب؛ الترتيب يأتي من backend service حتى يكون موحدًا.

### 8. مهلة 10 دقائق وAuto Assignment
عند إنشاء/وصول Booking غير مسند:
- سجل `assignment_due_at = created/received + 10 minutes` أو بنية مكافئة.
- أنشئ Job/Command/Service يعالج الطلبات المتأخرة.
- بعد المهلة اختر أقرب/أفضل فني متاح وفق Dispatch Engine.
- للـTeam job اختر Team/Leader مؤهلًا.

سجّل الحدث في assignment history.

**مهم:** نفّذ الكود والجدولة فقط؛ لا تشغّل queue/scheduler أثناء هذه المرحلة.

### 9. قبول/رفض الفني
بعد الإسناد:
- الفني يحصل على الطلب.
- يمكنه Accept أو Reject.
- Reject يتطلب reason.
- عند الرفض يسجل history ويعاد Dispatch للمرشح التالي.
- امنع loop لنفس الفني على نفس Booking عبر assignment attempts/history.
- يمكن وضع حد attempts ثم إعادة الطلب لمدير الفرع مع Alert.

### 10. Live Location
أضف endpoint آمن لتطبيق الفني يرسل location أثناء:
- on-duty إذا وافق تصميم الخصوصية.
- أو على الأقل من لحظة قبول/On the way وحتى الوصول/الانتهاء.

خزن آخر موقع بشكل مناسب:
- `latitude`
- `longitude`
- `accuracy` إن توفر
- `captured_at`
- `booking_id`/session context عند الحاجة

لا تستخدم كل نقطة تاريخية في جدول رئيسي ثقيل. إن أردت history افصلها عن last-known location.

### 11. Customer Tracking + ETA
في Booking tracking:
- عند `on_the_way` يعرض آخر موقع للفني/قائد الفريق.
- يعرض ETA من backend/provider map service abstraction، وليس حسابًا ثابتًا في UI.
- لا تكشف رقم الهاتف الشخصي للفني.
- Team job يتتبع Leader افتراضيًا.

### 12. Late Arrival Alert
عند تجاوز ETA/موعد الوصول بهامش قابل للإعداد:
- أنشئ alert للـBranch Manager.
- سجل alert/event ولا ترسل spam متكررًا؛ استخدم flag أو last_alert_at.

### 13. API/UI للفرق
Branch Manager App:
- إنشاء/تعديل Team.
- اختيار Leader.
- إدارة الأعضاء.
- رؤية availability.
- Assign single technician أو team.

Serviceman App:
- Leader يرى المهمة الجماعية وأعضاء الفريق.
- Member يرى المهمة التي هو عضو فيها.
- الصلاحيات التنفيذية الأساسية (Start/Finish لاحقًا) تكون للLeader، مع إبقاء العرض للأعضاء.

Customer App:
- لا يختار الفني.
- يمكنه اختيار الفرع أو ترك NIRAB تختاره.
- لا يحتاج معرفة تفاصيل أعضاء الفريق قبل الإسناد إلا إن قررنا عرض الأسماء لاحقًا.

## حالات يجب دعمها
- Immediate booking.
- Scheduled booking.
- Repeat booking.
- Single technician.
- Team.
- Branch manually selected.
- Branch auto selected.
- Technician rejects.
- No eligible technician → manager alert/pending assignment.
- Outside polygon → nearest eligible branch fallback.

## معايير قبول المرحلة
- Booking يمكن إسناده لفني واحد أو فريق بدون كسر `serviceman_id` القديم.
- Branch Assignment لا يرسل الطلب لكل الفروع للمنافسة.
- المدير يرى مرشحين مرتبين من backend.
- Auto Assignment logic موجود بعد 10 دقائق وقابل للتشغيل بواسطة scheduler/queue لاحقًا.
- الفني يستطيع Accept/Reject مع reason وfallback.
- Availability يمنع double booking backend-side قدر الإمكان.
- تطبيق العميل يستطيع الحصول على slots فعلية وتتبع آخر موقع/ETA عندما يكون الفني في الطريق.
- مدير الفرع لا يرى أو يدير فرق فرع آخر.


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
