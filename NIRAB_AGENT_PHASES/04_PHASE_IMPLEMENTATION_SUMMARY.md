# تنفيذ المرحلة 4 — إثبات الخدمة والتحصيل والعهدة والتدقيق

تم تعديل السورس الحالي Laravel Modular وFlutter دون إضافة dependencies أو تشغيل النظام أو قاعدة البيانات.

## نطاق التنفيذ
- التسلسل: accepted → on_the_way → arrived → ongoing → work_finished → completed؛ مع تسجيل الوصول والمغادرة والدقة والتوقيت والمدة الفعلية.
- صور before/after/payment_proof/override_evidence مستقلة عبر storage abstraction الحالية، مع بيانات الرافع والتوقيت والموقع الاختياري.
- قوائم خدمة checkbox/text مطلوبة أو اختيارية، مع snapshot مستقل لا يتغير عند تعديل template. تُجمّد قائمة كل خدمة عند إنشاء تفاصيل الطلب؛ الطلبات القديمة تُجمّد مرة واحدة عند أول استخدام بعد التحديث.
- OTP إكمال إلزامي حسب السياسة؛ يُعرض للعميل ويُحجب من serialization لباقي الأدوار. لا يدخل الرمز في التدقيق.
- قائد الفريق أو الفني الأساسي يتحكم بالتنفيذ؛ العضو يحتاج صلاحية team_complete صريحة. الطلب المتكرر ينفذ لكل زيارة، والإغلاق الكلي يتبع إغلاق الزيارات.
- Force Complete لمدير الفرع/HQ بصلاحية force_complete صريحة، وسبب وملاحظة وإثبات عند تفعيل شرط الإثبات.
- التحصيل النقدي/التحويل يسجل المبلغ والطريقة الفعلية والمرجع والإيصال والتوقيت؛ اعتماد الفرع ثم المحاسب منفصل عن إكمال الطلب. يُمنع اعتماد الرافع لطلبه واستخدام نفس المراجع للمرحلتين.
- عند اعتماد المحاسب تُسجل accountant_approved في التدقيق وتصبح العملية ready_for_accounting_sync مع بيانات الاعتماد، ويتحدث is_paid عند تغطية المبلغ المستحق. المدفوع الإلكتروني المؤكد يدخل مباشرة عبر accounting_ready_at دون اعتماد يدوي.
- إعادة الإثبات المرفوض لا تنشئ تحصيلاً أو قيد عهدة جديداً؛ مبلغ التحصيل وطريقته ووقت التحصيل الأصلي ثابتة.
- ledger للعهدة: collected / handed_over / adjustment، وحد افتراضي 3000 SAR يمنع إسناد/قبول طلب Cash جديد فقط، مع تنبيه للفني والفرع.
- تسليم النقد: حجز المبلغ المطلوب، تأكيد المبلغ المستلم بالفرع، تخفيض العهدة بعد التأكيد فقط، ومراجعة المحاسب. منع المبالغ الزائدة والتأكيد المكرر والتعديل الصامت للسجلات المعتمدة.
- تقرير يومي للعهدة الافتتاحية والمحصل والمسلم والتسويات والمتبقي، والتنبيه للتسليم المتأخر، بتوقيت Asia/Riyadh الافتراضي.
- تدقيق مركزي append-only للحالات والإلغاء والإثباتات والقوائم والاعتمادات والتسليم والتسويات وتغييرات السعر/العرض والصلاحيات؛ actor/type/roles/branch/IP/user agent، مع حذف مفاتيح الأسرار وOTP.
- UI الفني يقود التسلسل ويعطل الإكمال عند النقص؛ Dashboard يعرض العهدة والتسليم. تطبيق مدير الفرع يعرض طوابير التحصيل وتأكيد التسليم والعهدة والتنبيهات. لوحة الويب تعرض اعتماد المحاسب والفلاتر والتدقيق وإعداد القوائم والصلاحيات.

## التوافق
- accepted هي حالة التنفيذ المقبول/المسند؛ assignment_status القديم يبقى منفصلاً.
- aliases: assigned → accepted وpending_verification → work_finished في مسار التنفيذ.
- فلتر ongoing القديم يجمع on_the_way/arrived/ongoing/work_finished حتى تبقى الطلبات ظاهرة في التطبيقات.
- evidence_photos القديم محفوظ للتوافق؛ الإثبات الجديد مصنف في booking_evidence.
- المسارات القديمة لتحديث الحالة تمر بالتحقق المركزي قبل notifications/transactions. اعتماد الدفع اليدوي القديم لا يتجاوز المرحلتين.
- اكتمال الخدمة لا يغير حالة الدفع، بما في ذلك عناوين المبالغ في UI والفواتير.
- سياسة الإثبات الجديدة تطبق على الطلبات النشطة أيضاً؛ الطلب الجاري القديم الذي يفتقد صور قبل التنفيذ يحتاج إكمالاً استثنائياً موثقاً بصلاحية، ولا يُنشأ له إثبات تاريخي وهمي.

