# PH-06 — المال والمحفظة والاستحقاقات

**التنفيذ:** CODE ONLY. تسبقها PH-05. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S03-PRICING-OFFERS-POLICIES.md](../specs/S03-PRICING-OFFERS-POLICIES.md)
- [specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md](../specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md)
- [specs/S07-FINANCE-INTEGRATIONS-SECURITY.md](../specs/S07-FINANCE-INTEGRATIONS-SECURITY.md)

## المهام المتسلسلة
1. نفذ ledger/accounts/allocations/wallet holds واستهلاكها/ردها، مع payment_requests/payment_refunds مصدر بوابة واحد؛ adapters دقيقة بين minor وdecimal legacy.
2. نفذ bank receipts private/review واعتماد مالي مخول/cash collections/remittance/refunds جزئية ومنع تجاوز captured أو تكرار الحركة.
3. نفذ grant/reserve/consume/release/expire entitlement linked paid order؛ صلاحية عند وقت الخدمة، extra paid line لكل زيارة إن وجدت.
4. نفذ renewal agreements consent/cycle key/payment verification/retry/grant مرة واحدة، مع manual renewal وcapability disabled عند مزود لا يدعم التفويض.
5. نفذ membership benefits independent عن visit_pack، وإعادة تقييم eligibility عند expiry دون تغيير سعر order confirmed.
6. حدّث top-up/wallet/شراء اشتراكات القديمة إلى Finance service الجديد عند V2 أو احجبها؛ لا balances ghost في users/account dashboard.
7. نفذ money reconciliation reports الأولية واختبارات التوازن وrefund allocation وdeferred visits وcash discrepancy.

## مخرجات إلزامية
- Finance ledger/adapters
- Entitlement/Renewal services
- wallet/bank/cash/refund APIs
- اختبارات PH-06

## اختبارات تكتب هنا وتنفذ فقط في PH-12
20 concurrent wallet debits لا سالب، refund retry مرة، 4 زيارات/30 يوم مستقلة، no grant قبل verified، expiry/reschedule credits، partial wallet/gateway reconciliation.

## بوابة اكتمال الكود
لا رصيدين ماليين؛ grants وrefunds idempotent؛ client cannot set paid/price.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
