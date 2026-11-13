# NIRAB-CITY-ONE-BRANCH | نص أمر نهائي لتكليف الوكيل

**انسخ النص التالي كما هو للوكيل وأرفق جميع ملفات مجلد الخطة:**

---

أنت مسؤول التنفيذ البرمجي للانتقال المعماري `NIRAB-CITY-ONE-BRANCH` على مصادر NIRAB Clean الحالية: Laravel Backend/Admin، Flutter Customer Web/Android، Provider، Serviceman. القرار النهائي من العميل: المدينة تُنشأ وتُفعل من الإدارة فقط؛ لكل مدينة فرع واحد حصري يغطي المدينة كلها؛ لا مناطق تغطية Branch ولا رسم Polygons يدوية؛ لا يجوز حفظ أو استعمال عنوان خارج مدينة مؤهلة؛ بعد اختيار المدينة/العنوان يظهر فقط كتالوج خدمات فرع المدينة المؤهلة، وتتحقق السلة والجدولة من مهارات الفنيين والفرق والورديات والقدرة الفعلية.

أقرأ حزمة الملفات بالترتيب من `00-START-HERE.md` حتى `14-EDGE-CASES-AND-INVARIANTS.md` واتبّع `13-PHASES-AND-DELIVERABLES.md` باعتباره الترتيب الملزم. ابدأ أولاً بتدقيق أحدث مستودعاتنا وتوثيق Commit/Branch/Dirty State؛ التقارير القديمة ليست إثباتًا للمصدر الحاضر. اصنع تقريرًا `CITY-01-SOURCE-AUDIT.md` مع ملف:سطر للتبعيات الحالية (Zone/Area/Branch/Cart/Checkout/Quote/Repeat/Payment)، ثم نفّذ الخطوات من CITY-02 حتى CITY-09 تدريجيًا بمراحل مراجعة واضحة.

**لا تشغّل إنتاجًا ولا قواعد بيانات أو migrations أو seeders أو بوابات دفع أو ضغط/اختبارات ثقيلة**. اكتب migrations/tools مع dry-run default فقط؛ نفّذ فحوص الصياغة وstatic/lint والاختبارات الوحدة الصغيرة المتاحة دون تجهيز بيئة ثقيلة. لا تغير أسرارًا، ولا تحذف أو تعيد كتابة حجوزات ومدفوعات تاريخية، ولا تلغِ Legacy Multi-branch بصورة تكسر Refund/Invoices. لا تغيّر MySQL Spatial أو Laravel/Flutter أو مفاهيم الشركة الواحدة. حافظ على Contracts للمحفظة/الاشتراكات والباقات/الدفع الإلكتروني والتحويل البنكي/Cash/Quote/Odoo/notifications/OTP.

أعِد استخدام `DirectServicePolicy`, `BranchReadinessService`, `BranchCapacityPolicy`, `AvailabilityService`, `BranchCheckoutService`, `DirectBookingReservationGuard`, `CheckoutBranchGroupingService` لعمليات legacy بدل إنشاء محرك أهلية مستقل يناقضها. صمم CityResolver يعتمد بيانات جغرافية معتمدة لا اسم المدينة النصي ولا مركز مدينة دائري، واستخدم Foreign Key/Unique لفرض علاقة 1:1. لا تنشر مدينة دون تعريف Geo verified وفرع مهيأ وخدمات قابلة للحجز. في نقص مصدر موثوق للحدود، أكمل التنفيذ الآمن الممكن وحدد `BLOCKED FOR LIVE ACTIVATION` دون قبول عناوين بمعلومات مضللة.

المخرجات المطلوبة بعد كل مرحلة:

1. تقرير `CITY-XX-...-REPORT.md` يشمل السبب، الدليل، الملفات والمهاجرات وRoutes، مخاطر التوافق، الفحوص المنفذة/غير المنفذة.
2. شيفرة المشروع المعدلة في مستودعاتها الصحيحة، لا نسخ معزولة.
3. عقد API نهائي وMigration/Backfill dry-run/feature flags، مع حراسة أمنية ومحاسبية.
4. Matrix قبول P0/P1 من `11-QA-ACCEPTANCE.md`، ونتائج كل حالة بـPASS/FAIL/NOT RUN/BLOCKED دون ادعاء اختبارات لم تنفذ.
5. Runbook نشر وتراجع Owner Controlled وفق `12-RELEASE-ROLLBACK.md`.
6. تقرير ختامي يلخّص ما اكتمل بالمصدر وما يتطلب فحصًا يدويًا/موافقة قبل الإنتاج؛ لا تطلق «100% Production Ready» دون قبول فعلي.

أنهِ العمل بتقرير رئيسي `NIRAB_CITY_ONE_BRANCH_DELIVERY.md` يذكر Commit IDs ومصفوفة الملفات والتبعيات والميزات والمخاطر والنواقص، ويتضمن أوامر الفحص الخفيف وإجراءات Owner بعد التسليم.

---

## شكل التسليم النهائي المتوقع

```text
reports/
  CITY-01-SOURCE-AUDIT.md
  CITY-02-DATA-REPORT.md
  CITY-03-GEO-REPORT.md
  CITY-04-ADMIN-REPORT.md
  CITY-05-CATALOG-CART-REPORT.md
  CITY-06-BOOKING-REPORT.md
  CITY-07-CUSTOMER-FLUTTER-REPORT.md
  CITY-08-OPERATIONS-REPORT.md
  CITY-09-RELEASE-READINESS-REPORT.md
  CITY-ACCEPTANCE-RESULTS.md
  NIRAB_CITY_ONE_BRANCH_DELIVERY.md
```

**شرط الإغلاق:** عمليات المدن والعناوين والخدمات والسلة والتقويم والحجز ليست مجرد شاشات جميلة؛ كل واحدة تتبع مصدر قرار واحد، بلا مسار API يتخطى القيود، ولا خسارة تاريخ مالي أو تشغيلي. أبلغ عن أي Blocker حقيقي قبل تفعيل City Mode.
