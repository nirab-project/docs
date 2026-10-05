# إغلاق المرحلة 8 — واجهات التشغيل والإدارة

تاريخ التنفيذ: 2026-10-06.

تم تنفيذ واجهات الفرق والقائد والأعضاء بالأسماء والصور، وسعة الفريق وتفعيله؛ مهارات الفني وسعتها، فترات الدوام المتعددة، الإجازات وتعديلها وإلغاؤها، والحالة التشغيلية. أضيفت إدارة مناطق التغطية بالخريطة في التطبيق والإدارة، مع الرسم وتعديل النقاط والتفعيل والتعطيل وإرسال GeoJSON/إحداثيات متوافقة مع MySQL Spatial.

أضيف مركز التشغيل إلى قائمة لوحة الإدارة: الحقول الديناميكية وخياراتها وترجماتها العربية والإنجليزية، قواعد تسعير الفرع والحي والذروة ونوافذها وأولويتها وحالتها وتحديثها الجماعي، وعرض القواعد الفعلية من Backend الحالي. أضيف البحث والتصفية والتحديث الجماعي لإتاحة الخدمات على مستوى الفرع والمنطقة، وإدارة الصلاحيات ونطاقات الفروع بالمستخدمين والأسماء الحالية. تظل الخدمات كتالوجاً مركزياً.

تستخدم شاشات الإسناد أسماء الفنيين والفرق، التوفر، ETA، التقييم، عبء العمل وأسباب الاستبعاد، وتستدعي محرك الإسناد الحالي. تطبق صلاحيات القراءة والتعديل ونطاقات الفروع في Backend. تستخدم العمليات الحساسة AuditService وPriceAudit الحاليين، وتحمي الحقول المستخدمة في الحجوزات من الحذف أو تغيير النوع والقيم المحفوظة.

## الملفات

المسارات التالية نسبة إلى جذر المشروع.
### Backend ولوحة الإدارة

الجذر: `Admin Panel`.

الملفات المعدلة (20):

- `Modules/AdminModule/Resources/views/layouts/partials/_aside.blade.php`
- `Modules/AdminModule/Resources/views/quality/index.blade.php`
- `Modules/BookingModule/Http/Controllers/Api/V1/Provider/BookingController.php`
- `Modules/BookingModule/Http/Controllers/Api/V1/Provider/DispatchController.php`
- `Modules/BookingModule/Http/Controllers/Web/Admin/BookingController.php`
- `Modules/BookingModule/Http/Controllers/Web/Provider/BookingController.php`
- `Modules/BookingModule/Routes/api/v1/api.php`
- `Modules/BookingModule/Services/DispatchEngine.php`
- `Modules/ServiceManagement/Http/Controllers/Api/V1/Admin/BranchServiceAvailabilityController.php`
- `Modules/ServiceManagement/Http/Controllers/Api/V1/Admin/PricingRuleController.php`
- `Modules/ServiceManagement/Http/Controllers/Api/V1/Admin/ServiceFieldController.php`
- `Modules/ServiceManagement/Routes/api/v1/api.php`
- `Modules/ServiceManagement/Routes/web.php`
- `Modules/ServicemanModule/Http/Controllers/Api/V1/Provider/ServicemanController.php`
- `Modules/ServicemanModule/Routes/api/v1/api.php`
- `Modules/ServicemanModule/Routes/web.php`
- `app/Http/Controllers/QualityAdministrationController.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Services/OperationalAccess.php`
- `bootstrap/app.php`

الملفات الجديدة (8):

- `Modules/ServiceManagement/Http/Controllers/Web/Admin/OperationsController.php`
- `Modules/ServiceManagement/Resources/views/admin/operations-workforce.blade.php`
- `Modules/ServiceManagement/Resources/views/admin/operations.blade.php`
- `app/Http/Middleware/EnforceWorkforceManagement.php`
- `public/assets/admin-module/js/nirab-operations.js`
- `public/assets/admin-module/js/nirab-workforce.js`
- `resources/lang/ar/operations.php`
- `resources/lang/en/operations.php`

### تطبيق مدير الفرع

الجذر: `Provider app`.

الملفات المعدلة (7):

- `assets/language/ar.json`
- `assets/language/en.json`
- `lib/feature/booking_details/controller/booking_details_controller.dart`
- `lib/feature/booking_details/repo/booking_details_repo.dart`
- `lib/feature/booking_details/widget/assign_serviceman_screen.dart`
- `lib/feature/serviceman/view/serviceman_setup_screen.dart`
- `lib/feature/serviceman/view/team_management_screen.dart`

الملفات الجديدة (7):

- `lib/feature/serviceman/operations/operations_api.dart`
- `lib/feature/serviceman/operations/team_editor.dart`
- `lib/feature/serviceman/operations/workforce_editors.dart`
- `lib/feature/serviceman/view/branch_operations_screen.dart`
- `lib/feature/serviceman/view/branch_service_areas_screen.dart`
- `lib/feature/serviceman/view/branch_service_availability_screen.dart`
- `lib/feature/serviceman/view/technician_operations_screen.dart`

## APIs وRoutes

