# NIRAB — المرحلة 9: إغلاق تجربة العميل ودورة الطلب قبل Code Freeze

## الهدف
إكمال آخر فجوات تجربة العميل ودورة الطلب المشتركة بين Customer App وBranch Manager App وServiceman App، ثم تثبيت السورس للوصول إلى **Code Freeze**.

هذه المرحلة ليست لإضافة خصائص تجارية جديدة؛ هي لإكمال:
- Available Slots.
- Live Technician Tracking.
- اتساق حالات الطلب.
- Team visibility/actions.
- فصل حالات التنفيذ والدفع والفاتورة في الواجهات.

بعد إنهاء هذه المرحلة يجب اعتبار أي تغيير لاحق **Bug Fix فقط** حتى انتهاء Staging والنشر.

---

## نطاق المرحلة

### Customer App / Customer Web
- Booking scheduling.
- Available slots.
- Booking details.
- Technician tracking.
- Team information.
- Payment status.
- Invoice status.
- Quote/booking transition عند الحاجة.

### Serviceman App
- Assignment state.
- On the way.
- Location updates.
- Team role restrictions.
- Work finished/completion state.

### Provider / Branch Manager App
- Assignment state consistency.
- Team state.
- Manual override visibility.
- Booking status transitions.

### Backend
- Availability endpoint إن كان الحالي لا يعيد Slots.
- Tracking endpoint/payload.
- Status transition validation.
- Team member visibility payload.
- Payment/invoice state payload.
- Authorization.

---

## المطلوب تنفيذه

### 1. Available Slots بدل اختيار وقت عشوائي
الوضع المطلوب:

```text
Customer selects service/date
↓
Backend calculates real availability
↓
Customer sees available slots only
↓
Customer chooses a slot
↓
Backend validates again at booking confirmation
```

استفد من:
- service duration.
- technician/team schedules.
- shifts.
- leaves.
- existing bookings.
- travel buffer.
- branch capacity.
- execution type single/team.

إذا كان Backend الحالي لديه `validateRealAvailability()` فقط:
- أضف/أكمل Endpoint يعيد Slots إن لم يكن موجودًا.
- لا تنقل خوارزمية availability إلى Flutter.
- عند التأكيد أعد Validation لمنع race conditions.

واجهة العميل:
- تعرض Loading/Error/No availability بشكل واضح.
- لا تسمح باختيار Slot غير موجود.
- تدعم تغيير اليوم وإعادة جلب Slots.
- تحترم Branch المختار يدويًا أو Auto-assigned حسب الـflow الحالي.

### 2. Live Technician Tracking للعميل
استخدم الأساس الموجود:
- technician last location.
- tracking service.
- ETA.
- booking assignment.

المطلوب في Customer App:
- عند الحالة المناسبة مثل `on_the_way`:
  - عرض Map.
  - Customer location.
  - Technician current/last location.
  - ETA.
  - last updated time.
- تحديث الموقع عبر Polling/stream بالطريقة الأنسب للبنية الحالية.
- أوقف التحديث عندما لا تكون الشاشة نشطة أو عندما تنتهي الحالة لتقليل استهلاك البيانات.
- تعامل مع stale location بوضوح.
- لا تكشف موقع الفني خارج Booking المصرح للعميل به.

### 3. لا تربط المرحلة بخدمة Google Routes جديدة
حافظ حاليًا على ETA provider الموجود كـFallback.

إن كان الكود يحتوي:
```text
StraightLineEtaProvider
```
فأبقِ abstraction قابلة للاستبدال لاحقًا.

لا:
- تضف Google Routes credentials.
- تتصل بخدمة خارجية.
- تجعل Build يعتمد على API جديد غير مهيأ.

المطلوب فقط أن UI يستخدم ETA الموجود بشكل صحيح.

### 4. توحيد دورة حالات الطلب
راجع Backend والتطبيقات الثلاثة حتى تتفق على نفس semantics.

المسار الأساسي المتوقع:
```text
pending
↓
accepted
↓
assigned
↓
on_the_way
↓
ongoing
↓
work_finished
↓
completed
```

مع الحالات الاستثنائية الموجودة فعليًا مثل:
```text
canceled
rejected
failed
```

المطلوب:
- لا يخترع تطبيق حالة غير موجودة في Backend.
- لا يعرض نفس الحالة باسمين متناقضين.
- Actions المسموحة تظهر فقط في الحالة المناسبة.
- Backend يمنع transition غير صالح.
- Repeat booking وQuote-converted booking لا يكسران الدورة.

