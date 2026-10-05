# NIRAB — المرحلة 7: الإغلاق الأمني وتنظيف بقايا Demandium

## الهدف
إغلاق المخاطر البرمجية وبقايا منطق Marketplace/Provider Subscription قبل الانتقال إلى Code Freeze، بدون إضافة خصائص تشغيلية جديدة.

هذه المرحلة يجب أن تجعل السورس آمنًا وواضحًا في **Branch Mode**، وتمنع أي جزء Legacy من Demandium من تعطيل مدير الفرع أو إعادة مفهوم Provider المستقل.

---

## نطاق المرحلة

### Backend / Admin
ابدأ بمراجعة:
- `routes/web.php`
- جميع ملفات Routes داخل Modules.
- Middleware المرتبط بالـProvider Subscription / Business Plan.
- Controllers الخاصة بـProvider Subscription / Withdraw / Commission.
- API Resources التي تعيد بيانات العميل أو الفني.
- Chat/Booking/Provider/Serviceman responses.
- `.env.example`
- ملفات Config الجديدة الخاصة بـNIRAB.
- أي Routes أو Controllers تجريبية بقيت بعد التطوير.

### Provider / Branch Manager App
راجع خصوصًا:
- `BusinessSubscriptionController`
- كل استدعاءات:
  - `openTrialEndBottomSheet()`
  - subscription expiry checks
  - business plan checks
  - commission/subscription status checks
- Dashboard / Profile / Payment Information / Reports / Booking flows.

### Customer / Serviceman Apps
راجع فقط ما يتعلق بـ:
- كشف أرقام الجوال أو بيانات حساسة غير لازمة.
- أي نصوص قديمة تظهر Provider كبائع مستقل.
- Debug/test endpoints أو flags.

---

## المطلوب تنفيذه

### 1. إزالة أو تأمين `/image-proxy`
تم العثور سابقًا على Route يسمح بطلب URL يحدده المستخدم من خلال السيرفر.

المطلوب:
- الخيار المفضل: **حذف الـRoute بالكامل** إذا لم يعد ضروريًا.
- إذا كان مطلوبًا فعليًا لميزة مستخدمة، يجب تأمينه بـAllowlist صارمة للدومينات المسموح بها فقط.
- امنع:
  - `localhost`
  - `127.0.0.0/8`
  - `0.0.0.0`
  - Private IPv4 ranges
  - Link-local addresses
  - Cloud metadata endpoints
  - Private/internal hostnames
  - غير `https` إن لم تكن هناك حاجة صريحة
  - Redirects التي تنتهي إلى عنوان داخلي
- لا تعتمد على Regex بسيط على النص فقط؛ استخدم validation واضح وآمن.
- أضف Timeout وحدًا معقولًا لحجم الاستجابة إذا بقي الـproxy.
- لا تجعل الـRoute عامًا بلا Rate Limit/Authorization إذا كانت وظيفته داخلية.

### 2. حذف Routes التجريبية
احذف:
- `/test`
- وأي Route تجريبي/تشخيصي مشابه لا يدخل في المنتج النهائي.

ابحث أيضًا عن:
- debug endpoints
- temp endpoints
- sample routes
- development-only routes

ولا تحذف Health endpoint فعليًا إذا كان مستخدمًا للتشغيل؛ فقط فرّق بين Health رسمي وRoute تجريبي.

### 3. إغلاق Provider Subscription/Trial في Branch Mode
في تطبيق الفرع يجب أن يكون Branch Mode حاجزًا نهائيًا أمام كل منطق Provider Subscription.

المطلوب:
- أي دالة مثل `openTrialEndBottomSheet()` يجب أن تُرجع نجاحًا/تجاوزًا مباشرة عندما يكون `nirabBranchMode == true`.
- لا تعرض Trial expired.
- لا تعرض Buy Subscription.
- لا تمنع قبول Booking أو إدارة الفنيين أو فتح الشاشات بسبب Subscription.
- لا تعرض Business Plan selector.
- لا تعتمد فقط على إخفاء UI؛ أغلق المنطق في Controllers/Guards أيضًا.

ابحث عن جميع الكلمات/المفاهيم التالية في Provider App والباكند:
```text
subscription_base
commission_base
provider_subscription
subscription_expired
trial
business_plan
withdraw
withdrawable
commission
provider_earning
```

وصحح كل مسار فعال في Branch Mode.

