# CITY-06 | أهلية الخدمات والكتالوج والسلة والـQuote

## المشكلة المطلوب التخلص منها

لم يعد مقبولًا أن يرى العميل خدمة في الصفحة الرئيسية بصيغة قابلة للحجز ثم يضيفها للسلة، رغم أن فرع مدينته لا يملك فنيًا/فريقًا مؤهلًا أو لا يملك إذن تقديمها. لا ننقل رفض الحجز إلى آخر خطوة كلما أمكن منعه مبكرًا.

## عقد `CityServiceabilityFacade` (اسم مقترح)

وحّد استخدام المحركات الموجودة، ولا تكرر قواعدها:

- `DirectServicePolicy`: published, authorized admin, operational active, pricing mode.
- `BranchReadinessService`: city branch approved/intake, hours/capacity, setup blockers.
- `ServiceItemRequirements`: variant, quantity, field_answers, execution_type, required_technicians, duration.
- `AvailabilityService` + `BranchCapacityPolicy`: actual candidate workforce, shift, leaves, booked workloads when there is وقت.
- `PricingEngine`/`BranchCheckoutService`: السعر المبدئي أو سعر المعاينة المعتمد وVAT/coupon/booking charge.

عقد داخلي Typed Decision DTO:

```text
ServiceabilityDecision {
 city_id, branch_id, service_id,
 level: city|catalog|cart|slots|commit,
 status: READY|NOT_READY|NO_SLOTS|REQUIRES_QUOTE|UNVERIFIED|ERROR,
 reason_codes[],
 can_display, can_start_request, can_add_to_cart,
 missing_configuration[],
 validity_scope: CONFIG_ONLY|CONTEXT|DATE_TIME|FINAL,
 evaluated_at, revision
}
```

لا تُرسل `missing_configuration` الحساسة للعملاء؛ استخدمها في الإدارة فقط.

## كتالوج الصفحة الرئيسية بعد اختيار المدينة

- `GET /customer/cities/{id}/services` يرجع فقط الخدمات المسموح/الصالحة للفرع الحصري وفق policy، ويفرق `fixed` و`quote`؛ الخدمات غير الجاهزة لا تُقدم كقابلة للحجز.
- يعيد response معلومات العرض الجاهزة (`home_icon` و`translations` و`starting_price` إن جاز) دون تعداد الفنيين أو الفرق.
- خدمة لها مؤهلات وفريق جاهز مبدئيًا لكن لا Slots اليوم: تبقى مع CTA «اختر موعدًا» لا «غير متاحة في مدينتك».
- الخدمة التي تحتاج فريقًا غير مكوّن/بلا مهارات: لا تسمح بالبدء، يمكن إبقاؤها في تبويب «قريبًا» إذا اختار صاحب المنتج ذلك صراحة؛ الافتراضي إخفاؤها من قائمة الحجز الفعلية.
- احترم تفعيل الخدمة المركزي (`admin_enabled`) و`is_active`، ولا تُفعّل خدمات أُوقفت يدويًا.
- لا تستخدم cache عامًا للخدمات دون `city_id`, `branch_id`, eligibility/config revision; اختر TTL قصيرًا للـCatalog وأبطل cache عند تعديلات الفرع والموارد الضرورية.

## مسار طلب عرض السعر `quote`

- لا تضف خدمة quotation إلى Cart الخاص بخدمات fixed إن كان العقد الحالي يمنع ذلك.
- يسمح طلب عرض سعر فقط إذا العنوان داخل مدينة active وفرعها يدعم الخدمة وطلب العرض مفتوح.
- لا تعد بأن فريقًا موجود في موعد محدد قبل اكتمال المعاينة والموافقة؛ يمكن أن يكون أهلية طلب عرض سعر منفصلة عن جاهزية موعد التنفيذ.
- بعد قبول العرض والموعد، أعد التحقق من المدينة والفرع والإتاحة داخل مسار الحجز الحالي؛ حافظ على quote constraints وأي `requested_window`/48h policy موجودة.

## منع الإضافة غير المؤهلة للسلة

- Require server-verified address/city context قبل `POST /cart` لأول مرة و`PUT /cart/quantity`/`cart/provider-change` وما يعادلها.
- `branch_id` يُستنتج من city، ولا يقبل Customer تغييرَه منفردًا أو تخطي resolver بإرسال `provider_id`.
- تحقق من `fixed`, active, permissions, valid variant/options, quantity, configured workforce/team, current city/branch and service-pricing zone.
- في حال readiness ناقصة أعد `422 SERVICE_NOT_READY` مع reason code آمن بدل إضافة العنصر ثم سحبه لاحقًا؛ لا تكشف أسماء الفنيين.
- عدم وجود Slot اليوم وحده **لا يمنع إضافة الخدمة**؛ يوضح موعد لاحق في التقويم.
- سلة مختلطة مدينتين ممنوعة: `409 CART_CITY_CONFLICT` مع CTA نقل السلة بعد موافقة صريحة، ولا تنقل عناصر/الخصومات بصمت.
- إعادة فحص خفيف للسلة عند `GET cart` مع status per item: ready, needs_review, unsupported; لا تحذف عنصرًا قديمًا بصمت.
- إذا فقدت الخدمة صلاحيتها بعد إضافتها للسلة، امنع Checkout وقدم علاجًا (احذف العنصر/غير الخيارات/اختر عنوانًا آخر) مع الحفاظ على محتويات السلة.

## تسعير السلة

- افصل `starting_price` عن `final_total`؛ لا تعرض `0` أو Total على أنه نهائي في غياب الجدولة عندما يتطلب السعر موعدًا.
- إعادة احتساب السعر عندما يتغير `city/address/items/quantity/variant/answers/repeat-days/time` أو سعر الفرع.
- حصر `zone_id` الذي يستخدمه PricingEngine بسياق city المحلول server-side من mapping، حتى لو أرسل Flutter zone أخرى.
- الحفاظ على قسائم وعضويات ومحفظة وVAT والرسوم والباقات والزيارات حسب عقد Backend.

## أمثلة سلوك

| الحالة | الكتالوج | السلة |
|---|---|---|
| جدة active وفرعها مؤهل للخدمة | تظهر | تُضاف بعد تحقق العنوان |
| الرياض مختارة بلا عنوان دقيق | تظهر للاستكشاف | يطلب عنوانًا صالحًا قبل الإضافة |
| مدينة غير مفعلة | لا تظهر كمدعومة | 422 من الخادم |
| فريق الخدمة غير موجود | غير قابلة للحجز | 422 SERVICE_NOT_READY |
| فريق متاح بالأصل لكن كل Slots اليوم ممتلئة | تظهر | يمكن الإضافة؛ يختار موعدًا آخر |
| خدمة تتطلب عرض سعر | تظهر كطلب عرض | لا تمر كـfixed cart |
| سعر متغير يتطلب schedule | سعر ابتدائي واضح | لا Total نهائي حتى اكتمال الموعد |

## تبعيات backend المطلوب مراجعتها

`CartController`، CartService/DTO، `DirectServicePolicy`، `BranchServiceAvailabilityService`، ServicePriceResolver، PricingEngine، checkout-price/preview، Customer Catalog APIs، caches، Campaign/Offer/Pricing Zones. لا تستبدل وظائف coupon/payment أو تعتمد على `provider_id` المخزن بالسلة إذا اختلف عن فرع المدينة عند إعادة التحقق.

## تسليم

`CITY-05-CATALOG-CART-REPORT.md`: إثبات ملف:سطر لأي إصلاح، حالات قبل/بعد، API contracts، توافق Quote، ونتائج فحوص المصدر.
