# NIRAB — المرحلة 8: إغلاق واجهات التشغيل والإدارة

## الهدف
إكمال الواجهات التشغيلية التي أصبح Backend الخاص بها موجودًا بعد المراحل السابقة، بحيث يستطيع مدير الفرع والإدارة إدارة النظام من الواجهات الفعلية بدون إدخال UUIDs يدويًا أو استخدام API خارجي.

هذه المرحلة **لا تعيد بناء المحركات الخلفية**؛ المطلوب ربط وإكمال UI/UX فوق APIs/Services الموجودة، وإغلاق أي فجوة ضرورية بين Backend والواجهات.

---

## نطاق المرحلة

### Provider App → Branch Manager App
ركز على:
- Teams.
- Team Leader / Members.
- Technician Skills.
- Technician Shifts.
- Technician Leaves.
- Branch Service Areas.
- Technician operational status.
- Assignment/Dispatch screens.

### Admin Web
ركز على:
- Dynamic Service Fields.
- Field Options.
- Pricing Rules.
- Branch / Neighborhood / Peak pricing.
- Branch Service Availability.
- Team/Technician operational administration عند الحاجة.
- Roles/Permissions/Data scopes.

### Backend
التعديل يكون فقط عند الحاجة إلى:
- API ناقص لواجهة موجودة.
- Validation.
- Pagination/filtering.
- Authorization.
- Map payloads.
- Bulk operations.
- Audit logging.

لا تنشئ Backend مكررًا إذا كانت الخدمات الحالية كافية.

---

## المطلوب تنفيذه

### 1. إكمال Team Management في تطبيق الفرع
الشاشة الحالية لا يجب أن تعتمد على كتابة IDs/UUIDs يدويًا.

المطلوب:
- إنشاء/تعديل Team.
- اختيار Team Leader من قائمة فنيي الفرع.
- اختيار Team Members عبر multi-select.
- إظهار الاسم والصورة/الحالة المناسبة بدل UUID.
- منع اختيار فني من فرع آخر.
- منع تكرار العضو في نفس الفريق.
- منع أكثر من Leader لنفس الفريق.
- إظهار عدد أعضاء الفريق.
- دعم تفعيل/تعطيل الفريق إذا كان Backend يدعم ذلك.
- دعم `default/min/max technicians` عند استخدامها في التنفيذ.

إذا كانت هناك قيود Backend موجودة، اعرض رسالة Validation مفهومة بدل تجاهلها.

### 2. شاشة Technician Skills
أنشئ/أكمل UI لمدير الفرع بحيث يستطيع:
- اختيار فني.
- رؤية Skills الحالية.
- إضافة Skill/Service capability.
- إزالة Skill.
- دعم مستوى/نوع المهارة إن كان موجودًا في الـSchema.
- منع تعديل فني من فرع آخر.

استخدم APIs الحالية إن وجدت.

### 3. شاشة Technician Shifts
المطلوب:
- عرض أيام وساعات العمل للفني.
- إضافة/تعديل Shift.
- منع time ranges غير الصحيحة.
- دعم أكثر من Shift إذا كان Backend يسمح.
- عرض timezone وفق إعداد المشروع.
- لا تضف منطق جدولة مكررًا داخل Flutter؛ أرسل البيانات للBackend الموجود.

### 4. شاشة Technician Leaves
المطلوب:
- عرض الإجازات.
- إضافة Leave بفترة بداية/نهاية وسبب عند وجوده.
- تعديل/إلغاء حسب API.
- عرض overlap validation القادم من Backend.
- عدم السماح لمدير فرع بإدارة فني من فرع آخر.

### 5. Branch Service Areas UI
هذه واجهة مهمة.

المطلوب في Branch Manager أو Admin حسب الصلاحيات الحالية:
- عرض خريطة.
- إظهار حدود الفرع الحالية.
- رسم Polygon أو تعديل Polygon.
- حذف/تعطيل Area حسب API.
- إرسال إحداثيات صحيحة بنفس ترتيب/format الذي يستخدمه Backend.
- دعم أكثر من Area إذا كان Schema الحالي يسمح بذلك.
- إظهار validation إذا تقاطع/فشل Polygon حسب قواعد Backend.
- لا تغيّر قاعدة البيانات؛ استخدم MySQL Spatial الحالي.

إذا كان تطبيق Flutter الحالي لا يحتوي أداة Polygon مناسبة، استخدم الـMap package الموجود بالفعل في المشروع قبل إضافة dependency جديدة.

