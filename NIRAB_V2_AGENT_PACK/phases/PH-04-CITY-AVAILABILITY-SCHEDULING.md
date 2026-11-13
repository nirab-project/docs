# PH-04 — الإتاحة والموارد والجدولة

**التنفيذ:** CODE ONLY. تسبقها PH-03. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md](../specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md)

## المهام المتسلسلة
1. نفذ ResourceAvailabilityPort repository V2، وحدّث قارئات BranchCapacity/CityBookingAvailability/AvailabilityService والخدمات المختصة لتستخدم نفس الحجوزات/holds.
2. احتفظ CityResolver/geometry validation/READ COMMITTED/one-branch constraints؛ ارجع context revision واضحاً، وافصل geo readiness عن catalog draft.
3. نفذ day/slots APIs وschedule preview، intersection لموعد مشترك، sequential/parallel group planner وفق capabilities والسلامة والمدة الحقيقية.
4. نفذ reservations TTL/commit/release وقفل provider/resource موحد؛ صمم ترتيب الأقفال لكل مسار واستثناء deadlock retries بلا I/O خارجي في transaction.
5. نفذ once/selected_dates/finite recurrence وخطة pickup/delivery/inspection، clock injectable وUTC/timezone؛ لا تحويل الأيام المستقبلية إلى مواعيد مضمونة بلا lock.
6. نفذ reschedule atomic والإلغاء/expired holds/branch pause workflow، والتقويم يرى confirmed+active holds دون repeat parent.
7. اكتب اختبارات تعدد connections/حجز آخر موعد/team conflict/تغير الكمية/تغير المدينة لتنفذ على MySQL PH12.

## مخرجات إلزامية
- Scheduling/Availability/Reservation services
- city/branch adapters
- expiry jobs code
- concurrency test definitions

## اختبارات تكتب هنا وتنفذ فقط في PH-12
طلبان آخر موعد، زيادة أجهزة تغير المدة، فريق ناقص، calendar timezone، no-count duplicate، reschedule fail يحتفظ بالأصل، read-committed guard.

## بوابة اكتمال الكود
كل كاتب سعة يمر بقفل واحد؛ لا كسر city config ولا legacy fallback يفتح سعة صفر.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
