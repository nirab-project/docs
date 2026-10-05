# المرحلة 7 — الإغلاق الأمني وتنظيف Legacy

تاريخ التنفيذ: 2026-10-05. نُفذت التعديلات على السورس الفعلي مع الحفاظ على MySQL 8 وكيان Provider الداخلي، دون Migrations أو Features تشغيلية جديدة.

## المنجز

- حذف `/image-proxy` وتحديث صور العميل والويب لاستخدام التحميل المباشر مع HTML fallback للصور العابرة للدومينات. حذف `/test` و`admin/test-design` وScaffold `/paymentmodule` غير المستخدم.
- نقل `/user` وFallbacks الخاصة بالويب والتثبيت والتحديث إلى Controller قابل للكاش. لا توجد Closure actions إنتاجية متبقية في ملفات Routes التي تمت مراجعتها؛ Group closures ليست Route actions. لم يُنفذ `route:cache`.
- وضع الفرع هو الافتراضي الآمن عند غياب Config. `openTrialEndBottomSheet()` يعيد `true` مباشرة، مع تعطيل فحوص الميزات والتجربة وخطط الاشتراك في Controllers وGuards والتنقل المباشر، وإخفاء المالية القديمة من Profile/Account/Payment/Reports.
- إعادة استخدام `branch_mode.legacy` الموجود لمسارات السحب والبنك والتسويات والعمولات وشراء/تبديل اشتراك المزود. إضافة حواجز إلى نتائج الدفع القديمة وأوامر انتهاء التجربة/الاشتراك والتذكير؛ لم تتغير مسارات فواتير NIRAB وعمليات النقد التشغيلية.
- تجاوز الاستعلامات القديمة للاشتراك والعمولات في Account overview بوضع الفرع، مع إبقاء الملخص التشغيلي وفرق Completed/Paid.
- حماية الجوال والبريد والهوية وFCM وOTP والبيانات الحساسة في JSON والحقول JSON المضمنة. بيانات الاتصال تعتمد على `contacts.read` والفرع؛ صلاحيات HQ العامة تتطلب all_branches. الإبقاء على بريد تسجيل الدخول الاجتماعي المثبت من الخادم وOTP الخاص بصاحب الحجز، ومعالجة الحقول المحجوبة في واجهات العميل والفاتورة والمحادثة.
- إزالة OTP الثابت واستخدام `random_int`، وتنظيف بيانات مدير Seed التجريبية. Seeder يتطلب إعداد بيانات مدير صريحة، ولا يغيّر مديرًا قائمًا. تعطيل أمر Refresh التجريبي خارج demo أو داخل Branch Mode.
- إزالة تسجيل أرقام الجوال/العناوين/رسائل الإشعارات/طلبات وروابط الدفع؛ تسجيل أخطاء العمليات يقتصر على نوع الخطأ وسياقه. أُزيلت بقايا `dd` المعلقة.
- تنظيف `.env.example`: اسم NIRAB، APP_KEY فارغ، Debug معطل، MySQL، مستخدم DB غير root، حذف تكرار DUMP_BINARY_PATH، وإبقاء Tap/Tabby/Tamara/Odoo/SMS/WhatsApp/Backups معطلة افتراضيًا. بوابات SMS القديمة تحترم أيضًا NIRAB_SMS_ENABLED.
- النصوص الأساسية كانت تستخدم Branch/فرع بالفعل؛ أُضيفت ترجمة رسالة تعطيل Legacy بالعربية والإنجليزية.

## التحقق

- `php -l`: نجح على 50 ملف PHP/Blade جديد ومعدل؛ شمل الـController الجديد، وأعيد للملفات التي تغيرت بعد الفحص الأول ونجح.
- `composer validate --no-check-publish --no-plugins`: نجح؛ تحذيرات قديمة تخص قيود إصدارات `*` فقط.
- `composer dump-autoload -o --no-scripts --no-plugins`: أُوقف بعد استغراق المسح وقتًا طويلًا دون نتيجة؛ هذه خطوة اختيارية، ولم تُشغّل Scripts أو Artisan.
- `dart format`: نجح على 84 ملف Dart معدل. فحص المسافات `git diff --check` نجح في المشاريع الأربعة. JSON الترجمة صالح، ومتغيرات `.env.example` بلا تكرار.
- Flutter للعميل: Analyze نجح بلا Errors/Warnings مع 25 ملاحظة lint من مستوى info باستخدام --no-fatal-infos --no-fatal-warnings. نجح Build Web Release النهائي بالأمر flutter build web --release --no-pub --no-web-resources-cdn --no-wasm-dry-run؛ المخرجات في User app and web/build/web. أُصلح الاستيراد الذي كشفه التحليل وأعيد التحليل والبناء بنجاح.
- الفرع والفني: تعذر حل Dependencies من الكاش المحلي فقط؛ `loading_animation_widget` غير متاح للفرع و`flutter_switch` غير متاح للفني. لم تُنزّل Dependencies أو تُجهز Toolchain جديدة؛ لم يُنفذ Analyze/Build لهذين التطبيقين.
- لم يُشغّل Server أو Docker أو Emulator أو Artisan أو Migration أو Seeder أو PHPUnit/Pest/Flutter/E2E tests أو أي اتصال بتكامل خارجي.