### 5. Team Job behavior
للحجز الجماعي:
- Customer يرى أن المهمة ينفذها Team إذا كان ذلك مناسبًا.
- يمكن عرض Team Leader وبيانات مختصرة للفريق حسب سياسة الخصوصية.
- Team Leader يستطيع الإجراءات القيادية الموجودة في Backend.
- Member العادي لا يستطيع Complete/Close إذا كانت الصلاحية للقائد فقط.
- جميع أعضاء الفريق المصرح لهم يرون المهمة في Serviceman App.
- Branch Manager يرى الفريق الكامل وحالة أعضائه.

لا تعِد استخدام `serviceman_id` وحده لإخفاء بقية أعضاء الفريق إذا كان `booking_assignments`/team relations موجودًا.

### 6. Location update behavior في تطبيق الفني
تأكد من أن التطبيق:
- يرسل location فقط خلال الحالات التي تحتاج tracking.
- لا يستمر في polling/upload غير المحدود بعد انتهاء المهمة.
- يحدّث آخر موقع بالـEndpoint الحالي.
- يتعامل مع permission denied/location disabled بدون crash.
- لا يرسل الموقع إلى Booking غير مسند للفني.
- لا يغير Check-in/Check-out proof flow الموجود في المرحلة 4.

لا تضف background tracking معقدًا جديدًا إذا لم يكن موجودًا ومطلوبًا صراحة؛ ركز على إكمال الـflow الحالي بصورة آمنة.

### 7. فصل Booking / Payment / Invoice في الواجهة
في Booking Details للعميل والفرع، اعرض بشكل منفصل:

```text
Service / Booking Status
Payment Status
Invoice Status
```

أمثلة صحيحة:
```text
Service: Completed
Payment: Pending
Invoice: Issued
```

أو:
```text
Service: Completed
Payment: Paid
Invoice: Available
```

ممنوع:
- افتراض `completed == paid`.
- إخفاء Booking لمجرد أن Odoo sync لم يكتمل.
- جعل invoice status يغير service status.

استخدم الحقول الحالية الموجودة في API، وأضف Resource fields فقط إذا كانت ناقصة.

### 8. Invoice access UI
إذا Backend/Odoo integration يوفر Invoice PDF أو invoice reference:
- أظهر زر View/Download Invoice عندما تكون جاهزة.
- أظهر Pending/Processing إذا لم تصل بعد.
- لا تولد فاتورة محلية بديلة من Flutter.
- لا تتصل بـOdoo مباشرة من التطبيق؛ كل شيء يمر عبر NIRAB Backend.

### 9. Quote → Booking UX
راجع فقط اتساق flow الموجود:
```text
Quote request
↓
Branch quote
↓
Customer accepts
↓
Payment if required
↓
Confirmed booking
```

المطلوب:
- العميل يفهم حالة العرض وصلاحيته.
- العرض المنتهي لا يمكن قبوله.
- عند التحويل لا يظهر Booking مكرر.
- branch/service/address/price تنتقل بصورة صحيحة حسب Backend الحالي.
- لا تعِد بناء Quotation Engine.

### 10. حالات Offline / Error
لواجهات Slots/Tracking/Statuses:
- تعامل مع timeout.
- empty response.
- stale tracking.
- booking no longer active.
- no available slots.
- permission denied.

لا تجعل Failure في tracking يمنع فتح Booking Details الأساسية.

### 11. الترجمة
راجع النصوص الجديدة في التطبيقات:
- العربية.
- الإنجليزية.
- RTL.
- أسماء Branch/Team/Status.

لا تستخدم Strings ثابتة إذا كان المشروع يملك localization layer.

### 12. Code Freeze preparation
في نهاية هذه المرحلة:
- لا تبقِ TODOs مرتبطة بالـflow المطلوب هنا.
- لا تبقِ Mock data.
- لا تبقِ hidden debug buttons.
- لا تضف Feature جديدة خارج النطاق.
- وثّق أي Configuration يحتاجها النشر لاحقًا بدل تشغيلها.

---

## معايير القبول
- العميل يرى Available Slots حقيقية بدل الاعتماد فقط على رفض وقت غير متاح.
- العميل يستطيع رؤية الفني على الخريطة أثناء `on_the_way` باستخدام Tracking backend الموجود.
- الحالات متطابقة بين Backend وCustomer/Branch/Serviceman apps.
- Team jobs تعمل بصلاحيات القائد والأعضاء الصحيحة.
- Booking Status وPayment Status وInvoice Status منفصلة في UI والمنطق.
- Invoice يمر عبر Backend/Odoo integration وليس اتصالًا مباشرًا من التطبيق.
- لا توجد خدمة خارجية جديدة مطلوبة للبناء.
- لا توجد تغييرات PostgreSQL/PostGIS.
- السورس جاهز لـCode Freeze بعد Build/Syntax verification.

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