### 4. التأكد من تعطيل Legacy financial routes في Branch Mode
راجع Routes/Actions الخاصة بـ:
- Provider Withdraw.
- Provider bank settlement.
- Provider subscription purchase/switch.
- Commission settings الخاصة بالمزود المستقل.
- Provider payable/receivable settlement.

المطلوب:
- في Branch Mode تُرفض Backend-side برسالة واضحة أو تُعطّل عبر Middleware/Feature Guard.
- لا يكفي إخفاؤها من الواجهة.

لا تحذف الكود القديم إذا كان الحذف يسبب كسرًا غير ضروري؛ اعزله خلف Branch Mode.

### 5. إزالة بقايا Marketplace من النصوص الرئيسية
في واجهات العميل والفرع:
- استبدل Provider الظاهر للمستخدم بـBranch/فرع حيث المقصود فرع NIRAB.
- لا تغيّر أسماء Models/Tables الداخلية بلا داعٍ.
- لا تجعل العميل يعتقد أن الفرع بائع مستقل.
- لا تعرض Commission/Subscription/Earnings/Withdraw terminology للفرع.

### 6. حماية بيانات الاتصال
راجع API Resources وJSON responses وواجهات التطبيقات.

الهدف:
- العميل لا يحصل على رقم الفني الشخصي إذا كانت المحادثة الداخلية هي قناة التواصل المعتمدة.
- الفني لا يحصل على بيانات اتصال أكثر مما يحتاج.
- Branch Manager/HQ يحصلان على البيانات حسب الصلاحيات.
- لا تكسر OTP أو الحجز أو WhatsApp/SMS internal workflows.

لا تحذف حقول قاعدة البيانات؛ اضبط Serialization/Resources/Permissions.

### 7. تنظيف `.env.example`
صحح:
- أي متغير مكرر مثل `DUMP_BINARY_PATH`.
- أي Defaults خطرة.
- أي Secrets حقيقية أو قيم تشبه مفاتيح حقيقية.
- اجعل التكاملات الخارجية Disabled افتراضيًا:
  - Tap/Tabby/Tamara
  - Odoo
  - SMS
  - WhatsApp
  - Backups إن كانت تحتاج مسارًا حقيقيًا قبل التفعيل

يجب أن يبقى:
```env
DB_CONNECTION=mysql
```

ولا تضف إعداد PostgreSQL/PostGIS كمسار إنتاجي معتمد.

### 8. تنظيف Debug/Test code
ابحث عن:
- `dd(`
- `dump(`
- debug prints
- temporary logs التي تطبع payloads حساسة
- hard-coded test credentials
- temporary bypasses
- TODO security bypasses

لا تحذف Logging التشغيلي المفيد، لكن لا تسجل:
- API secrets
- access tokens
- full payment card data
- OTP values
- passwords

### 9. مراجعة Route Cache readiness
لا تشغّل `route:cache`.

لكن أصلح ما يمكن إصلاحه برمجيًا:
- انقل Closure routes غير الضرورية إلى Controllers إن كانت Routes إنتاجية.
- اجعل Routes الإنتاجية قابلة للكاش قدر الإمكان.
- إذا بقي Closure ضروريًا، وثّقه في تقرير النهاية.

### 10. لا تضف Features جديدة
هذه مرحلة Closure فقط.
ممنوع تحويلها إلى إعادة تصميم كبيرة أو إضافة خصائص تشغيلية لم تُطلب.

---

## معايير القبول
- لا يوجد `/test` أو endpoint تجريبي مكشوف.
- `/image-proxy` محذوف أو مؤمّن ضد SSRF بصورة صحيحة.
- Branch Mode لا يمكن أن يتوقف بسبب Provider Subscription/Trial.
- لا تظهر Commission/Withdraw/Provider Earnings في تطبيق الفرع.
- Legacy financial routes غير قابلة للاستخدام في Branch Mode.
- لا تُكشف أرقام أو بيانات اتصال خارج الصلاحيات المطلوبة.
- `.env.example` نظيف ومتوافق مع MySQL 8.
- لا توجد Secrets أو Debug bypasses واضحة في السورس.
- لا يتم إدخال PostgreSQL/PostGIS في هذا المشروع.

## قواعد تنفيذ إلزامية للوكيل

هذه الوثيقة **مواصفة تنفيذ مباشرة**. المطلوب تعديل السورس الفعلي وإنهاء نطاق هذه المرحلة بالكامل، وليس كتابة تقرير أو اقتراحات فقط.