## Migration الجديدة
`Admin Panel/Modules/BookingModule/Database/Migrations/2026_10_04_100000_create_execution_cash_audit_tables.php`
- تضيف حقول التنفيذ وaccounting_ready_at إلى bookings وbooking_repeats.
- تنشئ service_checklist_items وbooking_checklist_snapshots وbooking_checklist_results وbooking_evidence.
- تنشئ payment_collections وcash_handovers وtechnician_custody_entries وnirab_finance_scopes وaudit_logs.
- تحتوي indexes وforeign keys وdown() مع إسقاط الجداول والحقول الجديدة بالترتيب.
- لم تُنفذ migration ولم تُعدل قاعدة بيانات فعلية.

## العقود والمسارات
جميع المسارات التالية تحت `/api/v1`، وتستخدم envelope الحالي `response_formatter`. `kind` = booking أو booking_repeat.

| الدور | المسار | الغرض |
|---|---|---|
| الفني | GET /serviceman/execution/{kind}/{id} | الحالة، السياسة، النواقص، القوائم والصور |
| الفني | POST .../transition | status + booking_otp أو geo حسب الخطوة |
| الفني | POST .../check-in، POST .../check-out | تسجيل الوصول/انتهاء العمل |
| الفني | POST .../evidence | type + image + geo اختياري |
| الفني | PUT .../checklist | answers: [{id, value}] |
| الفني | POST .../collection | amount، method cash/bank_transfer، proof، reference اختياري، collected_at |
| الفني | GET /serviceman/cash/summary | الرصيد والحد والتقرير والتسليمات |
| الفني | GET /serviceman/cash/collections | طابور التحصيل الخاص بالفني مع subject_id/kind |
| الفني | POST /serviceman/cash/collections/{id}/resubmit | proof جديد وreference دون تكرار القيد |
| الفني | GET/POST /serviceman/cash/handovers | التسليمات/طلب تسليم |
| الفني | GET /serviceman/cash/ledger/{id} | ledger الفني نفسه |
| الفرع/HQ | GET /{provider,admin}/cash/capabilities | الصلاحيات الفعلية |
| الفرع/HQ | GET /{provider,admin}/cash/collections | طابور مع branch/method/date/status |
| الفرع/HQ | POST .../collections/{id}/branch | approve + note؛ سبب مطلوب للرفض |
| المحاسب/HQ | POST /admin/cash/collections/{id}/accountant | الاعتماد الثاني بعد الفرع |
| الفرع/HQ | GET .../cash/handovers، POST .../{id}/confirm | received_amount + note |
| المحاسب/HQ | POST /admin/cash/handovers/{id}/verify | مراجعة تسوية مستلمة |
| المحاسب/HQ | POST /admin/cash/technicians/{id}/adjust | amount موجب/سالب + reason |
| الفرع/HQ/المحاسب | GET .../cash/settlement، ledger/{id}، audit | التقرير والتدقيق داخل scope |
| الفرع/HQ | GET /{provider,admin}/execution/{kind}/{id} | عرض إثبات التنفيذ |
| الفرع/HQ | POST .../evidence، POST .../override | إثبات override ثم reason/note/evidence_id |
| العميل | GET /customer/booking/{id} وsingle/{id} | حقول additive: execution_requires_otp وexecution_evidence |

Web: `/admin/cash` و`/provider/cash`، والإعداد `/admin/cash/setup`. روابطها مضافة للقوائم حسب الصلاحيات.
إعداد صلاحيات accountant_approve/custody_adjust وbranch scope يتم بواسطة super-admin من صفحة الإعداد؛ لا تُمنح الصلاحيات تلقائياً للموظفين.

