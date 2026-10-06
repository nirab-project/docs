# UX-05 — الطلبات والعروض والمتابعة والحساب

اكتملت التعديلات المصدرية للمرحلة بتاريخ 2026-10-07، دون تشغيل التطبيقات أو خدمات الإشعارات والدفع.

- صفحة QuoteRequestDetailsScreen مستقلة تعيد تحميل الطلب المملوك من الخادم وتعرض الحالة والوصف والتفضيل الأولي والمعاينات والعروض والصلاحية والشروط ورابط الحجز الناتج. الطلب الأولي موضح بأنه ليس حجز تنفيذ.
- API الطلب يضيف customer_actions.can_accept/can_reject من الحالة والملكية وصلاحية العرض ونوع/نشاط الخدمة، وpayable_amount/currency. الرفض داخل transaction بأقفال؛ القبول والدفع يعيدان فحص الخدمة الفعالة والسعة وانتهاء العرض قبل التحويل.
- القبول يفتح سياق الموعد الصريح والملخص؛ المبالغ الأربعة (العرض، خصم المعاينة، الإجمالي، قابلية التحويل) من PostController فقط. أزيل fallback حساب Flutter من مبلغ الرابط. الروابط القديمة قابلة للفتح لكن قيمة amount مهملة، والخادم الأقدم الذي لا يرسل عقد الصلاحية لا يفعّل الدفع. نطاق التشغيل المقصود وضع الفروع؛ لا إعادة إحياء المزايدة القديمة خارج هذا الوضع.
- منع النقر المتكرر في الدفع مع إغلاق loader في finally. النقد/المحفظة يفتحان الحجز المعاد من الخادم، والنتيجة غير المؤكدة تطلب مراجعة الطلب بدل وصفها بالفشل المؤكد. Offline يظل بانتظار اعتماد الدفع. جلسة الدفع الرقمية وعقد التحقق الحاليان محفوظان.
- قوائم الطلبات تصحح bool is_booked ومعرف العنوان وتجمع الصفحات دون تكرار، وتعرض خطأ وإعادة محاولة؛ عمر ScrollController يتبع الصفحة. مدخل الطلبات في الحساب والقائمة واضح.
- إشعارات التشغيل تضيف post_id ونوع الحجز المتكرر. العميل يفتح الطلب أو الحجز المناسب؛ إشعارات العرض الأقدم تعود لقائمة الطلبات. الدخول والملكية يتحققان في API. الإشعارات العامة القديمة محفوظة.
- الحجوزات العادية وزيارات الباقة غير المتكررة تستخدم التفاصيل الحالية؛ المتكررة تستخدم مسارها الحالي. حالة التنفيذ والدفع والفاتورة تبقى منفصلة. زيارة الباقة ذات invoice_status=not_applicable لا تعرض فاتورة منتظرة. استجابة فاتورة سابقة لا تستبدل فاتورة الحجز الحالي.
- عرض الفريق وETA التقديري والطابع الزمني وقدم البيانات موجود بالمصدر وحُفظ دون مزود تتبع جديد. تطبيق الفرع والفني متوافقان مع معلومات زيارة الباقة من UX-03؛ لا تغييرات تجميلية إضافية.

## العقود والمهاجرات
لا migrations لهذه المرحلة. توسعة إضافية في GET quote-requests/{id} وGET post/details/{id}?post_bid_id=... وcommunications/notifications، مع حماية الخدمة في accept/paymentSnapshot والرفض. القديم يتجاهل حقول القراءة الإضافية؛ العميل الجديد يحتاج Backend الحديث لتفعيل قبول العرض.

## الفحوص
- PHP -l لأربعة ملفات Backend: نجاح، دون تشغيل Laravel.
- Dart format لخمسة عشر ملفاً وanalyze: لا أخطاء أو تحذيرات؛ عشر ملاحظات أسلوبية. محاولة --no-fatal-infos غير مدعومة في Dart المتاح، وأعيد الأمر بالصورة المدعومة ونجح. الفحص النهائي يغطي التعديلات الأخيرة.
- git diff --check: نجاح. JSON والترجمات تدخل الفحص النهائي الشامل.
- لم يُنفذ مرور دفع أو إشعارات أو فحص مرئي أو اختبارات آلية، التزاماً بالخطة.

## الملفات والرموز
التغييرات أعلاه تقابل QuotationController.show/accept/reject/paymentSnapshot، PostController.show، CommunicationController.notifications، QualityCommunicationObserver.saved، CheckOutController.getPostDetails/calculateTotalAmount، CustomPostCheckoutScreen._makePayment، CreatePostController.getMyPostList، RouteHelper ومسارات العرض، وأجزاء الحساب والفواتير التالية:

- `Admin Panel/Modules/BidModule/Http/Controllers/APi/V1/Customer/PostController.php`
- `Admin Panel/Modules/BidModule/Http/Controllers/APi/V1/Customer/QuotationController.php`
- `Admin Panel/Modules/SMSModule/Http/Controllers/CommunicationController.php`
- `Admin Panel/app/Observers/QualityCommunicationObserver.php`
- `User app and web/assets/language/ar.json`
- `User app and web/assets/language/en.json`
- `User app and web/lib/common/widgets/menu_drawer.dart`
- `User app and web/lib/feature/booking/widget/booking_invoices_panel.dart`
- `User app and web/lib/feature/booking/widget/booking_workflow_panel.dart`
- `User app and web/lib/feature/checkout/controller/checkout_controller.dart`
- `User app and web/lib/feature/checkout/model/post_details_model.dart`
- `User app and web/lib/feature/checkout/view/custom_post_checkout_screen.dart`
- `User app and web/lib/feature/checkout/widget/custom_post/cart_summary.dart`
- `User app and web/lib/feature/create_post/controller/create_post_controller.dart`
- `User app and web/lib/feature/create_post/model/my_post_model.dart`
- `User app and web/lib/feature/my_post/view/all_post_screen.dart`
- `User app and web/lib/feature/my_post/widgets/my_post_view.dart`
- `User app and web/lib/feature/profile/view/profile_screen.dart`
- `User app and web/lib/feature/quality/view/operational_notifications_screen.dart`
- `User app and web/lib/helper/route_helper.dart`
- `User app and web/lib/feature/my_post/view/quote_request_details_screen.dart`
