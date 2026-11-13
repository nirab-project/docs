# CITY-08 | تجربة العميل Flutter Android / Web

## الهدف

واجهة واحدة مفهومة: اختر المدينة -> حدد عنوانًا صالحًا -> شاهد خدمات فرع المدينة -> أضف للسلة بعد تحقق Backend -> تقويم أيام/ساعات واضح -> سعر صحيح -> حجز. لا تعرض عناصر مدينة أخرى ولا تعرض نجاح وهميًا عند فشل API.

## A. Initial City Context

- شاشة أول تشغيل/عند عدم وجود city سياق مختارة: `اختر مدينة الخدمة`، المدن من `GET /customer/cities` فقط (لا hardcoded array).
- خيار «استخدم موقعي» بعد إذن Geolocation، مع بديل يدوي بدون إذن؛ لا تطلب إذن الموقع عند أول render.
- للمستخدم العائد: اختبر أن المدينة المختارة ما زالت مؤهلة؛ مدينة صارت paused لا تظل نشطة بالـcache.
- إظهار المدينة الحالية في Header وتبديلها مع تحذير السلة والتسعير.
- تسجيل الدخول ليس شرطًا لتصفح المدينة/الخدمات، لكن الحفظ يرتبط بهوية العميل أو Guest principal كما يفرض Backend الحالي.

## B. Address Picker

- يختار العميل نقطة على الخريطة أو من Places؛ أرسل lat/lng وexpected city إلى `location/resolve`، ولا تعتمد على نص locality.
- عند `CITY_UNSUPPORTED` أو `CITY_PAUSED`: disable Save، رسالة عربية/إنجليزية، CTA «تغيير المدينة».
- عند `LOCATION_CITY_MISMATCH`: أظهر المدينة التي تحقق منها الخادم، واطلب موافقة صريحة على تبديل city context؛ لا تحفظ تلقائيًا.
- عند `LOCATION_VERIFICATION_FAILED`: خطأ اتصال + Retry، ليس `unsupported`، ولا تسمح bypass.
- اجعل address-save يستخدم server confirmation، لا تعتمد على نتيجة resolve قديمة وحدها، مع expiry/revalidation.
- عند عنوان قديم غير صالح: يظهر بالحساب لمراجعته لكن يُوسم غير قابل للحجز، ولا يستطيع جعله عنوان خدمة صالحًا دون إعادة التحقق.

## C. Home/Catalog/Details

- عند city-only: اعرض خدمات فرع المدينة مع عبارة واضحة «حدد عنوانك للمتابعة بالحجز».
- لا تخلط بيانات `selectedCityId` و`selectedAddressId` من شاشة ثانية أو Persistent Cache قديمة.
- صفحة خدمة fixed: `starting_price` فقط حتى تأكيد عناصر الخدمة وموعدها؛ خدمة quote: CTA «اطلب عرض سعر».
- حالة `service_not_ready`: لا زر إضافة، ويظهر سبب عام ومفهوم دون تفاصيل الموظفين.
- تغيير Variant/Quantity/Answers يفرض إعادة فحص أهلية الخدمة ومتطلبات الفريق والمدة قبل cart update.

## D. Cart

- `Add to Cart`: pending/loading أثناء Server check، النجاح فقط عند response success؛ أعرض أخطاء 422/409 inline قرب الخدمة.
- سلة مدينة واحدة، وإذا تبدلت المدينة لا تمسح العناصر بصمت: Dialog واضح (حفظ كمسودة إذا مدعوم أو حذف بعد موافقة) ثم إعادة فحص.
- `GET cart` يعرض Service states، لا يزيل البنود غير الصالحة دون إخبار العميل.
- لا يواصل إلى Checkout قبل تحقق كل البنود واستقرار city/address context.

## E. Inline Calendar

- داخل Checkout مباشرة، يعرض أول 7 أيام قابلة للفحص مع حالات `loading,available,unavailable,closed,error,not_checked` ونصوص ظاهرة لا لون وحده.
- بعد اختيار يوم: عرض slots الحقيقية في الشاشة نفسها؛ لا يفتح bottom sheet ثانٍ لإظهار الوقت الأساسي.
- لا يختار وقتًا تلقائيًا قبل موافقة العميل.
- الواجهة تدعم Single/Daily/Weekly/Custom ولا تسقط زيارة أو ساعة من نفسها؛ same time/different times وفق Backend verified plan.
- احفظ `starts_at` مع offset/Timezone الصحيح؛ Backend يحدد `Asia/Riyadh`، لا تولد الوقت من TZ جهاز العميل.
- إذا تأخر API ثم تغير City/Address/Cart/Mode، تجاهل response القديمة عبر `contextRevision` أو cancellation token.
- عند `409` قبل الدفع/التأكيد اعرض سببًا مفهومًا و«اختر موعدًا آخر»، ولا يبقى زر Confirm فعالًا على Snapshot منتهي.
- المبلغ النهائي يظهر بعد `pricing-preview` الناجح مع جدول زيارات مكتمل.

## F. مسارات/ملفات مرشحة للتعديل

- Home/Services/ServiceDetails Controllers & Repositories الفعلية (افحص مساراتها قبل الإنشاء).
- Address management/PlacePicker/GPS and guest session.
- Cart Controller/Repo, Checkout Controller/Repo.
- `lib/feature/checkout/widget/order_details_section/service_schedule.dart`
- `.../available_slot_picker.dart`, `.../daily_repeat_booking_schedule.dart`, `.../weekly_repeat_booking_schedule.dart`, `.../custom_repeat_booking_schedule.dart`
- `lib/feature/checkout/controller/schedule_controller.dart`, `lib/feature/checkout/repo/schedule_repo.dart`, `lib/helper/checkout_helper.dart`
- `assets/language/ar.json`, `assets/language/en.json` وما يعادلها حسب المصدر.

إعادة استعمال مكونات Flutter أفضل من ترك منتقيين فعالين مستقلين، أحدهما يتجاهل City Serviceability. قلّل تكرار network requests الناتج من rebuilds.

## G. الجودة وإمكانية الوصول

- العربية RTL والخطوط المعتمدة لنيراب، الإنجليزية LTR، مقاسات موبايل 360px وWeb 506px وديسكتوب، keyboard/focus/semantics.
- رسائل loading/error/empty قابلة لإعادة المحاولة ومفهومة، لا Toast عام لكل فشل.
- cache خاص بالمدينة والفرع والعنوان والبنود والكمية/الخيارات والتكرار والrevision، مع TTL محدود وعدم حفظ كل شيء في localStorage دون حاجة.
- عندما يختار العميل مدينة مدعومة لكن لا توجد خدمة جاهزة: صفحة Empty State محترمة لا قائمة خدمات كاذبة.

## تسليم

`CITY-07-CUSTOMER-FLUTTER-REPORT.md`: قائمة الشاشات والملفات، API integrations، حالات UI كلها، لقطات محاكاة إن أمكن، format/analyze scoped، والقيود التي تحتاج قبولًا على Web/Android الحقيقيين.
