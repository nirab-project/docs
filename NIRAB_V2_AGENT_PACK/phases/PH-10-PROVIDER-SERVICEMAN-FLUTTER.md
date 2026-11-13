# PH-10 — تطبيق مدير الفرع وتطبيق الفني

**التنفيذ:** CODE ONLY. تسبقها PH-09. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S09-PROVIDER-SERVICEMAN.md](../specs/S09-PROVIDER-SERVICEMAN.md)

## المهام المتسلسلة
1. نفذ environment guard في كلا التطبيقين بمسار util/utils الصحيح، مع typed contract DTOs وmin-version/deeplink conversion.
2. Provider: dashboard/booking detail/calendar/assignments/quotes/amendments/laundry/cash/reports، وفصل order reference عن booking/task.
3. Provider: احذف واجهات commission/marketplace provider sale غير المطبقة، واحتفظ user profile/technician management/settings التشغيلية والصلاحيات.
4. Serviceman: tasks and allowed_actions، nested answer summaries، checklists/evidence، arrival/start/complete، pickup/delivery/inspection، cash due وsync queue.
5. نفذ pending sync/idempotency وconflict resolution والهوية عند تغيير الحساب؛ لا يمنح offline status حقيقة نهائية.
6. احفظ Android package identifiers/signing configs وفصل local notifications من FCM الحقيقي؛ صحح identity/theme/RTL لكل new screen.
7. اكتب tests للنماذج/repositories/widgets وcross-role وهمية، وnative smoke test scripts للمرحلة النهائية فقط.

## مخرجات إلزامية
- Provider Flutter V2
- Serviceman Flutter V2
- tests and native smoke instructions
- تقارير PH-10

## اختبارات تكتب هنا وتنفذ فقط في PH-12
فرع لا يرى آخر، task مغلقة لا تعاد، cash retry، inspection no price edit، laundry handoff، team checklist، session expiry with pending upload.

## بوابة اكتمال الكود
التطبيقات تعرض كل إجابات/مرفقات الطلب اللازمة ولا تتصل بعقود legacy تجارية بعد cutover.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
