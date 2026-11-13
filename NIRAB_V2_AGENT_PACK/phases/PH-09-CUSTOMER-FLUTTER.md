# PH-09 — تطبيق العميل وFlutter Web

**التنفيذ:** CODE ONLY. تسبقها PH-08. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S08-ADMIN-CUSTOMER-APPS.md](../specs/S08-ADMIN-CUSTOMER-APPS.md)

## المهام المتسلسلة
1. نفذ AppEnvironment local/prod guard وإزالة hardcoded production fallback من API/analytics/maps pathways؛ لا compile الآن.
2. نفذ typed V2 models/repositories واستبدل route map التجاري، مع city context preserved وcontract upgrade guard/cache invalidation.
3. نفذ service rich content/form renderer/repeater/conditions/offer/addons/assets/address ونوع quote/inspection؛ state recovery وإزالة hidden answers.
4. نفذ cart sections/multiple services/selected checkout/buy now/groups/per-group schedules وshared slot preference والbreakdown.
5. نفذ payment processing/recovery/order/bookings/cancel/reschedule، wallet/bank evidence، quotes acceptance/inspection وvisit pack scheduling/renewal/membership.
6. حدث deeplinks/push route handlers وRTL وترجمات/errors/upload progress، بدون تغيير branding/signing/Firebase IDs.
7. اكتب model/controller/widget/integration tests لكل رحلة، مع fixtures من العقد—not hardcoded backend success screens.

## مخرجات إلزامية
- Customer Flutter code and translations
- environment guards
- V2 tests/fixtures
- تقارير PH-09

## اختبارات تكتب هنا وتنفذ فقط في PH-12
9 service renderers config، 3 services cart 2 schedules، refresh draft، quote null price، payment recovery، device timezone، guest merge، stale V1 cache.

## بوابة اكتمال الكود
لا حساب سعر موثوق في Dart ولا اتصال إنتاج افتراضي محلي؛ native/web نفس عقود البيانات.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
