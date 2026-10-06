# NIRAB — إغلاق الدمج المحلي على main

أُنجز دمج المصدر والإصلاحات محلياً. اعتماد البناء الكامل معلق: نجح Customer Web، وتعذر Android بسبب الأدوات المحلية، وتعذر تحليل/بناء المزود والفني بسبب التبعيات الناقصة. لا يُعلن Code Freeze ولا اعتماد إنتاجي لهذه النسخة. نتائج F01–F04/P01–P02 أدلة قراءة وكود، وليست نتائج تشغيل أو اختبارات.

مسارات `logs/` و`evidence/` في التقرير وmanifest محسوبة من مجلد التسليم `NIRAB-FINAL-CLOSURE`. توجد نسخة محفوظة من السجلات والأدلة في مستودع Docs داخل `NIRAB_FINAL_CLOSURE_EVIDENCE/`. عمليات القراءة والبناء التي نفذت ولم تبدأ خدمة أو تطبيقاً موثقة هناك.

## المراجع الفعلية ونقاط الاستعادة

| المستودع | MAIN_BASE_SHA | STAGING_SOURCE_SHA | FINAL MAIN SHA | حالة المصدر البعيد |
|---|---|---|---|---|
| Admin Panel | `55da3ab139939c6412f15fc702bd09dde9610827` | `غير موجود` | `0a4f22f79e3d67cc78900bfeda8f8b524b6237fd` | REMOTE_UPDATE_PENDING |
| User app and web | `35e9e49e7d9a3b2b73bcf0cd5c79afeaf5260c8b` | `107f5c48ffa682e21a648032c6a6be0532839621` | `c2c95c17298dfc9915eacfc59dc24de24fe475e1` | REMOTE_UPDATE_PENDING |
| Provider app | `d1fd060586d3048aa90250535d6c693cc503bd85` | `غير موجود` | `d1fd060586d3048aa90250535d6c693cc503bd85` | UNCHANGED_REMOTE_MAIN |
| Service man app | `886bded47a8f8955908c845f237dd314c6ba7111` | `غير موجود` | `886bded47a8f8955908c845f237dd314c6ba7111` | UNCHANGED_REMOTE_MAIN |

كل المستودعات كانت نظيفة عند البدء. جرى fetch فقط، ولم ينفذ push. مرجع الاستعادة في كل مستودع: `backup/final-closure-20261006-184742-main`، وللعميل أيضاً `backup/final-closure-20261006-184742-staging`. بقي staging ومرجع stash السابق كما هما. جميع MAIN_BASE_SHA أسلاف للنسخة النهائية. دمج العميل commit بوالدين فعليين: baseline main وstaging؛ ليس squash أو merge باستراتيجية ours. لا staging محلي/بعيد في بقية المستودعات؛ منطق المراحل موجود فيها أصلاً على main، ولذلك لم يُنشأ مصدر staging وهمي أو commit صوري لتطبيقي المزود والفني.

أُنشئت commits الكود أولاً. هذا التقرير يُحفظ في Docs في commit توثيق لاحق؛ manifest الخارجي يسجل SHA ذلك الـcommit بعد إنشائه. لا محاولة لكتابة hash التوثيق داخل نفسه. تقرير المراجعة R0 المشار إليه في التكليف لم يكن ضمن الملفات المتاحة؛ استُخدمت الملاحظات المفصلة في التكليف نفسه وقراءة المصدر الحالي.

## حفظ واجهة main

حُلّت التعارضات التسعة عبر مقارنة ثلاثية بعد تنسيق نسخ منفصلة من base/main/staging، ثم مراجعة الدمج. حُفظت BrandTokens والوضعان الفاتح والداكن والخطوط والشعارات والأصول وأسماء الحزم ومعرّفات Android/iOS/Web. أضيف لون الحالة assigned إلى خريطة main فقط. تصحيح BrandLogo يخص التعامل الآمن مع BuildContext دون تغيير أصل الشعار أو أبعاده. لا استبدال شامل لـ lib أو views أو public ولا اعتماد عام لـ ours/theirs.