### 6. Dynamic Service Fields Admin UI
أنشئ/أكمل إدارة الحقول الديناميكية للخدمة من لوحة الإدارة.

يجب أن يستطيع Admin:
- إضافة Field.
- تحديد Label بالعربي/الإنجليزي وفق نظام المشروع.
- تحديد Type.
- Required/Optional.
- ترتيب Field.
- Unit إن كان مدعومًا.
- Placeholder/Help text إن كان Schema يدعم.
- إدارة Options لأنواع select/radio/multi-select.
- تفعيل/تعطيل Field.
- حذف Field بأمان حسب قواعد Backend.

ادعم الأنواع الموجودة فعليًا في Backend فقط. لا تخترع Types لا يدعمها الـSchema.

### 7. Pricing Rules Admin UI
أنشئ/أكمل واجهة إدارة:
- Branch factor.
- Neighborhood/area factor.
- Peak pricing.
- Date/time windows إذا كان Backend يدعمها.
- Priority/order.
- Active status.
- Bulk updates إذا كانت API موجودة.

المطلوب:
- Validation واضح.
- عدم السماح بمعامل/قيمة غير صالحة.
- إظهار Effective configuration قدر الإمكان دون تنفيذ Pricing Engine جديد في الواجهة.
- حفظ Audit من Backend، لا من Flutter/JS فقط.

### 8. Branch Service Availability UI
الإدارة/مدير الفرع يجب أن يستطيع:
- تفعيل/تعطيل Service لفرع.
- تفعيل/تعطيل Service لمنطقة إذا كان Backend يدعم ذلك.
- البحث/التصفية.
- Bulk enable/disable إذا كان API يدعم.
- عدم إنشاء نسخة Service جديدة لكل فرع.

الخدمات تبقى Catalog مركزي.

### 9. صلاحيات الأدوار
راجع Backend Policies/Gates/Scopes وكذلك إخفاء عناصر UI.

القواعد:
```text
HQ / Super Admin
→ يرى ويدير كل الفروع.

Branch Manager
→ فرعه فقط.

Accountant
→ التحصيلات/العهد/المالية الممنوحة له فقط.

Customer Service
→ متابعة الطلبات والشكاوى حسب الصلاحية، بدون تعديل الأسعار إن لم يمنح الإذن.

Team Leader
→ مهام فريقه والإجراءات المسموحة.

Technician
→ مهامه فقط.
```

المطلوب:
- Backend enforcement إلزامي.
- UI hiding مجرد تحسين إضافي وليس وسيلة الحماية الأساسية.
- أي API List يجب أن تكون scoped حسب الدور.

### 10. تحسين واجهات Dispatch التشغيلية
إذا كانت الشاشات الحالية تعرض الفنيين كـIDs أو بيانات خام:
- اعرض الاسم.
- المسافة/ETA المتاح.
- availability.
- rating.
- workload إذا كان API يرسله.
- سبب عدم الأهلية إن كان مفيدًا.
- زر assign واضح.

لا تعِد كتابة Dispatch Engine داخل التطبيق.

### 11. Audit UX عند الحاجة
في العمليات الحساسة:
- تعديل Pricing Rule.
- تعديل Service Availability.
- تغييرات الفرق.
- أي عملية مالية موجودة في هذه الواجهة.

تأكد أن Backend يسجل Audit.
يمكن إظهار `updated_by/updated_at` إذا كانت البيانات متاحة، لكن لا تنشئ سجل تدقيق محليًا في الواجهة.

### 12. لا تغير منطق المرحلة 7 أو النموذج المالي
- لا تعيد Provider Subscription.
- لا تعيد Commission.
- لا تعيد Withdraw.
- لا تربط Branch revenue برصيد قابل للسحب.
- لا تغيّر MySQL.

---

## معايير القبول
- مدير الفرع يستطيع إنشاء فريق واختيار القائد والأعضاء بدون كتابة IDs.
- توجد واجهات عملية لـSkills/Shifts/Leaves.
- يمكن إدارة Branch Service Areas بالخريطة.
- Admin يستطيع إدارة Dynamic Service Fields وOptions.
- Admin يستطيع إدارة Pricing Rules من UI.
- Admin/Branch يستطيع إدارة Service Availability ضمن صلاحياته.
- الصلاحيات مطبقة Backend-side.
- لا توجد شاشة تشغيلية أساسية تتطلب استدعاء API يدويًا لإتمام المتطلبات أعلاه.
- لا يوجد أي اعتماد على PostgreSQL/PostGIS.

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