## إعدادات ENV
في `config/nirab_execution.php` وأسماء/قيم افتراضية في `.env.example`:
NIRAB_EXECUTION_ENABLED، NIRAB_REQUIRE_GEO_CHECK_IN، NIRAB_REQUIRE_GEO_CHECK_OUT،
NIRAB_CHECK_IN_RADIUS_METERS (0 يعطل فحص المسافة)، NIRAB_BEFORE_PHOTO_MINIMUM،
NIRAB_AFTER_PHOTO_MINIMUM، NIRAB_REQUIRE_CUSTOMER_OTP، NIRAB_OVERRIDE_REQUIRES_EVIDENCE،
NIRAB_TECHNICIAN_CASH_CUSTODY_LIMIT (3000)، NIRAB_HANDOVER_OVERDUE_HOURS (24)،
NIRAB_SETTLEMENT_TIMEZONE (Asia/Riyadh).
لا توجد مفاتيح أو secrets جديدة.

## تحقق البناء والتنسيق
- PHP: نجح php -l على 47 ملفاً معدلاً/جديداً.
- PHP الجديد: تنسيق 24 ملفاً باستخدام PhpParser الموجود مسبقاً.
- Blade: compileString + PHP parse لعدد 4 قوالب، دون boot التطبيق أو الاتصال بقاعدة البيانات.
- Composer validate: ناجح؛ تحذيرات constraints القديمة (*) لم تتغير.
- PSR-4 autoload: نجح حل مسارات 18 class جديدة دون boot التطبيق. أوقف فحص optimized classmap الاختياري بعد إطالة التنفيذ دون نتيجة؛ ملف autoload السابق لم يتغير.
- Dart format: نجح على 24 ملفاً عبر التطبيقات الثلاثة؛ تطبيقَا الفني والفرع يفتقدان package resolution لflutter_lints لغياب تعريف الحزم.
- Customer web build: نجح flutter build web --no-pub --no-wasm-dry-run؛ الناتج في User app and web/build/web. استُخدم --no-wasm-dry-run لتجاوز فحص WASM الاختياري الذي لا يناسب universal_html الحالي.
- APK الفني ومدير الفرع: لم ينفذ لغياب .dart_tool/package_config.json/dependencies المهيأة؛ لم تُنشأ بيئة بديلة ولم تُثبت حزم.
- لا سيرفر/Docker/emulator/Artisan migrations/اختبارات runtime/اتصالات فعلية بالخدمات الخارجية.

## إصلاح مانع بناء موجود
كان البناء الأول يتوقف في flutter_html 3.0.0 عند استدعاء qs.matches مع html 0.15.6 المثبتة.
استُبدل Html في html_viewer_screen وpro_plan_terms_dialog_widget بـHtmlWidget من flutter_widget_from_html_core الموجود والمستخدم أصلاً في المشروع، مع المحافظة على حجم فقرة HTML.
لم يُغير pubspec أو pubspec.lock أو cache الحزم. نجح البناء بعد الإصلاح.

## الملفات المعدلة/الجديدة

### Admin Panel

