# NIRAB — المرحلة 4: إثبات التنفيذ، إغلاق الطلب، التحصيل النقدي والعهدة والتدقيق

## المتطلبات السابقة
- Branch/financial model من المرحلة 1.
- Teams/dispatch من المرحلة 2.
- Service/quote model من المرحلة 3.

## الهدف
بناء Workflow إثبات الخدمة والتحصيل غير الإلكتروني بشكل محكم:
- Check-in جغرافي.
- صور قبل.
- Checklist خاصة بالخدمة.
- Work execution.
- صور بعد.
- Check-out جغرافي.
- OTP العميل.
- Completion.
- Cash/Bank transfer proof.
- اعتماد مدير الفرع ثم المحاسب.
- عهدة كاش لكل فني بحد 3000 SAR.
- Audit log شامل.

## مصادر السورس

### Backend
- `Modules/BookingModule/`
- `Modules/ServicemanModule/`
- `Modules/UserManagement/`
- `Modules/PaymentModule/`
- `Modules/TransactionModule/`
- `Modules/AdminModule/`
- `Modules/ProviderManagement/`
- `app/Providers/AuthServiceProvider.php`

معروف حاليًا:
- Booking يملك `evidence_photos` و`booking_otp`.
- Serviceman BookingController يستطيع `completed` ويتحقق من OTP حسب الإعداد.
- Customer لا يستطيع Completed مباشرة.
- Offline payment له approval حاليًا لكنه ليس مرحلتين Branch Manager ثم Accountant.

### Branch Manager App
- `lib/feature/booking_details/`
- `lib/feature/booking_requests/`
- `lib/feature/dashboard/`
- `lib/feature/reporting/`
- `lib/feature/payement_information/`

### Serviceman App
- `lib/feature/booking_details/`
- `lib/feature/booking_request/`
- `lib/feature/dashboard/`
- `lib/feature/profile/`

### Customer App
- `lib/feature/booking/`
- `lib/feature/notification/`

## المطلوب تنفيذه

### 1. حالات التنفيذ
راجع `BOOKING_STATUSES` وكل UI يعتمد عليها.

أضف/نظّم workflow بحيث يدعم على الأقل:
```text
pending
accepted/assigned
on_the_way
arrived
ongoing
work_finished (أو pending_verification)
completed
canceled
```

لا تكسر الحالات القديمة بدون mapping واضح.

Team job:
- Leader هو صاحب صلاحية transition التنفيذ الأساسية.
- Member لا يستطيع إنهاء Booking كامل إلا بصلاحية/دور خاص.

### 2. Geo Check-in
عند وصول الفني/Leader:
- endpoint `check-in` أو transition واضح.
- يتطلب current latitude/longitude.
- يسجل accuracy/timestamp إن متاح.
- اختياريًا يتحقق من مسافة معقولة من customer service location وفق setting configurable، ولا تستخدم قيمة hardcoded.
- يسجل `arrived_at` وموقع الوصول.

### 3. Before Photos
قبل بدء `ongoing` أو قبل Completion حسب policy:
- يرفع صور Before في collection منفصلة.
- لا تضع قبل وبعد في array واحدة غير مصنفة.
- metadata: uploader, time, type, optional lat/lng.
- minimum count configurable، والافتراضي 1 إن لم توجد سياسة أخرى.

### 4. Service Checklist
استفد من Service data وأضف:
- `service_checklist_items`
- `booking_checklist_results`

لكل service يمكن HQ تعريف عناصر مثل:
- تنظيف المطبخ.
- تنظيف الحمامات.

عند Completion:
- العناصر required يجب أن تكون checked/answered.
- احتفظ snapshot للـChecklist المستخدمة في Booking حتى لا يتغير التاريخ إذا عدّل Admin template لاحقًا.

### 5. Work Finished / Check-out
عند انتهاء الفني:
- يسجل finish latitude/longitude.
- `work_finished_at`.
- صور After mandatory.
- يحسب actual duration من check-in/start إلى finish.
- لا يحول Completed قبل استيفاء المتطلبات.

### 6. OTP العميل
العميل يستلم/يعرض OTP الموجود في النظام.

Completion requires:
- correct booking OTP.
- before photos موجودة.
- after photos موجودة.
- required checklist complete.
- geo check-in/out موجود حسب settings.

بعد نجاح الشروط:
- Booking → `completed`.
- أرسل notification فوري للCustomer/HQ/Branch حسب نظام الإشعارات.
- لا تجعل `completed` يساوي `paid` (تم إصلاحه في المرحلة 1).

### 7. Override Completion
Branch Manager/HQ يمكنه Force Complete فقط بصلاحية محددة:
- reason required.
- evidence/note required حسب policy.
- يسجل Audit log كامل.
- لا توفر هذا endpoint للفني العادي.

