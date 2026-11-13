# PH-11 — إغلاق التكاملات وأدوات التصفير والـSeeders

**التنفيذ:** CODE ONLY. تسبقها PH-10. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S07-FINANCE-INTEGRATIONS-SECURITY.md](../specs/S07-FINANCE-INTEGRATIONS-SECURITY.md)
- [specs/S10-RESET-PRESERVATION-SEEDS.md](../specs/S10-RESET-PRESERVATION-SEEDS.md)
- [owner-only/01-VPS-CUTOVER-RUNBOOK.md](../owner-only/01-VPS-CUTOVER-RUNBOOK.md)

## المهام المتسلسلة
1. أكمل event outbox/inbox وnotification/Odoo/payment/refund/report adapters وكل observers/jobs/exports، ومنع duplicate posting ورسائل ما قبل commit.
2. نفذ inventory/plan/apply/resume/preservation-verify/seed/verify CLI بحراس default dry-run وقائمة المحميات وunknown table stop وexternal quarantine؛ **لا تشغّلها الآن**.
3. نفذ reference/local scenario seeders idempotently مع stable codes وprices drafts؛ skills remap explicit؛ local-only city fixtures لا تدخل production reference.
4. اكتب Compose/Dockerfiles/env.local.example/test helpers ولا تشغّلهم؛ local MySQL READ COMMITTED/spatial وfake external providers وlocal DB sentinel/egress guard.
5. أكمل وثائق local bootstrap/owner cutover/rollback وmanifest النهائي المبني على source inventory؛ لا autorun migration/reset في entrypoint/CI.
6. راجع كل caller في legacy route map؛ block old clients on commerce writes، وold callbacks quarantine؛ لا بقايا dual-write. حدّث API schemas والأمثلة والتطبيقات معاً.
7. راجع requirements matrix؛ اكتب CODE-READY report وlist known local validation pending. جميع tests مكتوبة وليست منفذة؛ لا تشغيل composer أو builds كحيلة لإغلاق هذه البوابة.

## مخرجات إلزامية
- Code-ready implementation in 4 repos
- safe reset commands + seeders
- local Docker/test assets (unexecuted)
- updated API/schema & owner runbook
- CODE-READY.md

## اختبارات تكتب هنا وتنفذ فقط في PH-12
reset preservation/unknown table/real payment guard/crash resume/second seed run، Odoo and notification duplicates، old API writes blocked، missing local external config fail-closed.

## بوابة اكتمال الكود
كل المتطلبات لها implementation + written tests؛ لا TODO في core flows؛ لا production changes؛ بعدها فقط PH12.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
