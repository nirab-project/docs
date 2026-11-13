# CITY-14 | ثوابت الحالات الطرفية والتعارضات — إلزامي

## الثوابت (Invariants) التي يجب فرضها في الخادم

**INV-01:** `CustomerBookableCity` تتطلب City active + verified geographic definition + exactly one linked branch + branch published/intake + configured service count >=1. لا تعتمد على Flutter cache لتقريرها.

**INV-02:** علاقة 1:1 مدينة/فرع مثبتة في DB والـService Layer، مع lock عند reassignment، والبيانات السابقة لا تمس.

**INV-03:** إنشاء/تعديل العنوان يعتمد point verification، لا النص ولا `city_id` المرسل من المستخدم. أي عدم يقين Fail Closed مع مسار Retry.

**INV-04:** لا cart item يمكن مواصلة Checkout به دون city/address صحيحين وخدمة `fixed` مهيأة للفرع الحصري وvariant/quantity/answers صحيحة. `quote` له مسار منفصل.

**INV-05:** Snapshot/Coupon/Wallet/Payment لا تعتمد على فرع مختلف عن City branch وقت الموافقة؛ أي تغيير بعدها يتطلب رفضًا واضحًا وإعادة موافقة، ولا ترحل الطلب تلقائيًا.

**INV-06:** Calendar preview لا يحجز سعة؛ Confirm يعمل مع Transaction/Lock/Idempotency للحماية من السباق.

**INV-07:** `AvailableForDate` لا يعني مجرد open hours؛ بل workforce/team/shifts/leaves/duration/capacity/booking conflicts جميعًا.

**INV-08:** انشغال الفريق مؤقتًا لا يعني تعطيل الخدمة إداريًا؛ غياب الفريق المؤهل أصلًا يمنع تقديمها كقابلة للحجز.

**INV-09:** Repeat booking يجب أن يحقق جميع الزيارات لنفس فرع المدينة؛ لا dropping ولا نقل Branch بين الأيام بصمت.

**INV-10:** تعذر مصدر Geo أو Availability = error، لا equivalent to unsupported/unavailable؛ لا False Success ولا False NoSlots.

**INV-11:** City paused تمنع **عملًا جديدًا** لكنها لا تلغي Bookings قائمة أو تبطل حقوق الدفع/الفاتورة/الاسترداد.

**INV-12:** الفروع/الحجوزات التاريخية Multi-branch تظل قابلة للعرض/الإدارة دون ترحيل رجعي صامت.

**INV-13:** لا Authorization اعتماد على UI فقط؛ Backend Policies + Scope + Foreign Key + Unique + Audit.

**INV-14:** `city_id` متغير سياق؛ عند تغييره يجب إبطال Catalog/Cart eligibility/slots/checkout price/tokens وعرض أثر التغيير.

**INV-15:** Timezone السعودية ثابت في API/حساب المدة وحواف الأيام، وليس timezone جهاز المستخدم.

## حالات مشوشة يجب معالجتها بقرار واضح

### 1. مستخدم يختار الرياض لكن يضع نقطة داخل جدة

الخادم يرجع MISMATCH مع اقتراح جدة؛ لا يحفظ تحت الرياض. بعد موافقة تغيير المدينة تمسح صلاحية slot/snapshot، وتُراجع السلة صراحةً.

### 2. عنوان عند حافة الرياض

لا Radius ولا Bounding Box. اختبار هندسي للنقطة على boundary، وعند overlap يمنع الاختيار التلقائي. اعتبر بيانات الحدود إصدارًا قابلًا للتغيير، وسجّل إصدار التحقق.

### 3. مدينة منشورة أوقف المدير كل خدماتها

قائمة المدن للعميل لا تعرض المدينة باعتبارها قابلة للحجز (أو تُظهر تحذيرًا «خدماتها متوقفة» في شاشة معلومات فقط حسب القرار)، ولا تقبل عنوانًا جديدًا حتى إعادة الجاهزية؛ مع بقاء العناوين السابقة.

### 4. فرع منشور بلا فريق مطلوب لإحدى الخدمات

تبقى المدينة متاحة للخدمات الأخرى. الخدمة التي ينقص فريقها `not ready`؛ لا تجعل انتشار الفشل إلى كل مدينة بدون سبب.

