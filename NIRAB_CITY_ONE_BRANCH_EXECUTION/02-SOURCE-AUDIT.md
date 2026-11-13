# CITY-02 | تدقيق المصادر أولًا (Gate A)

**حالة المرحلة:** إلزامية قبل أي migration أو تغيير API. التقارير السابقة لا تكفي لإثبات الكود المنشور حاليًا.

## افتح مستودعات NIRAB الفعلية

- Backend / Admin Panel (Laravel)، User app and web (Flutter)، Provider app (Flutter)، Service man app (Flutter).
- افحص `git status -sb`, الفرع و`HEAD`, حالة التزامن مع `origin/main` إن أمكن دون تعديل شجرة العمل؛ لا تفترض أن النسخة القديمة في zip هي الأحدث.
- أبلغ عن تغييرات محلية غير مدفوعة وحافظ عليها؛ لا `git reset --hard` ولا `git clean -fd` ولا squash أو force push دون طلب صريح.
- سجل شجرة Routes الفعلية، إصدارات Flutter/Laravel، حالة feature flags، migrations الموجودة، أية تغييرات حساسة سبق تنفيذها.

## ملفات مرشحة للفحص (أسماء معروفة تاريخيًا؛ ثبّت المسار الموجود)

| المجال | مرشح مراجعة |
|---|---|
| الخدمة | `Modules/ServiceManagement/Services/DirectServicePolicy.php`, `BranchServiceAvailabilityService.php`, `ServiceItemRequirements`, `ServicePriceResolver` |
| الفرع | `Modules/ProviderManagement/Services/BranchReadinessService.php`, `BranchCapacityPolicy.php`, `BranchIntakePolicy.php`, `BranchOnboardingService.php`, `BranchZoneChangeService.php` |
| الإتاحة | `Modules/BookingModule/Services/AvailabilityService.php`, `DispatchEngine.php`, `DirectBookingReservationGuard.php` |
| السلة والحجز | `Modules/CartModule/Http/Controllers/Api/V1/Customer/CartController.php`, `Modules/BookingModule/Services/BranchCheckoutService.php`, `CheckoutBranchGroupingService.php` |
| Customer APIs | `Modules/BookingModule/Http/Controllers/Api/V1/Customer/DispatchController.php`, `BookingController.php`; ومتحكمات خدمة/منطقة وعناوين العملاء الفعلية |
| الإدارة | `app/Http/Controllers/BranchOnboardingController.php`, `Modules/ProviderManagement/Http/Controllers/Web/Admin/ProviderController.php`, Views إدارة الفرع |
| Flutter | `lib/feature/checkout/.../service_schedule.dart`, `available_slot_picker.dart`, `daily_repeat_booking_schedule.dart`, `weekly_repeat_booking_schedule.dart`, `custom_repeat_booking_schedule.dart`, `lib/helper/checkout_helper.dart`, Customer home/services/cart/address controllers |
| التوافق المالي | `Modules/PaymentModule/...`, `Modules/CustomerSubscriptionModule/...`, Odoo AccountingEvents, notification callbacks |

ملحوظة: وجود أسماء الملفات في تقرير قديم لا يثبت وجودها بالحالة نفسها الآن؛ ابحث عن النظير الفعلي بدل إنشاء ملفات مكررة.

## أسئلة تدقيق يجب إجابتها مع أدلة ملف:سطر

### جغرافيا وبيانات

1. هل `zones` الحالي يمثّل مدنًا بالفعل أم مناطق تشغيل أوسع؟ وهل توجد Zone واحدة للرياض وأخرى لجدة؟
2. هل `zone_id` مستخدم في pricing/coupon/cart/quote/address/booking/payment/favorites/Provider APIs؟ أحصِ كل موضع.
3. هل هناك بيانات boundaries معتمدة للمدن يمكن إعادة استعمالها؟ وما مصدرها ونظام إحداثياتها؟
4. ما هي كل الطرق التي تحفظ/تعدل عناوين العميل، بما فيها guest/registered/legacy/admin/checkout؟
5. أين تُخزن الخدمة في موقع العميل `service_address`, و`zone_id`, `provider_id`, `branch_service_area_id`؟
6. هل توجد جداول متعددة `cities`, `areas`, `zones` ذات معان متداخلة؟ ما هو مصدر الحقيقة المطلوب؟

### الأعمال والتشغيل

7. هل `DirectServicePolicy::catalog` يفحص النشر فقط أو فرع المدينة الفعلي؟
8. هل `CartController` يسمح بإضافة الخدمة بلا `provider_id` ثم يفحص وقت Checkout فقط؟
9. أين يُحسب `required_technicians`, `execution_type`, team membership, shifts, leaves, duration, travel buffer؟
10. هل الجدولة الفردية والمتكررة تستخدمان نفس planner؟ كيف يتعامل `available_slot_picker` مع اختلاف provider بين الأيام؟
11. هل `BranchCheckoutService` قد يولد عدة bookings للفروع؟ ما الحارس الذي يعيد فحص السعة لحظة التأكيد؟
12. هل الإدارة الحالية تنشئ `branch_service_permissions` تلقائيًا عند إنشاء الخدمة؟ وهل migration 2026-11-08 الخاصة بالتوريث نُفذت؟ هل عامل `branch-services` يعمل في الإنتاج؟ **لا تستعلم عن الإنتاج أو تغيره دون إذن**؛ أبلغ عن المتطلبات.

### Customer / Provider / Serviceman

13. من أين تأتي قائمة خدمات الرئيسية وهل يملك Flutter Address/City Context موحدًا؟
14. كيف تُخزن city/zone المختارة في Flutter؛ ما إبطال cache عند تغيير العنوان أو الكمية أو نوع الزيارة؟
15. ما مسارات Provider المسموح فيها تغيير الفرع وHours/Shifts/Teams؟
16. كيف يتعامل الفني مع موعد من فرع قديم أو طلب مدفوع بعد إيقاف المدينة؟

## مخرجات Gate A

أنشئ `reports/CITY-01-SOURCE-AUDIT.md` ويجب أن يحتوي على:

- SHA/branch/dirty state لكل مستودع، قائمة الأوامر المنفذة فقط.
- جدول `Current path -> Current behavior -> Desired behavior -> Risk -> Proposed change` مدعوم بأماكن المصدر.
- مصفوفة `zones/provider/area` (القيود والفهارس والتبعيات)، وقرار **كيف نحافظ على Zone legacy**.
- قائمة نقاط الدخول الخاصة بحفظ العنوان وإضافة السلة وCheckout والتكرار والـQuote.
- تحليل اصطدام المهاجرات ووجود جدول مدن سابق؛ لا تنشئ جدولًا ثانياً إن وُجد ما يكافئه.
- قرار حول مرجع حدود المدن؛ إن كان غير متاح، صنّف ذلك كـ **حاجز نشر**، لا تعتمد تطابق الاسم النصي مكانه.
- خطة تغيير مرحليّة لا تمس تاريخ المال أو الحجوزات.

**معيار الخروج:** لا يبدأ تنفيذ CITY-02 قبل إثبات فهم مواضع حفظ/إدارة العنوان، ومرجع zone، وعقود cart/checkout، وكيفية تعريف حدود المدينة بأمان.