- Admin Panel: فحص 1718 مسار هوية/قالب/أصل؛ تغييرات الهوية المحمية: 0.
- User app and web: فحص 334 مسار هوية/قالب/أصل؛ تغييرات الهوية المحمية: 0.
- Provider app: فحص 477 مسار هوية/قالب/أصل؛ تغييرات الهوية المحمية: 0.
- Service man app: فحص 288 مسار هوية/قالب/أصل؛ تغييرات الهوية المحمية: 0.

التغييرات المرئية اللازمة: واجهات slots وworkflow/tracking والفواتير/العروض، وإظهار نافذة اعتماد السعر والمواعيد باستخدام ConfirmationDialog الحالي. الترجمات العربية والإنجليزية مدمجة دون إعادة تصميم التنقل. أضيف pubspec.lock الموجود والمستخدم فعلياً إلى Git للعميل؛ لم تُرقّ الحزم أو SDK. لا lockfile محلول للمزود والفني، وسُجل المانع بدلاً من اختلاقه.

## إغلاق الملاحظات بالأدلة

### F01: PASS (مراجعة مصدر)

صور مباشرة مع loading/fallback؛ اختيار Slots حقيقية للعادي وكل تكرار والعرض؛ مراجعة السعر بمكونات main؛ Workflow وحالة الفريق والفواتير. التتبع يمنع الطلبات المتداخلة، يتوقف بالخلفية/الخروج/فقد الإذن/الحالات النهائية، ويتحمل الإحداثيات غير الصالحة.

الدوال/المواضع النهائية:

- `User app and web/lib/common/widgets/custom_image.dart:3`
- `User app and web/lib/common/widgets/zoom_image.dart:5`
- `User app and web/lib/feature/checkout/widget/order_details_section/available_slot_picker.dart:40`
- `User app and web/lib/feature/booking/widget/booking_workflow_panel.dart:84`
- `User app and web/lib/feature/booking/widget/booking_invoices_panel.dart:5`
- `Admin Panel/Modules/BookingModule/Services/TrackingService.php:67`

### F02: PASS (مراجعة مصدر)

DTO بقائمة حقول الفرع العامة فقط، ومُعرّف داخلي مسجل في نفس الطلب للتحقق منه قبل استثناء DTO من ترشيح البيانات الشخصية. لا owner/staff/bank داخل DTO، ولا توريث للاستثناء إلى عناصر أخرى. حماية الأشخاص والصلاحيات الأصلية باقية. الخريطة تتجاهل الإحداثيات المفقودة/غير المحدودة ولا تصنع موقع 0,0.

الدوال/المواضع النهائية:

- `Admin Panel/Modules/ProviderManagement/Http/Resources/PublicBranchResource.php:11`
- `Admin Panel/app/Http/Middleware/ProtectPersonalData.php:48`
- `User app and web/lib/helper/map_bound_helper.dart:23`
- `User app and web/lib/feature/provider/controller/nearby_provider_controller.dart:350`
- `User app and web/lib/feature/provider/controller/provider_booking_controller.dart:508`

### F03: PASS (مراجعة مصدر)

منطقة الخدمة تأتي من عنوان التنفيذ وZone الفعلية واستعلام polygon في GeoService. ترتيب التداخل priority ثم id، وترتيب الفرع النهائي ثابت أيضاً. نفس area تُمرر للإتاحة والتسعير والعروض والتأكيد. لا يُعتمد area_id المرسل من العميل. يُعاد فحص الفرع/التغطية/السعة والمواعيد بعد الأقفال وعند إدخال كل زيارة. ST_Contains يحافظ على سياسة استبعاد حد المضلع؛ fallback الحالي لا يتغير.

الدوال/المواضع النهائية:

- `Admin Panel/Modules/ProviderManagement/Services/BranchServiceAreaRepository.php:21`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Api/V1/Customer/DispatchController.php:45`
- `Admin Panel/Modules/ServiceManagement/Http/Controllers/Api/V1/Customer/PricingController.php:31`
- `Admin Panel/Modules/BookingModule/Services/BranchCheckoutService.php:330`
- `Admin Panel/Modules/BidModule/Http/Controllers/APi/V1/Customer/QuotationController.php:241`

### F04: PASS (مراجعة مصدر)

مرجع سعر مشفر في الخادم لمدة 15 دقيقة، يتضمن الفرع والمنطقة والعنوان والسلة والزيارات والخصومات والضرائب والمبلغ والمحفظة. المراجعة تعرض المبلغ والمواعيد والفرع قبل التأكيد؛ cash/offline/wallet يعيد التحقق ويرفض التغير، والدفع المثبت ينفذ المرجع المحفوظ دون إعادة تسعير. تكرار الطلب يعيد نتيجة الحجز المحفوظة. إيصال paid يبقى إذا تعذر الإنهاء ويُعلّم pending_review، وتُعزل جميع كتابة الإنهاء في savepoint. I/O للبوابة خارج أقفال DB. العرض يحافظ على السعر/خصم المعاينة ويُقارن بالسعر المعروض، ودفعة المحفظة ثابتة. تُوزع قيود الدفعات المتكررة على الزيارات دون مضاعفة رصيد الحساب.

الدوال/المواضع النهائية:

- `Admin Panel/Modules/BookingModule/Services/BranchCheckoutService.php:25`
- `Admin Panel/Modules/BookingModule/Services/BranchCheckoutService.php:44`
- `Admin Panel/Modules/BookingModule/Services/BranchCheckoutService.php:72`
- `Admin Panel/Modules/BookingModule/Services/BranchCheckoutService.php:314`
- `Admin Panel/Modules/PaymentModule/Services/GatewayPaymentService.php:18`
- `Admin Panel/Modules/PaymentModule/Services/GatewayPaymentService.php:89`
- `Admin Panel/Modules/BidModule/Http/Controllers/APi/V1/Customer/QuotationController.php:155`
- `User app and web/lib/feature/checkout/controller/checkout_controller.dart:20`

### P01: PASS (فحص ساكن وتجميع)

ملفا JS موجودان أصلاً في main، محفوظان ومضمنان في الأرشيف. 20 + 29 مرجع byId حرفي تطابق القوالب دون عنصر مفقود. workforce يستخدم api المشترك المُصدّر من operations الذي يرسل CSRF وsame-origin، والقالب يحمّلهما بالترتيب. لم تُعطّل الصلاحيات أو CSRF.

الدوال/المواضع النهائية:

- `Admin Panel/public/assets/admin-module/js/nirab-operations.js:21`
- `Admin Panel/public/assets/admin-module/js/nirab-workforce.js:3`
- `Admin Panel/Modules/ServiceManagement/Resources/views/admin/operations.blade.php:117`
- `Admin Panel/Modules/ServiceManagement/Routes/web.php:15`

### P02: PASS (مراجعة مصدر)

حواجز Marketplace المالي باقية في فرع branch mode للعمولات/السحب/اشتراك المزود وحساباته. لا proxy فعال ولا OTP ثابت في مسح الكود، ولا مفاتيح خاصة/علامات تعارض في نطاق الفحص. لم يتغير APP_KEY أو DB engine أو التوقيع أو هوية التطبيقات. اكتمال التشغيل لا يجعل الحجز مدفوعاً؛ المحاسبة تتطلب دفعاً مسجلاً.

الدوال/المواضع النهائية:

- `Admin Panel/app/Http/Middleware/BranchModeLegacyFeatureMiddleware.php:11`
- `Admin Panel/Modules/ProviderManagement/Routes/api/v1/api.php:30`
- `Admin Panel/app/Services/NirabBookingFinancialService.php:27`

حدود F04 المتعمدة: الدفع الجزئي يتطلب مجموعة حجز واحدة ويُرفض قبل الاعتماد خلاف ذلك؛ الدفع الكامل بالمحفظة يدعم المجموعات. المبلغ النهائي لكل زيارة مقرب إلى منزلتين موافقتين للبوابة، مع الاحتفاظ بتفاصيل التسعير. فشل إرسال إشعار بعد commit لا يلغي الحجز أو إيصال الدفع، ويترك تحذيراً عاماً لإعادة المحاولة. حالة pending_review تتطلب مراجعة الفريق ولا تنشئ refund تلقائياً.

## عقود التطبيقات والصلاحيات

| العقد | دليل القراءة النهائي |
|---|---|
| Slots والفرع والتسعير | `User app and web/lib/feature/checkout/widget/order_details_section/available_slot_picker.dart:76`؛ `Admin Panel/Modules/BookingModule/Routes/api/v1/api.php:18` |
| المزود: إسناد/فرق/مناطق | `Provider app/lib/util/app_constants.dart:28`؛ `Admin Panel/Modules/BookingModule/Routes/api/v1/api.php:79` |
| الفني: قبول/رفض وموقع/فريق | `Service man app/lib/feature/booking_details/repository/booking_details_repo.dart:32`؛ `Admin Panel/Modules/BookingModule/Routes/api/v1/api.php:98` |
| صلاحية قائد الفريق والتنفيذ | `Admin Panel/Modules/BookingModule/Services/BookingExecutionService.php:50`؛ `Service man app/lib/feature/booking_details/widget/booking_details_widget.dart:118` |
| حماية الحالة المالية | `Admin Panel/Modules/BookingModule/Services/TechnicianCashService.php:49`؛ `Admin Panel/app/Services/NirabBookingFinancialService.php:27` |

توافق أسماء الحقول والحالات والـHTTP methods فُحص بالقراءة. لا تثبت هذه المراجعة نجاح سير عملي end-to-end. فحص imports المحلية لـDart لم يجد مساراً فعالاً مفقوداً أو اختلاف case؛ السطور الثلاثة القديمة المعلّقة ليست imports فعالة. Composer لم يبلغ تعارض PSR/classes.

## فحوص النسخة النهائية

الأدوات: Flutter 3.41.4، Dart 3.11.1، PHP 8.4.24، Composer 2.10.1، Node v26.7.0، Git 2.53.0.windows.2. بيئة Windows. لم تثبت أو تحدث Toolchain أو dependencies.

| المكون/الفحص | الأمر | الحالة | Exit | السجل |
|---|---|---|---|---|
| Admin Panel / composer-validate | `composer --no-plugins --no-scripts validate --no-check-publish` | PASS | 0 | `logs/composer-validate.log` |
| Admin Panel / composer-autoload | `composer --no-plugins --no-scripts dump-autoload --optimize` | PASS | 0 | `logs/composer-autoload.log` |
| Admin Panel / php-lint | `php -l <every tracked non-Blade PHP source, excluding vendor/storage/cache>` | PASS | 0 | `logs/backend-php-lint.log` |
| Admin Panel / nirab-operations-syntax | `node --check public/assets/admin-module/js/nirab-operations.js` | PASS | 0 | `logs/nirab-operations-syntax.log` |
| Admin Panel / nirab-workforce-syntax | `node --check public/assets/admin-module/js/nirab-workforce.js` | PASS | 0 | `logs/nirab-workforce-syntax.log` |
| Admin Panel / blade-compile | `php C:\Users\PC\Desktop\Projects\nirab-project\NIRAB-FINAL-CLOSURE\scripts\compile_blade.php C:\Users\PC\Desktop\Projects\nirab-project\Admin Panel C:\Users\PC\Desktop\Projects\nirab-project\NIRAB-FINAL-CLOSURE\evidence\compiled-blade` | PASS | 0 | `logs/blade-compile.log` |
| Admin Panel / blade-php-lint | `php -l <standalone compiled Blade templates>` | PASS | 0 | `logs/blade-php-lint.log` |
| User app and web / dart-format | `dart format --output=none --set-exit-if-changed <modified Dart files>` | PASS | 0 | `logs/dart-format-verification.log` |
| User app and web / analyze-default | `flutter analyze --no-pub` | FAIL | 1 | `logs/flutter-analyze-customer-final.log` |
| User app and web / analyze-errors-warnings | `flutter analyze --no-pub --no-fatal-infos` | PASS | 0 | `logs/flutter-analyze-customer-no-fatal-infos.log` |
| User app and web / web-release | `flutter --suppress-analytics build web --release --no-pub --no-web-resources-cdn` | PASS | 0 | `logs/flutter-build-web-final.log` |
| Provider app / offline-dependencies | `flutter pub get --offline` | BLOCKED_MISSING_DEPENDENCY | 69 | `logs/provider-pub-offline.log` |
| Provider app / analyze | `flutter analyze --no-pub` | BLOCKED_MISSING_DEPENDENCY | لم ينفذ | `logs/provider-pub-offline.log` |
| Provider app / dart-format | `No modified Dart files` | NOT_APPLICABLE | لم ينفذ | `لا ينطبق` |
| Service man app / offline-dependencies | `flutter pub get --offline` | BLOCKED_MISSING_DEPENDENCY | 69 | `logs/serviceman-pub-offline.log` |
| Service man app / analyze | `flutter analyze --no-pub` | BLOCKED_MISSING_DEPENDENCY | لم ينفذ | `logs/serviceman-pub-offline.log` |
| Service man app / dart-format | `No modified Dart files` | NOT_APPLICABLE | لم ينفذ | `لا ينطبق` |
| User app and web / android | `Preflight only; no Gradle build invoked` | BLOCKED_MISSING_TOOLCHAIN | لم ينفذ | `evidence/platform-preflight.json` |
| User app and web / ios | `Not invoked on Windows` | NOT_APPLICABLE | لم ينفذ | `evidence/platform-preflight.json` |
| Provider app / android | `Preflight only; no Gradle build invoked` | BLOCKED_MISSING_TOOLCHAIN | لم ينفذ | `evidence/platform-preflight.json` |
| Provider app / ios | `Not invoked on Windows` | NOT_APPLICABLE | لم ينفذ | `evidence/platform-preflight.json` |
| Service man app / android | `Preflight only; no Gradle build invoked` | BLOCKED_MISSING_TOOLCHAIN | لم ينفذ | `evidence/platform-preflight.json` |
| Service man app / ios | `Not invoked on Windows` | NOT_APPLICABLE | لم ينفذ | `evidence/platform-preflight.json` |

نجح syntax لجميع 1111 ملف PHP مفحوص. جُمعت 56 قالب Blade مستقلاً دون bootstrap أو render أو DB؛ فُحصت 112 نسخة PHP ناتجة من تهجئتين لمسار الجذر تمثل القوالب الـ56 نفسها. نجح Node لكلا الملفين، ونجح Composer autoload لـ16234 class مع scripts/plugins معطلة وCOMPOSER_DISABLE_NETWORK=1. تحذيرات Composer الخمسة عن قيود إصدارات واسعة سابقة ولم تغير.

تحليل العميل الافتراضي أعاد 1 بسبب 24 info فقط، دون errors أو warnings؛ أُثبت وجود كل سطر مشار إليه في baseline main أو staging في `evidence/customer-analysis-infos.json`. التحليل التكميلي --no-fatal-infos أعاد 0 ولا يخفي تلك الملاحظات. لم تضاف suppressions. بناء Web هو JavaScript Release ناجح؛ تحذيرات wasm لا تُعد نجاح بناء wasm أو Android.

الربط بالنسخة: `evidence/verification-final.json` يسجل commit/tree وأمر/نسخة أداة/exit/log لكل هدف. ابتدأ مشغّل فحص Backend قبل تعديلات محصورة في أجسام دوال أربعة ملفات؛ أُعيد php -l للملفات الأربعة بعد commit وأُثبت تطابق composer.json/lock وتصريحات PSR/classes مع نهاية الفحص. التفصيل في `evidence/verification-tree-reconciliation.json` و`logs/backend-final-recheck.log`. بناء Web والتحليل النهائي يخصان tree العميل المسجل دون تعديل مصدر بعده.

Android: لا SDK platforms أو build-tools ولا Gradle 8.14.3-all في cache. لم يُشغّل Gradle كي لا ينزل الأدوات. المزود: loading_animation_widget ناقصة؛ الفني: flutter_switch ناقصة؛ offline pub get أعاد 69 لكليهما. لم ينفذ analyze غير صالح بلا package_config. iOS غير متاح على Windows. راجع `evidence/platform-preflight.json`.

## الملفات والتغييرات وقاعدة البيانات

أضيفت migration واحدة: `Modules/BookingModule/Database/Migrations/2026_10_06_230000_create_booking_checkout_snapshots.php`. تنشئ جدول checkout snapshots وإشارة payment_request فريدة مع payload مشفر على مستوى التطبيق. **لم تُشغّل**، ولم يُعدّل أي migration تاريخي. down يرفض إسقاط الدليل المالي تلقائياً؛ يتطلب خطة أرشفة/استعادة يراجعها الفريق. APP_KEY الحالي مطلوب لقراءة payload؛ لم يُقرأ أو يُغيّر. بقي محرك MySQL وإعداد المنطقة الزمنية كما هما.

### Admin Panel: 22 ملف متغير عن baseline

- `Modules/BidModule/Http/Controllers/APi/V1/Customer/QuotationController.php`
- `Modules/BookingModule/Database/Migrations/2026_10_06_230000_create_booking_checkout_snapshots.php`
- `Modules/BookingModule/Entities/BookingCheckoutSnapshot.php`
- `Modules/BookingModule/Http/Controllers/Api/V1/Customer/BookingController.php`
- `Modules/BookingModule/Http/Controllers/Api/V1/Customer/DispatchController.php`
- `Modules/BookingModule/Http/Traits/BookingTrait.php`
- `Modules/BookingModule/Routes/api/v1/api.php`
- `Modules/BookingModule/Services/BranchCheckoutService.php`
- `Modules/BookingModule/Services/TechnicianCashService.php`
- `Modules/PaymentModule/Http/Controllers/GatewayController.php`
- `Modules/PaymentModule/Http/Controllers/PaymentController.php`
- `Modules/PaymentModule/Lib/PaymentResponse.php`
- `Modules/PaymentModule/Services/GatewayPaymentService.php`
- `Modules/PaymentModule/Traits/Payment.php`
- `Modules/ProviderManagement/Entities/Provider.php`
- `Modules/ProviderManagement/Http/Resources/PublicBranchResource.php`
- `Modules/ProviderManagement/Services/BranchAssignmentService.php`
- `Modules/ProviderManagement/Services/BranchServiceAreaRepository.php`
- `Modules/ServiceManagement/Entities/PricingRule.php`
- `Modules/ServiceManagement/Http/Controllers/Api/V1/Customer/PricingController.php`
- `Modules/TransactionModule/Lib/Transaction.php`
- `app/Http/Middleware/ProtectPersonalData.php`

### User app and web: 64 ملف متغير عن baseline

- `.gitignore`
- `assets/language/ar.json`
- `assets/language/en.json`
- `lib/common/repo/data_sync_repo.dart`
- `lib/common/widgets/brand_logo.dart`
- `lib/common/widgets/custom_image.dart`
- `lib/common/widgets/not_found_screen.dart`
- `lib/common/widgets/notification_helper.dart`
- `lib/common/widgets/service_center_dialog.dart`
- `lib/common/widgets/zoom_image.dart`
- `lib/feature/address/view/add_address_screen.dart`
- `lib/feature/auth/controller/auth_controller.dart`
- `lib/feature/auth/controller/facebook_login_controller.dart`
- `lib/feature/auth/repository/auth_repo.dart`
- `lib/feature/auth/widgets/file_upload_field_widget.dart`
- `lib/feature/booking/controller/booking_details_controller.dart`
- `lib/feature/booking/controller/invoice_controller.dart`
- `lib/feature/booking/controller/service_booking_controller.dart`
- `lib/feature/booking/model/booking_details_model.dart`
- `lib/feature/booking/view/booking_details_screen.dart`
- `lib/feature/booking/view/repeat_booking_details_screen.dart`
- `lib/feature/booking/view/web_booking_details_screen.dart`
- `lib/feature/booking/widget/booking_details_section.dart`
- `lib/feature/booking/widget/booking_invoices_panel.dart`
- `lib/feature/booking/widget/booking_item_card.dart`
- `lib/feature/booking/widget/booking_workflow_panel.dart`
- `lib/feature/booking/widget/regular/booking_summery_widget.dart`
- `lib/feature/booking/widget/repeat/repeat_booking_service_log_widget.dart`
- `lib/feature/checkout/controller/checkout_controller.dart`
- `lib/feature/checkout/controller/schedule_controller.dart`
- `lib/feature/checkout/repo/checkout_repo.dart`
- `lib/feature/checkout/repo/schedule_repo.dart`
- `lib/feature/checkout/view/custom_post_checkout_screen.dart`
- `lib/feature/checkout/view/payment_screen.dart`
- `lib/feature/checkout/widget/custom_post/cart_summary.dart`
- `lib/feature/checkout/widget/order_details_section/available_repeat_time_picker.dart`
- `lib/feature/checkout/widget/order_details_section/available_slot_picker.dart`
- `lib/feature/checkout/widget/order_details_section/custom_repeat_booking_schedule.dart`
- `lib/feature/checkout/widget/order_details_section/customer_location_info.dart`
- `lib/feature/checkout/widget/order_details_section/daily_repeat_booking_schedule.dart`
- `lib/feature/checkout/widget/order_details_section/service_schedule.dart`
- `lib/feature/checkout/widget/order_details_section/weekly_repeat_booking_schedule.dart`
- `lib/feature/checkout/widget/proceed_to_checkout_button_widget.dart`
- `lib/feature/conversation/view/conversation_details_screen.dart`
- `lib/feature/create_post/controller/create_post_controller.dart`
- `lib/feature/create_post/repository/create_post_repo.dart`
- `lib/feature/language/controller/localization_controller.dart`
- `lib/feature/location/widget/pickmap_dialog_widget.dart`
- `lib/feature/my_post/view/provider_offer_details_screen.dart`
- `lib/feature/my_post/widgets/provider_bidding_notification_dialog.dart`
- `lib/feature/profile/widget/update_additional_files_widget.dart`
- `lib/feature/provider/controller/nearby_provider_controller.dart`
- `lib/feature/provider/controller/provider_booking_controller.dart`
- `lib/feature/provider/view/become_a_provider.dart`
- `lib/helper/analytics/analytics_helper.dart`
- `lib/helper/analytics/tiktok_analytics.dart`
- `lib/helper/checkout_helper.dart`
- `lib/helper/country_code_helper.dart`
- `lib/helper/file_validation_helper.dart`
- `lib/helper/map_bound_helper.dart`
- `lib/helper/phone_verification_helper.dart`
- `lib/helper/route_helper.dart`
- `lib/theme/custom_theme_colors.dart`
- `pubspec.lock`

### Provider app: 0 ملف متغير عن baseline

لا تغييرات مصدر جديدة؛ main الحالي يحوي الوظائف المطلوبة أصلاً.

### Service man app: 0 ملف متغير عن baseline

لا تغييرات مصدر جديدة؛ main الحالي يحوي الوظائف المطلوبة أصلاً.

## حزم المصدر المتطابقة

| المكون | ZIP | SHA-256 | ملفات |
|---|---|---|---|
| Admin Panel | `NIRAB-main-20261006-backend-0a4f22f79e3d-source.zip` | `69b3573c28196c979c767c0cfa0d0d0aaf64eaaa27870fc74fbe085e7633a2e1` | 3042 |
| User app and web | `NIRAB-main-20261006-customer-c2c95c17298d-source.zip` | `ee0c37d420a0795b911d049208c593812f57ade4e7b82177a54a324cd18917cc` | 967 |
| Provider app | `NIRAB-main-20261006-provider-d1fd060586d3-source.zip` | `1705eda52dc1fd346b6d4299f8152cad12a155b0dbf9d8aa415b1e441559deab` | 927 |
| Service man app | `NIRAB-main-20261006-technician-886bded47a8f-source.zip` | `2104c4e28d44d814251aee7bade879c28360a9041a98d94116c5411d81d4249f` | 489 |

الحزم أنشئت من git archive للـSHA النهائي ثم استثناء الملفات المحلية/المخرجات. قورنت جميع ملفات كل ZIP مع Git blob hashes ونجحت، وفُحصت CRC والمحتويات والملفات الإلزامية. public JS والقوالب وresources/views/vendor (تخصيصات المشروع) والخطوط والأصول المتتبعة محفوظة. استُبعدت مخرجات ios/android build المتتبعة القديمة وملفات Flutter ephemeral/Generated.xcconfig؛ القوائم الدقيقة في evidence/*-archive-files.json.

لا تحتوي الحزم .git أو vendor dependencies أو node_modules أو build/cache أو .env فعلي أو مفاتيح توقيع أو service-account secrets. لم تُضمّن أدوات أو خطوط من نظام الجهاز. هذه حزم مراجعة مصدر، وليست Deploy artifacts؛ تجهيز النشر يحتاج dependencies والإعدادات الخاصة والتوقيع والمخرجات الجديدة خارجها. مخرجات Web الجديدة موجودة محلياً في User app and web/build/web ولم تُخلط بالمصادر أو تُنشر.

## البعيد وما يبقى للفريق

لم يحدث push أو deploy. لا hooks فعالة أو ملفات CI متتبعة اكتُشفت محلياً، لكن سياسات الحماية وعمليات النشر البعيدة غير مثبتة، ولا يوجد تفويض صريح مستقل بدفع source فقط؛ لذلك REMOTE_UPDATE_PENDING للمصدر المعدل. تطبيقا المزود والفني مطابقان لـorigin/main المجلوب عند البداية. لا ادعاء بآخر حالة للشبكة بعد ذلك ولا تجاوز لحماية الفروع.

خطوات الفريق اللاحقة، لم تُنفذ هنا:

1. مراجعة التقرير والـSHAs واستكمال تبعيات/toolchains البناء المرخصة؛ معالجة الملاحظات المطلوبة بحسب سياسة الفريق، ثم Analyze/Android والتوقيع الرسمي.
2. مراجعة CI/CD وحماية main ثم نقل commits عبر المسار المعتمد دون force، وإعادة الدمج والفحوص إن تحرك remote.
3. Backup لقاعدة البيانات والمرفقات والإعدادات وAPP_KEY؛ مقارنة سجل migrations الحقيقي بكل migrations المصدر، ثم تطبيق الناقص فقط بعد مراجعة الأثر والتراجع.
4. ضبط timezone وعناوين الواجهات وCORS للصور المباشرة وpolygons والصلاحيات وأدوار القائد والاتصال الخاص دون إدخال أسرار إلى Git.
5. تجهيز Queue/Scheduler بإجراءات الفريق، ثم تحقق تشغيلي مستقل للأسعار/التزامن/التكرار/المحفظة/الإشعارات وpending_review؛ لا استنتاج لنجاحها من syntax/build.
6. بعد ذلك فقط التحقق المصرح لتكاملات الدفع/Odoo/ZATCA/SMS وغيرها والنشر المعتمد.

```text
CODE_MERGE_STATUS       = PASS — main المحلي مع تاريخ الدمج محفوظ
FUNCTIONAL_FIXES_STATUS = PASS — مراجعة مصدر وتنفيذ F01–F04/P01–P02؛ التشغيل غير مختبر
BUILD_VERIFICATION     = Backend PASS؛ Customer Web PASS؛ Analyze الافتراضي FAIL(info فقط)؛ Android BLOCKED_MISSING_TOOLCHAIN؛ Provider/Technician BLOCKED_MISSING_DEPENDENCY
REMOTE_STATUS          = REMOTE_UPDATE_PENDING للمصدر المعدل؛ لا push
RUNTIME_VALIDATION     = NOT_RUN_BY_SCOPE
PRODUCTION_DEPLOYMENT  = NOT_RUN_BY_SCOPE
```

أُنجز الدمج والإصلاح البرمجي. اعتماد البناء الكامل معلق؛ لم يُعلن Code Freeze. لم تُشغّل اختبارات أو تطبيقات أو Docker أو قاعدة بيانات أو migrations/seeders أو Artisan boot أو Queue/Scheduler أو تكاملات.
