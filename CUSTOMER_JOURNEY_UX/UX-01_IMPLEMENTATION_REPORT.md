# UX-01 — مداخل الخدمة وطلب العرض

اكتمل التعديل المصدري. المرجع هو main المسجل في UX_BASELINE.json.

- ServiceActionHelper.open يوحد طلب الخدمة في التفاصيل والبطاقات/البحث والمفضلة. fixed يحتفظ بالسلة وquote يفتح طلب العرض.
- رابط الطلب يحمل slug وaction فقط؛ CreatePostScreen يعيد تحميل الخدمة وحقولها من API بعد الدخول، مع تحميل/فشل/إعادة محاولة.
- getSignInRoute يحفظ query بالكامل ويعيد checkout إلى سياقه. عودة تسجيل الدخول الاجتماعي تحفظ المعاملات. سياسة Guest Checkout لم تتغير.
- وقت طلب العرض اختياري ومحلي لنموذج الطلب؛ لا ServiceSchedule ولا CartController لتأكيده. requested_window_start يبقى تفضيلاً.
- BranchAssignmentService.intakeOnly مستخدم حصراً عند إنشاء Quote: يحافظ على الفرع والمنطقة وأهلية الخدمة ويؤجل سعة التنفيذ إلى التحويل.
- إخفاء السعر العددي للخدمات quote عند مداخل الشراء، وحفظ التعليمات الإضافية ضمن العلاقة الموجودة. حماية quote في Cart لم تتغير.

## العقود والمهاجرات
GET تفاصيل الخدمة الموجود؛ POST quote-requests يقبل additional_instructions الاختياري وrequested_window_start الاختياري الموجود. لا migrations.

## الفحوص
Dart 3.11.1: format وanalyze لـ11 ملفاً معدلاً؛ لا أخطاء ولا تحذيرات، 5 ملاحظات أسلوبية ظهرت في الفحص الأول وتراجع ضمن الإغلاق. php -l نجح لملفي PHP المعدلين. JSON ar/en قابل للتحليل. لا تشغيل بيئة أو اختبار وظيفي.

## الملفات
- `Admin Panel/Modules/BidModule/Http/Controllers/APi/V1/Customer/QuotationController.php`
- `Admin Panel/Modules/ProviderManagement/Services/BranchAssignmentService.php`
- `User app and web/assets/language/ar.json`
- `User app and web/assets/language/en.json`
- `User app and web/lib/common/widgets/service_center_dialog.dart`
- `User app and web/lib/common/widgets/service_widget_vertical.dart`
- `User app and web/lib/feature/auth/widgets/social_login_button.dart`
- `User app and web/lib/feature/create_post/controller/create_post_controller.dart`
- `User app and web/lib/feature/create_post/model/create_post_body.dart`
- `User app and web/lib/feature/create_post/view/create_post_screen.dart`
- `User app and web/lib/feature/create_post/widget/subcategory_service_view.dart`
- `User app and web/lib/feature/favorite/widget/favorite_service_item_view.dart`
- `User app and web/lib/feature/service/widget/service_info_card.dart`
- `User app and web/lib/helper/route_helper.dart`
- `User app and web/lib/helper/service_action_helper.dart`

## حدود المرجع
الملف المتاح في Downloads باسم NIRAB_LATEST_SOURCE_REVIEW_2026-10-06.md يتناول N01–N03 ولا يتضمن تعريفات F01–F07 المطابقة للخطة. لذلك تستخدم خريطة الإغلاق تعريفات F من الخطة نفسها والمصدر الفعلي، ولا تنسب أرقام أدلة ذلك التقرير إلى هذه المراجعة.