### قرارات معمارية ثابتة لا يجوز تغييرها
- قاعدة البيانات المعتمدة رسميًا للمشروع هي **MySQL 8 + Spatial**.
- **ممنوع** تحويل المشروع إلى PostgreSQL/PostGIS ضمن هذه المرحلة.
- حافظ على `DB_CONNECTION=mysql`.
- أي Migration أو Query جديد يجب أن يعمل على MySQL 8.
- تجنب إضافة SQL خاص بـ PostgreSQL.
- استخدم Eloquent / Query Builder / طبقات الخدمات الحالية قدر الإمكان.
- `Provider` يبقى كيانًا داخليًا للتوافق، ويُعامل وظيفيًا كـ **Branch** في NIRAB.
- لا تعِد منطق Commission / Provider Subscription / Provider Withdraw إلى Branch Mode.
- لا تجعل `Completed` مساويًا لـ `Paid`.

### طريقة العمل
1. افحص السورس الحالي أولًا ولا تفترض أسماء ملفات أو Classes أو Routes غير موجودة.
2. أعد استخدام الوحدات والخدمات الحالية بدل إنشاء بنية موازية مكررة.
3. حافظ على Backward Compatibility قدر الإمكان.
4. لا تحذف جداول/أعمدة Legacy مستخدمة إذا كان تعطيلها أو عزلها خلف Branch Mode أكثر أمانًا.
5. أي Migration جديدة يجب أن تحتوي `down()` مناسبًا وIndexes/Foreign Keys اللازمة.
6. لا تضع مفاتيح أو Credentials حقيقية في السورس.
7. النصوص الجديدة يجب أن تستخدم نظام الترجمة الحالي.
8. لا تضف TODOs أو Stubs أو Endpoints وهمية توحي بأن الميزة مكتملة.
9. عند تغيير Contract للـAPI، حدّث جميع التطبيقات المتأثرة في نفس المرحلة.
10. أصلح أخطاء Build/Compile الناتجة عن تعديلاتك قبل إنهاء المرحلة.

### ممنوع أثناء التنفيذ
- ممنوع تشغيل Laravel server أو أي Web server.
- ممنوع تشغيل Docker/Compose أو تجهيز بيئة تشغيل كاملة.
- ممنوع تشغيل Android/iOS emulator أو simulator.
- ممنوع تشغيل `php artisan migrate` أو `migrate:fresh` أو `migrate:refresh`.
- ممنوع تشغيل Seeders على قاعدة بيانات فعلية.
- ممنوع تشغيل PHPUnit/Pest/Flutter tests/E2E/Integration/Load/Stress tests.
- ممنوع الاتصال الحقيقي بـ Tap أو Odoo أو ZATCA أو SMS أو WhatsApp أو أي خدمة خارجية.
- ممنوع استخدام مفاتيح Production أو Sandbox حقيقية أثناء التنفيذ.
- ممنوع تعديل DNS أو إعدادات السيرفر أو Supervisor/Cron الإنتاجي.
- ممنوع تنفيذ Data Migration من MySQL إلى أي محرك آخر.

### التحقق المسموح والمطلوب في نهاية المرحلة فقط

#### Backend / PHP
- نفّذ `php -l` على ملفات PHP الجديدة والمعدلة.
- نفّذ `composer validate` إذا كانت Composer متاحة.
- يمكن تنفيذ `composer dump-autoload -o --no-scripts` فقط إذا كانت Dependencies متاحة بالفعل.
- لا تنفذ أوامر Artisan التي تعتمد على Database أو تشغيل التطبيق كاملًا.

#### Flutter
- لا تشغّل التطبيق.
- إذا كانت Flutter toolchain وDependencies متاحة، نفّذ:
  - `flutter analyze` على التطبيقات المعدلة.
  - Build/compile فقط للتطبيقات المعدلة إذا كان ذلك ممكنًا بدون Signing أو تشغيل Emulator.
- لا تبنِ iOS إذا كانت البيئة لا تدعمه.
- إذا لم تكن الأدوات متاحة، لا تقم بإعداد بيئة جديدة؛ اذكر ذلك في التقرير النهائي.

### تقرير الإنهاء المطلوب من الوكيل
في نهاية المرحلة أعطِ تقريرًا قصيرًا يحتوي:
- الملفات المعدلة.
- الملفات الجديدة.
- Migrations الجديدة إن وجدت.
- APIs/Routes الجديدة أو المعدلة.
- أي Legacy behavior تم تعطيله.
- Build/Syntax checks التي نُفذت ونتيجتها.
- أي شيء يحتاج Configuration أو Deployment لاحقًا، بدون تنفيذه.
