# S04 — السلة وطلب الشراء والتأكيد
## السلة الموحدة
تبقى سلة العميل موحدة وتشمل أنواع خدمات مختلفة. تقسيم بصري: ready_to_book، quote_drafts، visit_pack_products، memberships، unschedulable. حقول كل عنصر: service/offer/version، نوع المنتج، الإجابات، الكميات والإضافات، city/address، schedule_preference، price_state، estimated amount، validation_errors.

لا يمسح تغيير المدينة سلة مدينة أخرى. إتمام واحد يختار عناصر **مدينة واحدة وعملة واحدة**؛ كل عنوان/موعد/متطلبات تنفيذ يشكل group. يظل باقي المحتوى في السلة. لا يحصل نقل صامت من جدة إلى الرياض أو من فرع المدينة إلى فرع آخر.

عند merge بعد login: استخدم guest token صحيحاً، افحص الملكية، حافظ على configs المختلفة كعناصر مستقلة، اعرض التعارض، لا تنشئ entitlement أو booking أثناء merge. وجود phone/userId في body لا يثبت ملكية حساب.

## فصل طرق التأكيد
- `cart`: اختيارات cart_item_ids subset؛ لا يسمح quote غير مقبول ضمن مبلغ الدفع.
- `buy_now`: يمر بنفس preview/confirm عن طريق intent مستقل؛ لا يمس بقية السلة.
- `accepted_quote`: snapshot لنسخة مقبولة + عنوان + موعد مع إعادة الإتاحة.
- `entitlement_redemption`: يحجز زيارة من الرصيد + يدفع الإضافات فقط إن وجدت.
عضوية/رصيد زيارات جاهز السعر يمكن شراؤه مع خدمات المدينة في order واحد إذا payment eligibility مشتركة؛ لا يحجز كل الزيارات تلقائياً. إنشاء entitlement يحدث عند verified payment فقط. نقد بعد الخدمة لا يمول عضوية أو pack غير مدفوع؛ يستبعد النظام وسيلة الدفع غير المشتركة مع شرح أو يفصل التأكيد بموافقة العميل.

## تكوين مجموعات التنفيذ
تجمع بنود في حجز واحد فقط مع عنوان واحد، نافذة زمنية مشتركة، workflow متوافق، branch واحد، مهارات وموارد يمكن حجزها معاً، وقواعد سلامة تسمح بالجمع. لا يكفي أن تكون من نفس الصنف. الافتراضي sequential ضمن المجموعة ويجمع durations+buffers؛ parallel يتطلب إعداد صريح وحجز فرق منفصلة. باقة مركبة تمرر مكوناتها لمخطط المتطلبات لا مجرد duration العرض.

رسوم group محسوبة مرة واحدة؛ splitting لتلبية الموارد لا يرفع رسوم العميل دون موافقة. groups متعددة تدخل checkout واحدة شرط المدينة والوسيلة الواحدة. group فيه عنوان نقل له origin/destination محفوظين، كلاهما ضمن السياسة الداخلية المعتمدة؛ النقل بين مدن خارج نطاق V2 ويحول لمراجعة لا تسعير تلقائي.

## بروتوكول التأكيد
1. `preview`: تحقق auth/schemas/versions/city/branch/terms؛ أعد الأسعار وتفاصيل groups والمواعيد والمهلة والمبلغ المطلوب؛ لا كتابة order أو payment خارجي.
2. `confirm` مع Idempotency-Key + preview_id + expected_hash + payment method + consent: قفل principal/idempotency ثم branch/الموارد بالترتيب الموحد، افحص preview والبيانات والسعة.
3. في transaction واحدة: أنشئ order/items/groups/bookings pending/reservations وwallet hold عند اللزوم وسجل outbox؛ all-or-none لكل العناصر المختارة. لا استدعاء gateway أو Odoo/FCM داخل transaction.
4. online: بعد commit أنشئ PaymentRequest/session من snapshot عبر gateway adapter؛ يبقى attempt pending_payment وcapacity hold TTL. cash_after_service: حجز confirmed مع unpaid؛ wallet: تحقق available واخصم/قيد مرة، confirmed. bank transfer: pending_review بمهلة/حجز مؤقت معلنة، لا paid قبل اعتماد مسؤول.
5. Webhook موثّق أو تحقق server-to-server فقط يثبت المال. redirect نجاح ليس دليلاً. ربط order+amount+currency+principal+generation قبل finalization.
6. verified payment → transaction نهائية تتحقق من holds؛ تحولها committed وتنشط entitlement وتنظف cart items المختارة فقط، وتنشر outbox بعد commit.
7. انتهاء hold: expire attempt وحجوزاته غير المدفوعة وتحرير wallet hold؛ وصول مبلغ لاحق لا يحيي الموعد من دون إعادة الحجز. يحاول reacquire مرة تحت lock؛ إن تعذر ينتقل `resolution_required` مع سجل refund/تواصل واضح. لا overbooking ولا تغيير موعد تلقائي.

## سياسة idempotency والتزامن
UNIQUE(actor,operation,key)، hash canonical body. نفس key/body يعيد نفس نتيجة العملية دون ثانية؛ body مختلف 409 IDEMPOTENCY_MISMATCH. الطلب الجاري 202 مع retry_after/status URL؛ لا lock مبهم لا نهاية له. الاحتفاظ بمرجع العملية حتى لو انتهت صلاحية سجل caching، فلا يعاد دفع/حجز تمت له side effects.

ترتيب الأقفال ثابت لكل confirmation/refund/cancel/consume: idempotency/principal → branch ids sorted → resource locks sorted → order/payment/wallet/entitlement sorted حسب registry مشتركة. راجع كل entrypoint لمنع اختلاف الترتيب؛ أعد transaction في deadlock بحد 3 دون تكرار external calls. يمكن قفل provider row الحالي كحارس coarse-grained أولي بدل اختراع exclusion index غير موجود في MySQL؛ المعيار صحة السعة لا throughput نظري.

## مراجعة وحجز العميل
شاشة review تعرض المبالغ والضريبة والخصومات والرسوم لكل group، الموقع والتاريخ ونوع الفترة (arrival window/appointment) والمدة التقديرية، ما سيدفع الآن وما يستحق لاحقاً، وطلبات التسعير المستبعدة. حتى لو دفع مرة واحدة تعرض قائمة bookings مستقلة. لا تعرض `paid` لمجرد الحجز.

تمكين تعديل قبل التأكيد يعيد preview؛ بعد التأكيد reschedule/adjustment مسارات منفصلة. إلغاء group لا يلغي الأخرى؛ order state مشتقة من أبنائه. أسئلة العميل ومرفقاته تظهر في المهمة الصحيحة وليس في حجز آخر.

## حالات يجب ألا تضيع
double-click، app restart أثناء الدفع، payment succeeds لكن الرد ضاع، failed group capacity، price changed، city pause، service archived، cart user switched، stale address boundary، wallet insufficient، coupon انتهى، bank receipt rejected، refund partial. كل حالة لها error code/واجهة إعادة محاولة تحفظ البيانات المسموح بها.