- جديد: `GET /admin/operations` مع مسارات جلسة الإدارة تحت `/admin/operations/*` للحقول والخيارات والأسعار والإتاحة والفرق والفنيين والإجازات والمناطق والإسناد والصلاحيات. تعيد هذه المسارات استخدام Controllers/Services الموجودة وتبقى تحت حماية جلسة الإدارة وCSRF.
- جديد: `GET /api/v1/provider/dispatch/context` و`services` و`servicemen` و`servicemen/{id}` و`bookings`؛ وقائمة الفنيين والخدمات تدعم pagination.
- جديد: `PUT /api/v1/provider/dispatch/servicemen/{id}/status`؛ و`PUT|DELETE /api/v1/provider/dispatch/servicemen/{id}/leaves/{leave_id}`.
- جديد وربط مصحح: `GET /api/v1/provider/booking/dispatch/{id}/candidates` و`PUT /api/v1/provider/booking/dispatch/{id}/assign`. بقيت مسارات الإسناد الخاصة بالإدارة متوافقة وأصبحت scoped.
- معدّل: `/api/v1/provider/dispatch/teams` و`servicemen/{id}/skills` و`shifts` و`leaves` و`service-areas` و`alerts`: صلاحيات، validation ورسائل مفهومة، تدقيق حيث يلزم، والتحقق من الفرع والتداخل وصحة Polygon.
- جديد: `GET|PUT /api/v1/provider/branch-service-availability` و`PUT /api/v1/provider/branch-service-availability/bulk`. أضيف GET وbulk أيضاً إلى مسارات الإدارة الموجودة.
- جديد: `GET /api/v1/admin/pricing-rules/effective` و`PUT /api/v1/admin/pricing-rules/bulk-status`. بقي POST bulk القديم متوافقاً مع تطبيق القاعدة على الكتالوج.
- معدّل: مسارات الحقول والخيارات وقواعد التسعير والصلاحيات الحالية لإلزام Authorization وحماية السجلات المستخدمة والتدقيق. تتوافق قوائم الأسعار أيضاً مع قواعد الأحياء القديمة التي حفظت area_id دون provider_id.
- معدّل: مسارات إدارة الفنيين القديمة والإسناد القديم في التطبيق والويب لمنع تجاوز صلاحيات التشغيل ومحرك الإسناد في Branch Mode.

## Migrations وLegacy

لا توجد Migrations أو Dependencies جديدة. استُخدمت الجداول والفهارس والخدمات الحالية، مع قفل سجل الفرع لتسلسل تحديثات إتاحة الخدمة ومنع تكرار الإعداد العام عند NULL في فهرس MySQL.

أزيل إدخال UUIDs من شاشة الفرق، وأخفي اختيار الفني التقليدي في وضع الفروع لصالح الإسناد الحالي. عُزل تجاوز الإسناد المباشر القديم خلف وضع Legacy؛ لا تغيير في Completed/Paid، ولا إعادة Commission أو Provider Subscription أو Withdraw، ولا تحويل محرك قاعدة البيانات أو نقل بيانات.

## التحقق

- `php -l`: نجح لجميع ملفات PHP/Blade الجديدة والمعدلة (26 ملفاً).
- تحويل 4 قوالب Blade معدلة إلى PHP دون إقلاع Laravel أو اتصال بقاعدة البيانات، ثم `php -l`: نجح.
- `composer validate --no-check-publish`: نجح مع تحذيرات سابقة عن قيود الإصدارات غير المحددة لبعض Dependencies.
- `composer dump-autoload -o --no-scripts --no-plugins`: نجح؛ 16230 class.
- `node --check`: نجح لملفي JavaScript الجديدين.
- `dart format` ثم `dart format --output=none --set-exit-if-changed`: نجح على 12 ملف Dart، والفحص النهائي لم يحتج تغييرات.
- `git diff --check`: نجح في مستودعي الإدارة والتطبيق. تم ضبط المسافات والأسطر النهائية وفحص JSON وترجمات 70 مفتاح واجهة مباشر في العربية والإنجليزية.
- `flutter pub get --offline`: تعطل لأن `loading_animation_widget` غير موجودة في الكاش المحلي. لذلك لم يُنفذ Flutter analyze أو Build كامل، ولا يُدعى نجاحهما. Flutter 3.41.4 / Dart 3.11.1 موجودان، لكن اعتماديات التطبيق غير مكتملة؛ لم تُنزّل حزم أو يُجهّز بديل لبيئة التشغيل.

لم تُشغّل خوادم أو التطبيق أو محاكيات، ولا migrations أو seeders أو اختبارات Unit/Integration/E2E أو خدمات مالية وخارجية.

## ما يلزم عند النشر لاحقاً

- نشر ملفات Backend وpublic JS والتطبيق، وتحديث كاش Routes/Views ضمن إجراءات النشر المعتادة إن كان مستخدماً.
- استخدام إعداد مفتاح خرائط Google الحالي في إعدادات الأعمال والتطبيق؛ لا مفاتيح حقيقية جديدة في السورس.
- منح الموظفين الصلاحيات التشغيلية ونطاقات الفروع اللازمة من مركز التشغيل. الصلاحيات المالية تبقى في واجهتها الحالية.
- توفير اعتماديات Flutter الحالية في بيئة البناء ثم إجراء analyze وbuild فقط. فحص التشغيل الفعلي لم يُنفذ وفق القيود المطلوبة.
