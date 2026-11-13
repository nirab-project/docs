# PH-00 — تثبيت اللقطة وخريطة التبعيات

**التنفيذ:** CODE ONLY. بداية الحزمة. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [02-SOURCE-AUDIT.md](../02-SOURCE-AUDIT.md)
- [03-DECISIONS-AND-SCOPE.md](../03-DECISIONS-AND-SCOPE.md)

## المهام المتسلسلة
1. حدد جذور المشاريع الأربعة الفعلية مقارنة بأسماء الأرشيفات. لا تفترض أن مجلد «Service man app» يحمل اسم الأرشيف. احفظ git status وcommit IDs وsource diff دون تغيير عمل المستخدم غير المتعلق.
2. اكتب SOURCE-DELTA.md وDEPENDENCY-MAP.md: مصادقة/إعدادات/جغرافيا/الفروع/المال/الحجوزات/التقارير/تطبيقات. تحقق من الأدلة E01..E25 ومن الأسماء الفعلية للجداول والحقول.
3. اجمع كل استخدام legacy services/carts/bookings/booking_repeats: controllers, Blade, Flutter, observers, payment hooks, cron, notifications, Odoo. عيّن قرار KEEP_CONFIG أو ADAPT_V2 أو BLOCK_V1 أو REMOVE لكل استخدام.
4. حلل protected references closure للمستخدمين/FKs/ملفات التخزين من migrations/models دون تشغيل DB. أنشئ قائمة unknown تحتاج inventory محلي لاحقاً.
5. ثبت فرع عمل محلي غير ناشر؛ لا push/main/merge. اقرأ إعدادات CI الموجودة نصياً واكتب تنبيه Dokploy auto-deploy. لا تشغل scripts.
6. تأكد من قيد المدينة/الفرع وقرار checkout مدينة واحدة. لا تعيد طرح عمولات أو polygons فروع. وثق ADRs D01..D20.
7. سجل مخاطر ملفات المنصات والassets/اعتمادات lock المطلوبة للاختبار النهائي. جهز تنفيذ strategy للمحرك الجديد وحدود adapters.

## مخرجات إلزامية
- SOURCE-DELTA.md
- DEPENDENCY-MAP.md
- LEGACY-COMMERCE-ROUTE-MAP.md (inventory)
- PRESERVATION-INVENTORY-DRAFT.json
- تقارير PH-00

## اختبارات تكتب هنا وتنفذ فقط في PH-12
اكتب قائمة فحوص تناسق المخطط والعقود التي ستنفذ في PH12، ولا تنشئ DB الآن.

## بوابة اكتمال الكود
لا ملفات مفقودة مسكوت عنها؛ جميع مسارات المال/المدينة مع مالك واضح؛ التغييرات محلية فقط.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
