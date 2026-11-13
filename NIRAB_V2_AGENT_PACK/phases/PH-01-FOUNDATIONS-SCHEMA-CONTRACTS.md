# PH-01 — أساس V2 والمخطط والعقود

**التنفيذ:** CODE ONLY. تسبقها PH-00. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S01-DOMAIN-DATA-STATES.md](../specs/S01-DOMAIN-DATA-STATES.md)
- [specs/S11-API-CONTRACTS.md](../specs/S11-API-CONTRACTS.md)

## المهام المتسلسلة
1. أنشئ CommerceModule وسجله/routes V2/DI وnamespaces حسب source snapshot. API version guard وتفريق user/guest/staff/finance، مع إبقاء auth/config الحالية.
2. اكتب migrations additive للجداول الواردة في S01 والفهارس/قيود المبالغ والعلاقات. لا تشغلها ولا تجعلها تحذف بيانات legacy. أضف ربط V2 للبوابات/receipts والأصول الجغرافية الموجودة دون تغيير مفاتيحها.
3. أنشئ value objects Money, Quantity, TimeWindow, VerifiedCityContext, VersionedConfiguration, PrincipalScope؛ لا float authoritative. عرّف ports للموارد والدفع/المال/outbox والهوية.
4. أنشئ state machine guards وidempotency store وoptimistic revisions وoutbox/inbox/generation وerror mapper. اجعل local-only tools gated قبل أي implementation يستعملها.
5. اكتب DTOs وFormRequest/Policies وresponse serializers طبق OpenAPI؛ لا resource serialization عام يكشف حقول DB كاملة.
6. أضف capability flags V2 defaults off في production، وحد أدنى للعقد؛ لا enable أو migrate داخل boot. تأكد من امتداد config لا overwrite business settings.
7. حول specs العقود إلى ملفات code-owned schemas واكتب serializers مشتركة/fixtures للتطبيقات. عرّف decimal/UTC serialization بدقة.

## مخرجات إلزامية
- CommerceModule infrastructure
- migrations الجديدة دون تنفيذ
- OpenAPI/schema code-owned
- tests scaffolding وfactory definitions
- تقارير PH-01

## اختبارات تكتب هنا وتنفذ فقط في PH-12
اختبارات مكتوبة: FK constraints, Money serialization, state guards, idempotency hash mismatch, outbox rollback, actor isolation.

## بوابة اكتمال الكود
جميع الكيانات/ports والعقود موجودة في الكود؛ لا destructive migration ولا boot-time I/O خارجي.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