### 8. أنواع الدفع غير الإلكتروني
دعم واضح لـ:
- Cash.
- Bank Transfer.

الفني يسجل طريقة التحصيل الفعلية ويضيف:
- amount.
- proof image/receipt.
- transaction/reference number للتحويل إذا توفر.
- collected_at.

لا تعتبر الصورة وحدها approval مالي.

### 9. Two-stage Approval
أنشئ Payment Collection workflow منفصلًا عن Booking completion:

```text
submitted_by_technician
→ branch_verified / branch_rejected
→ accountant_approved / accountant_rejected
→ ready_for_accounting_sync
```

الحد الأدنى من الحقول:
- technician/submitted_by.
- branch_verified_by/at/note.
- accountant_approved_by/at/note.
- rejection reason.
- status.
- amount/method/reference/proof.

صلاحيات:
- Branch Manager يعتمد فقط collections لفرعه.
- Accountant يرى حسب scope المخصص.
- Technician لا يستطيع اعتماد ما رفعه.

### 10. Electronic Payment Handling
لا تمرر المدفوع الإلكتروني عبر approval اليدوي.

إذا Gateway webhook/payment state confirmed:
- `payment_status = paid/confirmed`.
- مؤهل للمحاسبة مباشرة في المرحلة 5.

### 11. Technician Cash Custody
أضف ledger خاصًا بكل فني:
- cash collected.
- cash handed to branch.
- adjustments بإذن محاسب فقط.
- current custody balance.

لا تخزن الرصيد فقط بدون ledger؛ يمكن cache balance لكن مصدر الحقيقة transactions.

### 12. حد 3000 SAR
Setting افتراضي:
- `technician_cash_custody_limit = 3000` SAR.

عند بلوغ/تجاوز الحد:
- الفني يبقى قادرًا على طلبات non-cash.
- يمنع من إسناد/قبول طلبات Cash الجديدة.
- Dispatch Engine من المرحلة 2 يعتبره ineligible لطلب cash.
- يظهر alert للفني ومدير الفرع.

لا تمنع كل الطلبات.

### 13. Cash Handover
Workflow:
- Technician ينشئ handover بمبلغ.
- Branch Manager confirms received amount.
- custody ledger ينقص بعد التأكيد الصحيح.
- Accountant يرى settlement/verification حسب الهيكل المالي.

يجب منع:
- مبلغ أكبر من custody balance بدون صلاحية adjustment.
- double confirmation.
- تعديل سجل approved بصمت.

### 14. Daily Settlement View
Branch Manager/HQ/Accountant يحتاج تقرير:
- opening custody.
- collected today.
- handed over.
- adjustments.
- remaining.

لا تبنِ Analytics المتقدمة؛ فقط operational cash settlement المطلوب.

### 15. Audit Log مركزي
أنشئ `audit_logs` أو equivalent reusable service.

سجل على الأقل:
- booking status overrides.
- payment approval/rejection.
- cash handover.
- custody adjustment.
- cancellation.
- price/quote changes إذا يمكن دمج Phase 3 history.
- actor ID/type/role.
- branch.
- entity/type/id.
- old/new values sanitized.
- IP/user agent إن متاح ومناسب.
- timestamp.

لا تسجل secrets أو OTP plaintext داخل audit payload.

### 16. فصل الصور/المرفقات
استخدم storage abstraction الحالية (local/R2/S3) ولا hardcode URL.

أنواع evidence يجب أن تكون واضحة:
- before.
- after.
- payment_proof.
- override_evidence.

### 17. UI الفني
Booking Details يجب أن يقود الفني بالتسلسل:
1. On the way.
2. Arrived + geo.
3. Before photos.
4. Start.
5. Checklist.
6. After photos.
7. Finish geo.
8. OTP.
9. Complete.
10. Payment proof إذا cash/transfer.

امنع زر Complete إذا missing requirement بدل الاعتماد على خطأ API فقط.

### 18. UI مدير الفرع/المحاسب
Branch Manager:
- pending payment proofs.
- approve/reject.
- custody by technician.
- overdue handover alerts.

Admin Accountant web:
- second-stage approval queue.
- filters by branch/method/date/status.
- audit details.

## معايير قبول المرحلة
- لا يستطيع الفني Complete بدون شروط الإثبات المطلوبة عندما تكون policy مفعلة.
- Before/After منفصلتان ومؤرختان.
- Geo check-in/out محفوظان.
- Checklist required enforced backend-side.
- Cash/transfer لا يصبح Approved for accounting قبل Branch + Accountant approvals.
- Electronic confirmed payment لا ينتظر approval اليدوي.
- عهدة الفني ledger-based وحد 3000 يمنعه من Cash jobs فقط.
- كل approval/reject/override/handover له Audit trail.