## لاحقًا عند النشر

- نشر Backend والتطبيقات المتأثرة معًا، ثم إعادة إنشاء كاش Routes في بيئة النشر وفق الإجراءات المعتادة؛ لم تُنفذ إجراءات نشر هنا.
- إعداد APP_KEY وDB credentials خارج السورس. إبقاء NIRAB_BRANCH_MODE=true، وإعداد صلاحيات contacts.read/locations.read وفق الأدوار والفروع.
- تفعيل SMS صراحةً بـ NIRAB_SMS_ENABLED بعد إعداد مزود حقيقي؛ البوابات القديمة لم تعد تتجاوز هذا العلم. بقية التكاملات والنسخ الاحتياطية تحتاج إعدادًا صريحًا قبل التفعيل.
- استكمال Analyze/Build للفرع والفني على جهاز يملك Dependencies المطلوبة. iOS لم يُبنَ لأن الجهاز Windows.

## الملفات

الملف البرمجي الجديد: `Admin Panel/app/Http/Controllers/SystemRouteController.php`. لا توجد Migrations جديدة أو APIs تشغيلية جديدة. القائمة التالية تشمل الملفات المعدلة في كل مشروع؛ يتضمن حجم Diff أيضًا Dart format المطلوب.


<details>
<summary>Admin Panel — 50 ملفًا معدلًا</summary>

