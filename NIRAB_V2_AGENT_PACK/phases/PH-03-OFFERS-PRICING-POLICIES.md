# PH-03 — العروض ومحرك التسعير

**التنفيذ:** CODE ONLY. تسبقها PH-02. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S03-PRICING-OFFERS-POLICIES.md](../specs/S03-PRICING-OFFERS-POLICIES.md)

## المهام المتسلسلة
1. نفذ fixed/unit/area/components/threshold/percentage modifiers/quantity bundle pricing وفق ترتيب S03 وMoney minor. inputs لا تتضمن أسعاراً موثوقة من العميل.
2. نفذ composite expansion وتخصيص السعر دون double-count، extra quantity priced and operational؛ offer_count ليس included units.
3. نفذ visit pack/membership product definitions وصلاحيتها/خصوماتها، وما يقبل وسيلة دفع نقد/محفظة/online وفق contract.
4. نفذ promotion stacking default best eligible، reference oldPrice لا discount إضافي، coupon usage reservation/consume/release idempotent كي لا تضيع حصة بفشل الدفع.
5. نفذ tax/fee config adapters من الإعدادات المحمية، rule trace وتفصيل due-now/due-later وestimated/quote_required/null vs zero.
6. نفذ pricing preview immutable hash/TTL وربط policy/schema/city/time revisions. simulate بلا آثار جانبية.
7. اكتب golden test vectors من fixtures وحالات rounding/refund allocation والمعدلات الصناعية؛ الأسعار المرجعية لا تُنشر للبيع.

## مخرجات إلزامية
- Pricing engine/DTOs/simulator
- coupon/policy adapters
- golden tests source
- تقارير PH-03

## اختبارات تكتب هنا وتنفذ فقط في PH-12
جميع pricing-golden-cases مع tampering وnegative values وoverflow وإضافات سعة وtax-inclusive/exclusive وunknown null.

## بوابة اكتمال الكود
مصدر سعر واحد لا calculators متوازية في Flutter/controllers؛ لا اعتماد على competitor prices.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