## قواعد تنفيذ إلزامية للوكيل

هذه الوثيقة **مواصفة تنفيذ** وليست تقريرًا أو اقتراحات. المطلوب تعديل السورس الفعلي وإنهاء نطاق هذه المرحلة بالكامل، مع الحفاظ على التوافق قدر الإمكان.

### قواعد عامة
1. افحص السورس الحالي قبل أي تعديل ولا تفترض أسماء ملفات أو جداول غير موجودة.
2. استخدم البنية الحالية Modular Laravel + Flutter، ولا تعِد بناء المشروع من الصفر.
3. حافظ على API backward compatibility كلما أمكن. إذا لزم تغيير عقد API، أضف الحقول الجديدة أولًا وحافظ على القديمة لفترة انتقالية بدل كسر التطبيقات فجأة.
4. لا تحذف جداول/أعمدة قديمة مستخدمة لمجرد أنها لم تعد ظاهرة وظيفيًا؛ علّمها Deprecated واتركها بقيم آمنة إن كانت إزالتها ستكسر أجزاء قديمة.
5. أي Migration جديدة يجب أن تكون قابلة للتراجع `down()` وأن تحتوي indexes/foreign keys المناسبة قدر الإمكان.
6. لا تضع Secrets أو مفاتيح حقيقية في الكود أو ملفات المثال. أضف أسماء متغيرات البيئة فقط عند الحاجة.
7. اجعل النصوص الجديدة قابلة للترجمة ولا تكتب نصوصًا عربية/إنجليزية ثابتة داخل منطق الباكند أو Widgets إن كان المشروع يستخدم ملفات ترجمة.
8. لا تغيّر حزم/Dependencies إلا عند الضرورة. إن أضفت Dependency فوثّق السبب في ملخص التنفيذ.
9. لا تُدخل كودًا تجريبيًا، TODOs غير منفذة، endpoints وهمية، أو stubs تُظهر الميزة وكأنها مكتملة.
10. عند وجود منطق قديم متعارض، افصله خلف Service/Policy/Feature flag مناسب بدل نسخ نفس المنطق في عدة Controllers.

### ممنوع أثناء التنفيذ
- ممنوع تشغيل السيرفر محليًا (`php artisan serve` أو ما شابهه).
- ممنوع تشغيل Docker/Compose أو بيئة كاملة.
- ممنوع تشغيل Emulator/Simulator أو فتح التطبيقات على جهاز.
- ممنوع تشغيل `php artisan migrate`, `migrate:fresh`, seeders أو تعديل قاعدة بيانات فعلية.
- ممنوع تشغيل PHPUnit/Pest/Flutter tests/E2E/Integration tests/Load tests/Stress tests.
- ممنوع الاتصال الحقيقي بـ Odoo أو بوابات الدفع أو SMS/WhatsApp أو أي خدمة خارجية أثناء التنفيذ.
- ممنوع إجراء اختبارات أداء ثقيلة أو إنشاء بيانات ضخمة.

### التحقق المسموح والمطلوب في نهاية المرحلة فقط
المطلوب **Build/Compile verification فقط** بدون تشغيل النظام:

**Laravel / PHP**
- شغّل `php -l` على ملفات PHP التي تم تعديلها/إنشاؤها.
- شغّل `composer validate` إن كانت Composer متاحة.
- يمكن تشغيل `composer dump-autoload -o --no-scripts` للتحقق من autoload فقط إذا كانت dependencies متاحة بالفعل.
- لا تشغّل أوامر Artisan التي تحتاج DB أو boot كامل للتطبيق.

**Flutter**
- لا تشغّل التطبيق.
- إذا كانت Flutter toolchain وdependencies متاحة: نفّذ Build فقط للتطبيقات التي تغيرت، مثل `flutter build apk --debug`، ولتطبيق العميل/الويب `flutter build web` إذا تغير جزء الويب.
- لا تبنِ iOS لأن ذلك قد يتطلب signing/macOS.
- إذا كانت البيئة لا تسمح بالبناء، لا تحاول تجهيز بيئة كاملة؛ اذكر بوضوح أن Build لم يُنفذ والسبب.

**Admin assets**
- إن تم تعديل JS/CSS المجمّع، يمكن تنفيذ build للأصول فقط إذا كانت node_modules/toolchain متاحة مسبقًا. لا تشغّل web server.

### تسليم الوكيل في نهاية المرحلة
أرفق ملخصًا قصيرًا يحتوي:
- الملفات التي تم تعديلها/إنشاؤها.
- migrations الجديدة.
- endpoints أو عقود API الجديدة/المتغيرة.
- أي إعدادات/ENV جديدة.
- نتيجة Build/Lint فقط.
- أي نقطة تعذر بناؤها بسبب عدم توفر toolchain، بدون تشغيل بيئة بديلة.