- `Admin Panel/.env.example`
- `Admin Panel/Modules/AdminModule/Http/Controllers/Web/Admin/Analytics/SearchController.php`
- `Admin Panel/Modules/AdminModule/Routes/web.php`
- `Admin Panel/Modules/Auth/Http/Controllers/Api/V1/LoginController.php`
- `Admin Panel/Modules/Auth/Http/Controllers/Api/V1/RegisterController.php`
- `Admin Panel/Modules/Auth/Http/Controllers/RegisterController.php`
- `Admin Panel/Modules/Auth/Http/Controllers/Web/PasswordResetController.php`
- `Admin Panel/Modules/Auth/Http/Controllers/Web/VerificationController.php`
- `Admin Panel/Modules/BidModule/Http/Controllers/APi/V1/Provider/PostBidController.php`
- `Admin Panel/Modules/BookingModule/Entities/Booking.php`
- `Admin Panel/Modules/BookingModule/Entities/BookingRepeat.php`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Web/Provider/BookingController.php`
- `Admin Panel/Modules/BookingModule/Http/Traits/BookingTrait.php`
- `Admin Panel/Modules/BookingModule/Listeners/SendBookingRequestEmail.php`
- `Admin Panel/Modules/BookingModule/Resources/views/provider/booking/list.blade.php`
- `Admin Panel/Modules/BusinessSettingsModule/Http/Controllers/Web/Admin/ConfigurationController.php`
- `Admin Panel/Modules/BusinessSettingsModule/Http/Controllers/Web/Admin/SubscriberController.php`
- `Admin Panel/Modules/BusinessSettingsModule/Http/Controllers/Web/Admin/SubscriptionPackageController.php`
- `Admin Panel/Modules/BusinessSettingsModule/Http/Controllers/Web/Provider/SubscriptionPackageController.php`
- `Admin Panel/Modules/BusinessSettingsModule/Providers/BusinessSettingsModuleServiceProvider.php`
- `Admin Panel/Modules/CustomerModule/Http/Controllers/Web/Admin/CustomerController.php`
- `Admin Panel/Modules/PaymentModule/Lib/PaymentResponse.php`
- `Admin Panel/Modules/PaymentModule/Lib/PaymentSuccess.php`
- `Admin Panel/Modules/PaymentModule/Routes/api.php`
- `Admin Panel/Modules/PaymentModule/Traits/SmsGateway.php`
- `Admin Panel/Modules/ProviderManagement/Http/Controllers/Api/V1/Provider/AccountController.php`
- `Admin Panel/Modules/ProviderManagement/Http/Controllers/Api/V1/Provider/ConfigController.php`
- `Admin Panel/Modules/ProviderManagement/Http/Controllers/Api/V1/Provider/ProviderController.php`
- `Admin Panel/Modules/ProviderManagement/Http/Controllers/Web/Admin/ProviderController.php`
- `Admin Panel/Modules/ProviderManagement/Http/Controllers/Web/Admin/SubscriptionController.php`
- `Admin Panel/Modules/SMSModule/Lib/SMS_gateway.php`
- `Admin Panel/Modules/ServiceManagement/Http/Controllers/Web/Provider/ServiceController.php`
- `Admin Panel/Modules/ServicemanModule/Http/Controllers/Api/V1/Serviceman/ServicemanController.php`
- `Admin Panel/Modules/TransactionModule/Entities/Account.php`
- `Admin Panel/Modules/UserManagement/Database/Seeders/UserTableSeederTableSeeder.php`
- `Admin Panel/Modules/UserManagement/Entities/User.php`
- `Admin Panel/Modules/UserManagement/Http/Controllers/Api/V1/OTPVerificationController.php`
- `Admin Panel/Modules/UserManagement/Http/Controllers/Api/V1/PasswordResetController.php`
- `Admin Panel/app/Console/Commands/DatabaseRefresh.php`
- `Admin Panel/app/Console/Commands/FreeTrialEnd.php`
- `Admin Panel/app/Console/Commands/SendRenewalReminderEmail.php`
- `Admin Panel/app/Console/Commands/SubscriptionTimeEnd.php`
- `Admin Panel/app/Http/Middleware/ProtectPersonalData.php`
- `Admin Panel/app/Providers/AppServiceProvider.php`
- `Admin Panel/resources/lang/ar/lang.php`
- `Admin Panel/resources/lang/en/lang.php`
- `Admin Panel/routes/api.php`
- `Admin Panel/routes/install.php`
- `Admin Panel/routes/update.php`
- `Admin Panel/routes/web.php`

</details>

<details>
<summary>Provider app — 51 ملفًا معدلًا</summary>

- `Provider app/assets/language/ar.json`
- `Provider app/assets/language/en.json`
- `Provider app/lib/common/model/config_model.dart`
- `Provider app/lib/feature/auth/controller/sign_up_controller.dart`
- `Provider app/lib/feature/auth/repository/auth_repo.dart`
- `Provider app/lib/feature/auth/view/sign_up_screen.dart`
- `Provider app/lib/feature/auth/widgets/file_upload_field_widget.dart`
- `Provider app/lib/feature/booking_details/controller/invoice_controller.dart`
- `Provider app/lib/feature/booking_details/widget/regular_booking/booking_details.dart`
- `Provider app/lib/feature/booking_details/widget/repeat_booking/repeat_booking_details.dart`
- `Provider app/lib/feature/booking_details/widget/repeat_booking/repeat_booking_service_log.dart`
- `Provider app/lib/feature/booking_requests/controller/calendar_controller.dart`
- `Provider app/lib/feature/booking_requests/widgets/booking_request_item.dart`
- `Provider app/lib/feature/dashboard/view/dashboard_screen.dart`
- `Provider app/lib/feature/dashboard/view/payment_screen.dart`
- `Provider app/lib/feature/dashboard/widgets/business_summery_section.dart`
- `Provider app/lib/feature/dashboard/widgets/earning_statistics_widget.dart`
- `Provider app/lib/feature/dashboard/widgets/my_subscription_section.dart`
- `Provider app/lib/feature/location/view/update_customer_address.dart`
- `Provider app/lib/feature/menu/view/menu_screen.dart`
- `Provider app/lib/feature/nav/bottom_nav_screen.dart`
- `Provider app/lib/feature/payement_information/controller/payment_info_controller.dart`
- `Provider app/lib/feature/payement_information/view/add_payment_info_screen.dart`
- `Provider app/lib/feature/payement_information/view/payment_information_screen.dart`
- `Provider app/lib/feature/profile/controller/user_controller.dart`
- `Provider app/lib/feature/profile/view/account_information/view/account_information.dart`
- `Provider app/lib/feature/profile/view/bank_information/controller/bank_info_controller.dart`
- `Provider app/lib/feature/profile/view/view/profile_screen.dart`
- `Provider app/lib/feature/profile/widgets/update_additional_files_widget.dart`
- `Provider app/lib/feature/reporting/controller/booking_report_controller.dart`
- `Provider app/lib/feature/reporting/controller/business_report_controller.dart`
- `Provider app/lib/feature/reporting/controller/transaction_report_controller.dart`
- `Provider app/lib/feature/reporting/view/business_report.dart`
- `Provider app/lib/feature/reporting/view/report_navigation_view.dart`
- `Provider app/lib/feature/reporting/view/transaction_report.dart`
- `Provider app/lib/feature/reporting/widgets/booking_report/booking_report_bar_chart.dart`
- `Provider app/lib/feature/review/controller/review_controller.dart`
- `Provider app/lib/feature/serviceman/widget/add_new_serviceman_acount_info.dart`
- `Provider app/lib/feature/splash/controller/splash_controller.dart`
- `Provider app/lib/feature/subscriptions/controller/business_subscription_controller.dart`
- `Provider app/lib/feature/subscriptions/controller/subscription_invoice_controller.dart`
- `Provider app/lib/feature/subscriptions/view/business/business_plan_screen.dart`
- `Provider app/lib/feature/subscriptions/widget/business/subscription_transaction_listview.dart`
- `Provider app/lib/feature/transaction/controller/transaction_controller.dart`
- `Provider app/lib/feature/transaction/view/withdraw_list_screen.dart`
- `Provider app/lib/helper/country_code_helper.dart`
- `Provider app/lib/helper/file_validation_helper.dart`
- `Provider app/lib/helper/notification_helper.dart`
- `Provider app/lib/helper/route_helper.dart`
- `Provider app/lib/helper/validation_helper.dart`
- `Provider app/lib/main.dart`

</details>

<details>
<summary>Service man app — 6 ملفًا معدلًا</summary>

- `Service man app/lib/feature/auth/repository/auth_repo.dart`
- `Service man app/lib/feature/booking_details/controller/invoice_controller.dart`
- `Service man app/lib/feature/booking_details/widget/booking_details_widget.dart`
- `Service man app/lib/helper/file_validation_helper.dart`
- `Service man app/lib/helper/notification_helper.dart`
- `Service man app/lib/helper/validation_helper.dart`

</details>

<details>
<summary>User app and web — 29 ملفًا معدلًا</summary>

- `User app and web/lib/common/repo/data_sync_repo.dart`
- `User app and web/lib/common/widgets/custom_image.dart`
- `User app and web/lib/common/widgets/not_found_screen.dart`
- `User app and web/lib/common/widgets/notification_helper.dart`
- `User app and web/lib/common/widgets/service_center_dialog.dart`
- `User app and web/lib/common/widgets/zoom_image.dart`
- `User app and web/lib/feature/address/view/add_address_screen.dart`
- `User app and web/lib/feature/auth/controller/auth_controller.dart`
- `User app and web/lib/feature/auth/controller/facebook_login_controller.dart`
- `User app and web/lib/feature/auth/repository/auth_repo.dart`
- `User app and web/lib/feature/auth/widgets/file_upload_field_widget.dart`
- `User app and web/lib/feature/booking/controller/invoice_controller.dart`
- `User app and web/lib/feature/booking/view/booking_details_screen.dart`
- `User app and web/lib/feature/booking/view/repeat_booking_details_screen.dart`
- `User app and web/lib/feature/booking/widget/booking_item_card.dart`
- `User app and web/lib/feature/booking/widget/repeat/repeat_booking_service_log_widget.dart`
- `User app and web/lib/feature/checkout/controller/checkout_controller.dart`
- `User app and web/lib/feature/checkout/view/payment_screen.dart`
- `User app and web/lib/feature/conversation/view/conversation_details_screen.dart`
- `User app and web/lib/feature/language/controller/localization_controller.dart`
- `User app and web/lib/feature/location/widget/pickmap_dialog_widget.dart`
- `User app and web/lib/feature/profile/widget/update_additional_files_widget.dart`
- `User app and web/lib/feature/provider/view/become_a_provider.dart`
- `User app and web/lib/helper/analytics/analytics_helper.dart`
- `User app and web/lib/helper/analytics/tiktok_analytics.dart`
- `User app and web/lib/helper/country_code_helper.dart`
- `User app and web/lib/helper/file_validation_helper.dart`
- `User app and web/lib/helper/phone_verification_helper.dart`
- `User app and web/lib/helper/route_helper.dart`

</details>

