# PH-07 — عروض الأسعار والتشغيل المتخصص

**التنفيذ:** CODE ONLY. تسبقها PH-06. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S06-QUOTES-FULFILLMENT.md](../specs/S06-QUOTES-FULFILLMENT.md)

## المهام المتسلسلة
1. نفذ quote drafts/submit/review/inspection/send revisions/accept/reject/expire، preferred date غير محجوز، inspection fee وcredit مرة واحدة.
2. نفذ انتقال approved quote إلى checkout المعتمد، financial version snapshot، وadditional-work amendment customer consent.
3. نفذ tasks/assignments/checklists/evidence للفريق/onsite/inspection/pickup/process/delivery وربط complete بتسوية entitlement.
4. نفذ نقل أثاث بعناوين أصل/وجهة وتفاصيل طوابق/تغليف/مصعد ومراجعة، وليس إنشاء deal خارجي عند الضغط.
5. نفذ laundry processing chain/counts/weight/excess approval/delivery proof، مع periods نوافذ وصول/استلام لا مدة الغسيل.
6. نفذ بيانات السيارات/أصول العميل إن لم تكتمل، وربط حقولها بسياق الحجز فقط؛ لا marketplace connector جديد.
7. نفذ quality/review/warranty follow-up ربطاً بالحجز الجديد وصلاحيات cross-role، مع audit لكل انتقال.

## مخرجات إلزامية
- Quote/Inspection/Adjustment APIs
- Task execution workflows
- specialized service adapters
- اختبارات PH-07

## اختبارات تكتب هنا وتنفذ فقط في PH-12
view لا يرسل quote، قبول إصدار منتهي، amendment رفض/قبول، laundry pickup لا يكمل booking، transfer actor scope، team evidence required.

## بوابة اكتمال الكود
كل نموذج من الخدمات التسع قابل للتمثيل والتشغيل؛ لا تكامل خارجي منافس.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
