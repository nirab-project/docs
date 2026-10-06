# UX-02 — سياق الحجز والتأكيد والدفع

اكتمل التعديل المصدري؛ لا إثبات تشغيل في هذه الجولة.

- ScheduleController.invalidateBookingContext يلغي الموعد والفرع والجدول المتكرر والسعر المعتمد عند تغيير الخدمة/الكمية/السلة/العنوان/موقع التنفيذ. إعادة تحميل السلة لا تبطل الموعد إلا إذا اختلفت عناصرها.
- AvailableSlotPicker يفصل غياب العنوان عن غياب الخدمة ويرفض نتيجة قديمة بعد تغير السياق.
- CheckOutController.invalidatePrice يمنع اعتماد ملخص سعر أعيد بعد تغير سياقه؛ placeBookingRequest محمي أثناء الإتاحة والملخص والإرسال، مع finally لإعادة واجهة التحميل.
- CheckoutRepo يحتفظ بطلب التأكيد المعتمد في الذاكرة عند فشل النقل/الخادم ويعيد نفس checkout_snapshot_id والحقول عند المحاولة اللاحقة، حتى إن أفرغ الخادم السلة. نتيجة الخادم وحدها تحسم الحجز. لا رموز دفع أو أسرار في رابط العودة.
- تحويل Quote يتطلب service_schedule صريحاً، وكذلك paymentSnapshot؛ requested_window_start لا يتحول تلقائياً إلى موعد تنفيذ.
- بقيت أقفال BranchCheckoutService وملكية النسخة ونتيجتها المتكررة والتحقق من بوابة الدفع والمحفظة والسعة وفصل حالات الدفع والفاتورة كما هي.

## العقود والمهاجرات
POST قبول العرض: service_schedule أصبح إلزامياً. العميل الموجود يرسله بعد اختيار Slot. لا migrations. باقي الحقول والأسعار من عقود checkout-price وquotation القائمة، وتفاصيلها في UX_API_CONTRACT_MAP.md.

## الفحوص
Dart 3.11.1 format/analyze: ستة ملفات، دون أخطاء أو تحذيرات؛ ملاحظتان أسلوبيتان في picker ضمن مراجعة الإغلاق. php -l نجح لـQuotationController. git diff --check نجح. لم يشغّل دفع أو Laravel أو اختبار.

## الملفات
- `Admin Panel/Modules/BidModule/Http/Controllers/APi/V1/Customer/QuotationController.php`
- `User app and web/lib/feature/cart/controller/cart_controller.dart`
- `User app and web/lib/feature/checkout/controller/checkout_controller.dart`
- `User app and web/lib/feature/checkout/controller/schedule_controller.dart`
- `User app and web/lib/feature/checkout/repo/checkout_repo.dart`
- `User app and web/lib/feature/checkout/widget/order_details_section/available_slot_picker.dart`
- `User app and web/lib/feature/location/controller/location_controller.dart`