### 5. مدينة بها فرع واحد، لكن أكثر من Zone legacy تتداخل

لا تختَر أول zone استعلامًا. وثّق Mapping/precedence وPricing Option على مستوى مدينة كمنطق مركزي حتى لا يتغير السعر خفية.

### 6. مدينة تصبح Paused والعميل دفع بالفعل

استمر بتنفيذ الحجز المدفوع ومسار إصلاحاته/استرداده وفق القوانين الحالية؛ لا يفرض city.active في مسار إكمال حجز تاريخي، بل في intake/creation الجديدة فقط.

### 7. إعادة ربط مدينة بفرع آخر أثناء وجود Preview

الـSnapshot يحمل branch-id/version. عند Create/Pay إذا تغير assignment: reject context changed، لا تبديل فرع مع الحفاظ على السعر القديم. الحجوزات المؤكدة سابقًا تبقى على فرعها.

### 8. تغيير الإحداثيات في Address بعد وضع الخدمات بالسلة

يفسد التحقق القديم؛ يجب إعادة Resolve قبل متابعة Checkout حتى لو بقي اسم المدينة نفسه.

### 9. تكرار طلب حجز أو رد دفع

Idempotency key/snapshot unique يمنع إصدار حجز أو خصم Wallet مرتين. حجز واحد جديد لا يبرر إزالة Backward Compatibility `bookings[]`.

### 10. تطبيق عميل قديم ما زال يرسل `provider_id` يدويًا

Under City Mode لا تسمح لمعرف مخالف بالتوجيه؛ تحقق من أنه يساوي الفرع المحلول أو اطلب Upgrade/Re-preview. لا تفتح مدخل Legacy يتيح bypass.

### 11. الحسابات والإجازات متغيرة سريعًا

المواعيد المعلنة Snapshot موقّتة؛ confirm هو الحكم. Invalidations لا تعتمد فقط على cache TTL الطويل؛ عالج تعديلات الفرق والورديات والإجازات.

### 12. إدخال اسم مدينة نصيًا مثل «الرياض الكبرى»

هذا اسم عرض/بحث فقط؛ لا تنشأ مدينة مدعومة تلقائيًا دون مرجع canonical approved.

### 13. عنوان خارج المدينة لكن داخل المحافظة أو المنطقة

لا يُقبل ما لم يقع ضمن **المدينة** المعرفة بقرار المنتج، ولا تساوِ بين `Riyadh City` و`Riyadh Region`.

### 14. أسعار ومتغيرات خدمة مشتركة بين المدن

لا تُعطل الأسعار المركزية، لكن احسب `effective pricing` لفرع المدينة والـzone mapping الصحيح؛ لا تستخدم السعر الافتراضي لخدمة بجدة عند اختيار الرياض.

### 15. تقويم يعرض يومًا available ولا يعثر على slots بعد لحظات

تغير الوقت/الموارد ممكن؛ أعد فحص اليوم وأظهر أن الإتاحة تغيرت، ولا تعرض رسالة «خطأ داخلي» أو تثبت الموعد القديم.

### 16. كتالوج متاح قبل العنوان

`city-only` يكفي لاستكشاف خدمات الفرع لكن إضافة السلة تستلزم عنوانًا verified، لأن City selection وحده ليس إثباتًا للموقع الفعلي.

## سلوك الأسلوب عند أرقام ورموز غير معروفة

- `service_capacity_per_day=0` في فرع قديم: preserve legacy semantics documented, مع تحذير إداري وإلزام فروع جديدة بقيمة صحيحة، لا تغيير صامت.
- `city_code`, `zone_id`, `provider_id` مجهولة: لا تخمّن، أرجع Validation/Error.
- خطأ الدفع: لا تحويل إلى cash بعد فشل Gateway دون موافقة؛ حافظ على حالة آمنة.
- أخطاء Places: لا تعطِ عنوانًا حقيقيًا من النص وحده؛ Coordinate verification شرط الحفظ.

## أثر التنفيذ المطلوب

لكل Invariant أنشئ traceability: `Invariant -> Service/Controller -> Unit/Contract Test -> Manual Scenario -> Report`. أي عنصر بلا اختبار أو فحص يشكل Risk مفتوحًا، لا تضعه Pass.
