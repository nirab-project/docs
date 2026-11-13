# S03 — العروض والأسعار والسياسات
## محاور مستقلة لا enum عملاق
- `offer_kind`: single، composite، quantity_bundle، visit_pack، membership.
- `price_mode`: fixed، rules، quote_required، inspection_required؛ `estimated` حالة الناتج غير ملزم لا نوع معادلة.
- `fulfillment`: onsite، inspection، pickup_processing_delivery.
- `recurrence`: none، selected_dates، recurrence_plan؛ المنتج visit_pack يملك entitlement ولا يكرر ثمن الشراء.
الأدمن يختار التركيبات المسموحة في validator. خدمة ليست تسع خدمات ثابتة hard-coded.

## حساب موحد
`EvaluateOffer(input, serviceVersion, offerVersion, cityContext, timeContext?)` ينتج: normalized_answers، pricing_state، breakdown_minor، rule_trace، requirements، policies، configuration_hash، missing_fields. مصدر السعر الخادم. Flutter يعرض النتائج ولا ينفذ حساباً مالياً موازياً. `RequirementsEvaluator` يحسب المدة/المهارات/حجم الفريق/الموارد بشكل مستقل عن `MoneyCalculator` ويجمعهما orchestrator.

Money scale=2 لـSAR. الحسابات العددية/الضريبية decimal/rational داخلياً ثم half-up عند line boundary مرة واحدة. توازن الإجمالي: subtotal − discounts + taxable/non-taxable fees + tax = total. قيم quantity والarea تقبل decimal string؛ لا binary floats؛ `max_total_minor` حارس overflow.

## ترتيب تطبيق القواعد
1. حدد price list للمدينة (override صريح) وإصدار العرض.
2. سعر الأساس أو مجموع مكونات/كميات مثبتة + إضافات مدفوعة + تعديلات الإجابات + time surcharge المصرح به.
3. minimum charge بالصيغة المعلنة قبل الخصم؛ حدود max التي تعني مراجعة تحول إلى estimated/quote_required، لا تقطع السعر اعتباطياً.
4. اختر promotion واحدة مؤهلة افتراضياً: أفضل خصم من campaign/membership/coupon غير قابلة للجمع. يسمح stacking فقط بترتيب محدد في policy؛ reference oldPrice ليس خصماً جديداً.
5. طبق cap والحدود والاستثناءات، ثم رسوم الزيارة مرة لكل fulfillment group وفق policy معلنة، لا مرة لكل row تقنياً.
6. ضريبة وفق tax policy **المحفوظة والمعتمدة** شاملة/غير شاملة. هذه الحزمة لا تقرر التزامات ضريبية أو معدل إنتاج. الأرقام في fixtures صناعية للاختبار.
7. خصص discounts/tax/payment/refund على البنود بأكبر البواقي، والtie-break UUID/code ثابت؛ مجموع التخصيص يساوي الأصل بالهللة.
8. احفظ snapshot/hash وTTL في preview. لا تعتبر preview حجز سعة.

إن كان المبلغ مجهولاً: `total_minor=null` و`pricing_state=quote_required`، ورسالة «سيُحدد بعد المراجعة». لا تحويل null إلى 0. خدمة مجانية: total=0 فقط إذا explicit_free=true وreason وapproval مع zero_charge checkout، دون طلب gateway بمبلغ صفر.

## قواعد مسموحة
unit_rate×quantity، area_rate×m²، per_component_sum، fixed_extra، percentage_extra على base مسمى، band table، threshold/minimum، included_quantity+extra_units، conditional modifier. كل rule يحمل code/priority/basis/scope/currency، ولا يستخرج السعر من description. النسب لا تطبق على subtotal يتضمن نفس rule فتكرر نفسها. كميات موظفين/أوقات منفصلة عن المال.

## أنواع الباقات
**Composite:** مكونات فعلية offer_version أو service component مثبتة، quantities/units/included_scope. مجموع allocation المكونات=ثمن الباقة، لا مجموع أسعار قائمة الخدمة بالضرورة. العرض المعلن لا يقول سجادة ثم يسقطها من التنفيذ. تخزين price_allocation_strategy وrounding ثابت. منع cycle وتجاوز عمق المكونات=3.

**Quantity bundle:** included_quantity حقل عدد حقيقي، base_minor، extra_unit_minor أو extras_disabled. اختيار 5+2 ينتج 7 وحدات تنفيذ لا quantity=1 معنوية. addon يغير المدة والفريق أيضاً. تغيير الباقة لا يبقي extras خارج حدودها. كتابة «يمكن إضافة» تتطلب وجود capability مفعلة.

**Visit pack:** total_visits وvalidity_days وmax_units_per_visit وentitled_offer_versions وسعة/خيارات included. ordersCount ليس عدد الأكياس. المجموع عند الشراء واحد؛ حجز زيارة مستحقة يستهلك credit، ولا يسعر ثمن كامل مرة أخرى. extra واحد في زيارة محددة يباع كبند إضافي منفصل لا يعدّل الرصيد بسالب.

**Membership:** امتيازات وخصومات منفصلة؛ renewal consent؛ لا grant خدمات تلقائي إذا لا تنص المزايا. لا تطبق خصم شراء العضوية على ذاتها بصورة دائرية أو على quote approved إلا policy صريحة.

## سياسات تجارية يجب تنفيذها إدارياً
الإلغاء وإعادة الجدولة/no-show/الإلغاء بعد البدء، صلاحية quote، حد الحجز، الحد الأدنى والأعلى للمساحة، حد تغيير العنوان، رسوم المعاينة وخصمها من التنفيذ مرة واحدة، إلغاء جزء من bundle، استرجاع unused visits، حدود الزيادة بالوزن، منع جمع رسوم متكررة. defaults المحلية ليست قرار إنتاج. Publish يمنع غياب سياسة لازمة لا يستنتجها من اسم الخدمة.

باقة مركبة core V2: إلغاء جزء منها لا يتم بصمت؛ قبل البدء إما إلغاء الباقة كلها أو amendment يعيد تسعير النطاق ويطلب قبول العميل. لا تعوّض سعر القائمة كاسترداد لبند حصل على جزء أصغر من ثمن باقة.

## السعر والموعد
الأسعار التي تعتمد على الفترة تظهر estimated قبل اختيار الوقت. عند اختيار الموعد يحسب final preview. إذا تغيرت الخدمة أو الوقت أو العنوان أو الكوبون أو الإصدار/السعة، يجب تجديد preview وقبول العميل لأي زيادة؛ لا رفع مبلغ gateway بعد موافقته. الزيادات بعد بداية العمل هي amendment مقبول، لا edit على order paid.

راجع حالات الأرقام الدقيقة في `fixtures/pricing-golden-cases.json` واكتب اختبارات لها قبل تشغيلها في PH-12. يمكن تغيير أسلوب التنفيذ الداخلي، لا تغيير النتائج المتوقعة لتلائم خطأ الحساب.
