# UX-06 — الإغلاق الساكن وتسليم النسخة

اكتمل نطاق التعديلات المصدرية المحدد بتاريخ 2026-10-07. التفاصيل والحدود في UX_FINAL_CLOSURE_REPORT.md؛ هذه ليست شهادة تشغيل أو جاهزية إنتاج.

## إصلاحات خرجت من مراجعة الإغلاق

- `Admin Panel/Modules/BookingModule/Http/Traits/BookingTrait.php`: addNewBookingService/increase_service_quantity_from_booking/remove_service_from_booking/decrease_service_quantity_from_booking تقفل الحجز وترفض تعديل prepaid_visit قبل أي تعديل خطوط أو أموال؛ تمنع التوسع في الوحدات/الخدمات المغطاة عبر المسارات القديمة.
- `Admin Panel/Modules/CustomerSubscriptionModule/Services/VisitPackageService.php`: resolveVisit يتحقق من إحداثيات العنوان ونصه وجهة الاتصال قبل Spatial؛ reserve القديم أيضاً يرفض quotation_id والمتكرر وخدمة quote/غير نشطة/محذوفة. إعادة usage السابق تبقى idempotent.
- `Admin Panel/app/Observers/QualityCommunicationObserver.php`: حماية snapshot التغطية والعنوان من التعديل بعد usage، ومنع رسوم إضافية على زيارة مغطاة.
- `Admin Panel/Modules/BookingModule/Services/BookingWorkflowService.php`: can_modify_details=false للزيارة المغطاة؛ UI العميل يوضح سياسة الإلغاء المؤهل ثم إعادة الحجز، والفرع والفني يخفيان التعديل غير المدعوم. التقدم التشغيلي والإلغاء والتسوية يظلان عبر محركاتهما الحالية.
- `Admin Panel/Modules/BidModule/Http/Controllers/APi/V1/Customer/QuotationController.php`: store يرفض الخدمة المحذوفة والعنوان غير السليم/المخالف للمنطقة؛ zone الحقيقي من MySQL Spatial، لا مجرد header. index/show يعيدان خدمة الطلب المملوك خارج نطاق منطقة التصفح الحالية، مع translations/fields.
- `Admin Panel/Modules/BidModule/Http/Controllers/APi/V1/Customer/PostController.php`: نفس قراءة خدمة الطلب المملوك في ملخص القبول؛ لا اختفاء الخدمة بتغير منطقة تصفح العميل.
- `User app and web/lib/feature/checkout/controller/schedule_controller.dart` و`widget/order_details_section/available_slot_picker.dart`: مصدر فرع quote لا يأتي من أول عنصر سلة أخرى.
- `User app and web/lib/feature/checkout/repo/checkout_repo.dart` و`controller/checkout_controller.dart`: إعادة التأكيد غير المحسوم تعرض توضيحاً ببيانات المحاولة القديمة ومبلغها، وتحتفظ بالمبلغ المعتمد لمتابعة Offline دون إعادة حسابه من سلة تغيرت.
- `User app and web/lib/feature/booking/widget/booking_workflow_panel.dart` والترجمتان: سياسة تغيير زيارة الباقة. أضيفت أيضاً تسمية طريقة prepaid_visit العربية/الإنجليزية إلى ملفات اللغة الست في التطبيقات الثلاثة بعد مراجعة مفاتيح الحالات الديناميكية.
- `Provider app/lib/feature/booking_details/widget/regular_booking/booking_details.dart` و`Service man app/lib/feature/booking_details/widget/booking_details_widget.dart`: إخفاء زر التعديل بناء على workflow ونوع تغطية الباقة، مع fallback آمن على prepaid_visit للخادم القديم.

## نتائج التدقيق والمخرجات

- 19 PHP lint ناجح؛ 42 Dart analyze دون أخطاء أو warnings، مع 28 info موثقة. ثلاثة Blade تدقيق نصي. JSON والترجمات وUTF-8 وgit diff --check ناجحة ضمن الملفات المعدلة.
- 70 ملف source معدل في أربعة مشاريع؛ الجرد والـSHA-256 في UX_CHANGED_FILES.json، ومرجع main قبل العمل في UX_BASELINE.json.
- لم تتغير الأسرار وAPP_KEY وملفات الهوية/الخطوط والاعتماديات. لم تعاد عمولات أو اشتراكات مزودين، ولم تتغير قاعدة MySQL/Spatial أو محركات الدفع/الباقات/FAQ.
- خريطة API والحقول وحماية المعاملة والـbackward compatibility وترتيب التطبيق الورقي: UX_API_CONTRACT_MAP.md.
- قائمة ونموذج إعداد المحتوى من الإدارة: UX_CONTENT_CONFIGURATION_CHECKLIST.md. لا بيانات مختلقة أو Seeder.
- كل ما لم يُتحقق منه تشغيلياً: UX_NOT_VERIFIED_RUNTIME.md. لا migrations مطبقة، لا نشر أو push.
- سجلات الأوامر والنتائج: UX_STATIC_CHECK_COMMANDS.md + UX_STATIC_*.txt/json.

لا migrations إضافية في UX-06. مهاجرتا UX-03/04 فقط موجودتان وغير مطبقتين. سجل commits لكل مرحلة في التقرير النهائي وUX_COMMITS.json.
