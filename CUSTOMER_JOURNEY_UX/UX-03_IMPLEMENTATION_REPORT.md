# UX-03 — الباقات وحجز زيارة من الرصيد

اكتمل المسار المصدري في Backend والعميل/الويب وتوافق العرض في الفرع والفني. يلزم تطبيق migration المكتوبة ضمن نشر معتمد لاحقاً؛ لم تُشغّل.

- VisitPackagesScreen مستقلة: خطط 4/8/12، شراء بالمحفظة، رصيد وصلاحية وحالة وسياسة الوحدات والفاصل، تجديد المحفظة القائم وسجل استخدام قابل لفتح الحجز. المدخل في الحساب والقائمة، ومدخل مركز الجودة يحيل إليها مع بقاء الشكاوى.
- PackageVisitScreen تحمل الأهلية والخدمة من الخادم، عنواناً مملوكاً وخياراً متاحاً في منطقته وكمية مغطاة، ثم AvailableSlotPicker بسياق صريح مستقل عن السلة، ملخص خادمي، وتأكيد واحد.
- VisitPackageService.book ينسق حجزاً جديداً من نوع prepaid_visit مع reserve الموجود داخل DB::transaction (ثلاث محاولات deadlock). لا حجز نقدي مؤقت ولا طلب واجهة ثانٍ للخصم، ولا تسعير مختلط.
- قفل العميل يسلسل مفاتيح إنشاء الزيارة، ثم قفل الفرع قبل إعادة السعة، ثم الحجز الجديد ثم الباقة والاستخدام. مسارا reserve وsettle القديمان يبقيان ترتيب booking → package → usage.
- idempotency_key + customer_id فريدان، وrequest_hash يرفض تغيير السياق لنفس المفتاح. الطلب المطابق يرجع نفس الحجز حتى بعد الإلغاء ولا يستهلك زيارة أخرى. Usage.booking_id وLedger.event_key يظلان فريدين.
- فشل إنشاء الحجز/الموعد/الرصيد يرجع كامل المعاملة بما فيها السجل ورسائل Outbox. العميل يحفظ محاولة book قبل الإرسال في SharedPreferences (معرفات ووقت وكمية فقط)، ويستأنفها بعد إعادة فتح الشاشة دون مفتاح جديد.
- settle أعيد ربطه بالحالة المقروءة تحت قفل الحجز؛ الإلغاء قبل بدء العمل يعيد زيارة مرة واحدة، وبعد بدء/انتهاء العمل يستهلكها. الحجز المغطى لا يعاد فتحه بعد تحرير/استهلاك الرصيد ولا يحوّل إلى وسيلة دفع أخرى.
- تغيير موعد/عنوان زيارة مغطاة يتطلب إلغاء مؤهلاً ثم حجزاً جديداً؛ لم يُضف مسار إعادة جدولة يتجاوز صلاحية الباقة أو الفاصل.
- workflow.visit_package يعطي بيانات تغطية تشغيلية فقط دون مبلغ شراء الباقة. invoice_status=not_applicable يعكس استثناء prepaid_visit الموجود فعلاً في AccountingEvents، ولا يصطنع فاتورة.

## العقود والمهاجرات
GET /visit-packages/{id}/booking-context؛ POST /booking-preview؛ POST /book. جميعها تحت auth:api مع فحص customer وملكية الباقة والعنوان في الخدمة. /book له throttle.
المهاجرة: Modules/BookingModule/Database/Migrations/2026_10_07_000001_create_visit_booking_requests.php. MySQL-compatible، مفتاح مركب فريد، foreign keys وdown() إسقاط الجدول الجديد. تطبق بعد مهاجرة quality packages السابقة، وقبل نشر العميل الجديد. لا بيانات ترحيل أو seeder.
المسارات القديمة plans/index/purchase/reserve/renewal/ledger باقية، مع منع إنشاء/شراء خطة لخدمة quote. لا تشغيل تجديد ولا شراء بطاقة جديد.

## الفحوص
php -l نجح لثمانية ملفات PHP الجديدة/المعدلة. Dart format/analyze للعميل: لا أخطاء أو تحذيرات، ملاحظات أسلوبية تراجع في الإغلاق. JSON للمنصات الثلاث قابل للتحليل؛ صحح ترميز ملفات معدلة إلى UTF-8. نتائج تحليل الفرع والفني محفوظة في سجل الإغلاق. لم يختبر التزامن أو الإلغاء أو الشراء تشغيلياً.

## الملفات
- `Admin Panel/Modules/BookingModule/Routes/api/v1/quality.php`
- `Admin Panel/Modules/BookingModule/Services/BookingWorkflowService.php`
- `Admin Panel/Modules/CustomerSubscriptionModule/Http/Controllers/VisitPackageController.php`
- `Admin Panel/Modules/CustomerSubscriptionModule/Services/VisitPackageService.php`
- `Admin Panel/app/Observers/QualityCommunicationObserver.php`
- `Admin Panel/resources/lang/ar/quality.php`
- `Admin Panel/resources/lang/en/quality.php`
- `Admin Panel/Modules/BookingModule/Database/Migrations/2026_10_07_000001_create_visit_booking_requests.php`
- `User app and web/assets/language/ar.json`
- `User app and web/assets/language/en.json`
- `User app and web/lib/common/widgets/menu_drawer.dart`
- `User app and web/lib/feature/booking/widget/booking_workflow_panel.dart`
- `User app and web/lib/feature/checkout/widget/order_details_section/available_slot_picker.dart`
- `User app and web/lib/feature/profile/view/profile_screen.dart`
- `User app and web/lib/feature/quality/view/quality_center_screen.dart`
- `User app and web/lib/helper/route_helper.dart`
- `User app and web/lib/feature/visit_package/view/package_visit_screen.dart`
- `User app and web/lib/feature/visit_package/view/visit_packages_screen.dart`
- `Provider app/assets/language/ar.json`
- `Provider app/assets/language/en.json`
- `Provider app/lib/feature/booking_details/widget/booking_workflow_panel.dart`
- `Service man app/assets/language/ar.json`
- `Service man app/assets/language/en.json`
- `Service man app/lib/feature/booking_details/widget/service_execution_panel.dart`
