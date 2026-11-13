# PH-05 — السلة والطلبات وإتمام الحجز

**التنفيذ:** CODE ONLY. تسبقها PH-04. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S04-CART-CHECKOUT-ORDERS.md](../specs/S04-CART-CHECKOUT-ORDERS.md)

## المهام المتسلسلة
1. نفذ cart CRUD/subset/guest merge/assets configurations وcity grouping؛ لا حذف سلة مدينة ثانية أو خلط branch ضمن الطلب.
2. نفذ checkout preview source adapters cart/buy_now/accepted_quote/entitlement مع groups/method eligibility وsnapshot/hash.
3. نفذ confirmation transaction order/items/groups/bookings/reservations ومفاتيح retry. pending online لا paid؛ cash creates unpaid؛ wallet via Finance port.
4. اربط PaymentModule بattempt/order context بعد commit، ولا external charge داخل DB transaction. اكتب late callback reconciliation path موضح في S04.
5. نفذ order detail/bookings DTOs، lifecycle aggregation، cancellation preview وpartial cancel والتعديلات التي تحفظ البقية.
6. حول backend cart/booking helpers والhooks القديمة إلى adapters أو block عند cutover حسب map، بلا dual writes.
7. اكتب tests للمشاهد mixed cart/buy now without clearing/price change/auth/confirmation repeated/multiple groups one failure.

## مخرجات إلزامية
- Cart/Checkout/Orders APIs وapplication services
- Payment binding adapter
- legacy route guards
- اختبارات PH-05

## اختبارات تكتب هنا وتنفذ فقط في PH-12
لا طلبين بضغطة مزدوجة؛ لا خصم إذا فشل group؛ mixed cities error واضح؛ الsubset المتبقي في السلة؛ gateway callback foreign order مرفوض.

## بوابة اكتمال الكود
كل intent يصل محركاً واحداً؛ order/booking/payment حالات منفصلة؛ لا total صفر لمجهول.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
