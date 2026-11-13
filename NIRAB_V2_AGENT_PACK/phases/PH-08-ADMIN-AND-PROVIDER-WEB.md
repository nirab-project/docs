# PH-08 — لوحات الإدارة والفرع على الويب

**التنفيذ:** CODE ONLY. تسبقها PH-07. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S08-ADMIN-CUSTOMER-APPS.md](../specs/S08-ADMIN-CUSTOMER-APPS.md)
- [specs/S09-PROVIDER-SERVICEMAN.md](../specs/S09-PROVIDER-SERVICEMAN.md)

## المهام المتسلسلة
1. نفذ Service Studio كامل Blade بform builder/forms/simulator/publish/content/media؛ ليست JSON textarea بديلاً عن واجهة الأدمن المفهومة.
2. نفذ إدارة offers/rules/policies/city activation/capabilities وpreflight warnings، وإجراءات archive/copy/version diff.
3. نفذ orders/calendar/tasks/quotes/entitlements/renewals screens وربط كل action بـapplication service وصلاحية/CSRF وIf-Match.
4. نفذ finance wallet/bank/reconciliation/refund/exception screens وOdoo monitor read/retry؛ لا زر reset/أسرار.
5. حدث لوحة مدير الفرع Laravel: scoped dashboard/calendar/quotes/assignments/cash/laundry، لا commission/subscription-for-provider marketplace features.
6. حدث landing/banner service links والتقارير/exports/invoices وsearch navigation بما يمنع legacy 500؛ حافظ الهوية ومحتوى الشركة المحمي.
7. اكتب browser e2e specs واختبارات RBAC/RTL وempty/loading/error، لكن لا تشغل browser/server حتى PH12.

## مخرجات إلزامية
- Admin/Provider Blade screens
- navigation/permissions/translations
- browser E2E source
- تقارير PH-08

## اختبارات تكتب هنا وتنفذ فقط في PH-12
إنشاء خدمة جديدة من الأدمن بلا كود، publish errors، ربط باقة صحيحة، branch scoped export، responsive pages، private receipt access.

## بوابة اكتمال الكود
لا زر معطل وهمياً في الوظائف المطلوبة؛ كل شاشة مرتبطة backend والعقود الجديدة.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