- `Admin Panel/.env.example`
- `Admin Panel/Modules/AdminModule/Resources/views/layouts/partials/_aside.blade.php`
- `Admin Panel/Modules/BookingModule/Database/Migrations/2026_10_04_100000_create_execution_cash_audit_tables.php`
- `Admin Panel/Modules/BookingModule/Entities/Booking.php`
- `Admin Panel/Modules/BookingModule/Entities/BookingChecklistResult.php`
- `Admin Panel/Modules/BookingModule/Entities/BookingEvidence.php`
- `Admin Panel/Modules/BookingModule/Entities/BookingRepeat.php`
- `Admin Panel/Modules/BookingModule/Entities/CashHandover.php`
- `Admin Panel/Modules/BookingModule/Entities/PaymentCollection.php`
- `Admin Panel/Modules/BookingModule/Entities/ServiceChecklistItem.php`
- `Admin Panel/Modules/BookingModule/Entities/TechnicianCustodyEntry.php`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Api/V1/CashOperationsController.php`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Api/V1/Customer/BookingController.php`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Api/V1/ExecutionController.php`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Api/V1/Provider/BookingController.php`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Api/V1/Serviceman/BookingController.php`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Web/Admin/BookingController.php`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Web/CashOperationsController.php`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Web/Provider/BookingController.php`
- `Admin Panel/Modules/BookingModule/Http/Traits/BookingScopes.php`
- `Admin Panel/Modules/BookingModule/Observers/ChecklistSnapshotObserver.php`
- `Admin Panel/Modules/BookingModule/Observers/ExecutionObserver.php`
- `Admin Panel/Modules/BookingModule/Observers/QuotationAuditObserver.php`
- `Admin Panel/Modules/BookingModule/Providers/BookingModuleServiceProvider.php`
- `Admin Panel/Modules/BookingModule/Resources/lang/ar/execution.php`
- `Admin Panel/Modules/BookingModule/Resources/lang/en/execution.php`
- `Admin Panel/Modules/BookingModule/Resources/views/cash/operations.blade.php`
- `Admin Panel/Modules/BookingModule/Resources/views/cash/setup.blade.php`
- `Admin Panel/Modules/BookingModule/Routes/api/v1/api.php`
- `Admin Panel/Modules/BookingModule/Routes/api/v1/execution.php`
- `Admin Panel/Modules/BookingModule/Routes/cash.php`
- `Admin Panel/Modules/BookingModule/Routes/web.php`
- `Admin Panel/Modules/BookingModule/Services/BookingAssignmentService.php`
- `Admin Panel/Modules/BookingModule/Services/BookingExecutionService.php`
- `Admin Panel/Modules/BookingModule/Services/DispatchEngine.php`
- `Admin Panel/Modules/BookingModule/Services/TechnicianCashService.php`
- `Admin Panel/Modules/BookingModule/Services/TrackingService.php`
- `Admin Panel/Modules/ProviderManagement/Resources/views/layouts/partials/_aside.blade.php`
- `Admin Panel/Modules/ServicemanModule/Http/Controllers/Api/V1/Serviceman/ServicemanController.php`
- `Admin Panel/app/Lib/Constant.php`
- `Admin Panel/app/Lib/Helpers.php`
- `Admin Panel/app/Models/AuditLog.php`
- `Admin Panel/app/Models/NirabFinanceScope.php`
- `Admin Panel/app/Providers/AuthServiceProvider.php`
- `Admin Panel/app/Services/AuditService.php`
- `Admin Panel/app/Services/NirabBookingFinancialService.php`
- `Admin Panel/app/Services/NirabFinanceAccess.php`
- `Admin Panel/config/nirab_execution.php`

### Service man app

- `Service man app/assets/language/ar.json`
- `Service man app/assets/language/bn.json`
- `Service man app/assets/language/en.json`
- `Service man app/assets/language/hi.json`
- `Service man app/lib/feature/booking_details/controller/booking_details_controller.dart`
- `Service man app/lib/feature/booking_details/controller/invoice_controller.dart`
- `Service man app/lib/feature/booking_details/widget/booking_details_widget.dart`
- `Service man app/lib/feature/booking_details/widget/booking_summery_widget.dart`
- `Service man app/lib/feature/booking_details/widget/service_execution_panel.dart`
- `Service man app/lib/feature/dashboard/view/dashboard_screen.dart`
- `Service man app/lib/feature/dashboard/widgets/cash_custody_panel.dart`
- `Service man app/lib/theme/custom_theme_colors.dart`

### Provider app

- `Provider app/assets/language/ar.json`
- `Provider app/assets/language/bn.json`
- `Provider app/assets/language/en.json`
- `Provider app/assets/language/hi.json`
- `Provider app/lib/feature/booking_details/controller/invoice_controller.dart`
- `Provider app/lib/feature/booking_details/widget/regular_booking/booking_details.dart`
- `Provider app/lib/feature/booking_details/widget/regular_booking/booking_summery_widget.dart`
- `Provider app/lib/feature/dashboard/view/dashboard_screen.dart`
- `Provider app/lib/feature/reporting/view/branch_cash_operations_screen.dart`
- `Provider app/lib/theme/custom_theme_colors.dart`

### User app and web

- `User app and web/assets/language/ar.json`
- `User app and web/assets/language/bn.json`
- `User app and web/assets/language/en.json`
- `User app and web/assets/language/hi.json`
- `User app and web/lib/feature/booking/controller/invoice_controller.dart`
- `User app and web/lib/feature/booking/model/booking_details_model.dart`
- `User app and web/lib/feature/booking/view/web_booking_details_screen.dart`
- `User app and web/lib/feature/booking/widget/booking_details_section.dart`
- `User app and web/lib/feature/booking/widget/booking_photo_evidence.dart`
- `User app and web/lib/feature/booking/widget/regular/booking_summery_widget.dart`
- `User app and web/lib/feature/booking/widget/repeat/repeat_booking_details_widget.dart`
- `User app and web/lib/feature/html/html_viewer_screen.dart`
- `User app and web/lib/feature/provider/widgets/pro_plan_terms_dialog_widget.dart`
- `User app and web/lib/theme/custom_theme_colors.dart`
