# CITY-12 | خطة النشر والترحيل والتراجع — Owner Controlled

> هذا المستند **لا يصرّح للوكيل** بتنفيذ migrations أو نشر أو تغيير إنتاج. كل أوامر الإنتاج يُراجعها ويشغلها المسؤول فقط بعد إثبات أمانها ونسخ احتياطي.

## قبل النشر (Owner Gate)

1. نسخة source/DB Backup قابلة للاستعادة مع إثبات integrity؛ لا تكتفِ بوجود اسم ملف.
2. جرد branch/city/zone relationships من dry-run بدون تعديل البيانات، وتأكيد أن الرياض وجدة الحقيقتين متطابقتان مع التعريف الجغرافي للمدينة **وليس منطقة إدارية أوسع**.
3. مصدر جغرافي معتمد قانونيًا وتشغيليًا ونسخة ملف الحدود checksum ومراجعة overlap/border points.
4. مراجعة خطة DB migrations على MySQL 8 مطابق للإنتاج، Indexes وحجم الجداول ونوافذ القفل.
5. فحص الاعتماديات: Backend الجديد يقرأ أعمدة additive؛ لا تباشر تشغيل كود يعتمد عمودًا قبل تطبيق migration في نافذة النشر.
6. تقارير PHPUnit/Unit/Static ومصفوفة P0 ومراجعة `Checkout snapshots`/Payments/Refunds/Quotes.
7. خطة التعامل مع booked/future jobs والإنذارات، واختبار رجوع عميق لنسخة frontend/backend تحت feature flag off.

## مراحل طرح مدروسة

### Wave 0 — DB schema فقط (بإذن الإدارة)

- تطبيق Migrations additive (بدون إسقاط جداول قديمة أو تهيئة مدن نشطة).
- تحقق أن `CITY_MODE` غير مفعل؛ Customer/Provider القديم يعمل.
- لا backfill غير قابل للعكس؛ حفظ backups والتحقق من indexes.

### Wave 1 — أدوات الإدارة وتجهيز المدن

- تجهيز City Registry والحدود المعتمدة، وربط الرياض/جدة بفروعهما **بعد التحقق من بياناتهما الفعلية**، وإنشاء Assignment واحد فقط لكل مدينة وفرع.
- City Draft افتراضيًا؛ لا تصير active بسبب migration.
- نفّذ readiness preview قبل النشر؛ تعطيل draft لا يؤثر على الحجوزات القديمة.
- إن احتاج ترحيل zones، سجل mapping كل حالة ambiguous بدل تخمين.

### Wave 2 — API في وضع توافق

- نشر endpoints الجديدة وFacades/Cards والحماية مع feature flag off؛ تحقق من الردود على Fixtures/Stage.
- تأكد أن `GET /cities` لا يكشف draft أو مدن بلا ready service.
- تحقق أن authorization/rate limiting/address guard تعمل على Stage.

### Wave 3 — Customer Web + Android pilot

- ابدأ ببيئة اختبار ثم جمهور داخلي/مدينة أو حسابات تجريبية مع قيد الـflag.
- اجعل Backend وFlutter يبقيان compatible عبر deploy skew (نسخة تطبيق قديمة مع Backend جديد)؛ لا يمرر العميل القديم إعداد City Mode غير مصرح.
- تحقق من حفظ عنوان ومتغيرات السلة، تقويم regular/repeat وQuote قبل أي فتح عام.

### Wave 4 — تفعيل تدريجي

- تفعيل `NIRAB_CITY_MODE_ENABLED` حسب إعدادات Feature Flags وبصلاحية مخولة، مع شرط readiness وحماية البيانات.
- لا تعطل تدفق حجوزات قديمة تحتاج Provider/Area contexts؛ اجعل `checkout_snapshot` يحمل mode/version ويمكنه المتابعة عبر مساره الأصلي.
- راقب: نسبة رفض العناوين، mismatch، Error/Timeout، empty calendar، cart 409، checkout price_changed، booking payment failures، queue failures، latency.

### Wave 5 — الإغلاق والتوثيق

- تقرير قبول واقعي (Android/Web/Admin/Provider/Serviceman)، وقياس حدود المدن في عينات من مناطق مختلفة.
- استكمال AR/EN والتوثيق؛ إخفاء الشاشات legacy من الـnavigation فقط حين تصبح غير لازمة للحجوزات الجديدة.
- لا تحذف جداول/أعمدة legacy إلا في مشروع cleanup منفصل بعد فترة استقرار وأرشفة وتقييم اعتماد السجلات القديمة.

## Feature flags المقترحة

| العلم | الافتراضي | المعنى |
|---|---|---|
| `NIRAB_CITY_MODE_ENABLED` | false | مسار المدن الجديد للعميل/الحجوزات الجديدة |
| `NIRAB_CITY_ADMIN_ENABLED` | false | واجهات إدارة المدن (بعد migrations) |
| `NIRAB_CITY_CALENDAR_ENABLED` | false | Calendar inline/API الدفعي |
| `NIRAB_CITY_STRICT_ADDRESS_VALIDATION` | false في مرحلة التكامل، ثم true **كشرط إلزامي عند تفعيل City Mode** | حارس حفظ العنوان |

**تحذير:** لا تسمح بأي حالة `CITY_MODE_ENABLED=true` و`STRICT_ADDRESS_VALIDATION=false` في الإنتاج. ارفض الإعداد غير الآمن عند boot/healthcheck أو checkout على الأقل؛ لا تعتمد على التذكّر اليدوي.

## Rollback

### إيقاف مبكر آمن

1. أوقف قبول جلسات City Mode جديدة عبر Feature Flag وأعد توجيه المستخدمين بطريقة مفهومة؛ لا تمس حجوزات City Mode أنشئت بالفعل.
2. حافظ على DB additive والجداول وعناوينها؛ إيقاف الكود الجديد لا يعني `DROP cities`.
3. Checkout Snapshots الصادرة سابقًا: إما إكمالها بصورة متوافقة تحت code path versioned أو طلب إعادة معاينة، **لا إعادة تسعير/نقل صامت**.
4. رجوع Backend/Flutter إلى Build متوافق مع Schema الحالي بعد اختبار مسبق؛ لا ترجع Migration destructive عن بيانات مدن استخدمت فعليًا.
5. أوقف نشر المدن الجديدة وأبلغ العميل إذا تأثرت إمكانات الحجز، مع استمرار إدارة الحجوزات المدفوعة والتنفيذ.

### شروط وقف التفعيل تلقائيًا/يدويًا

- أي قبول Server-side لعنوان مدينة غير مفعلة.
- أي حجز مزدوج أو فقد ربط الفرع أو دفع غير متوافق.
- تعطل نسبة ملحوظة من حفظ العناوين بسبب خطأ resolver/geo dataset.
- إسناد خدمة مدينة أخرى أو انتقال صامت لفرع آخر.
- استجابة Calendar positive مع عدم القدرة على الحجز على نحو منهجي بسبب اختلاف engine.

## تقارير الأداء والمراقبة

Dashboard/metrics بدون تفاصيل شخصية: `city_resolve_result_by_code`, `address_rejected`, `service_configured_count`, `calendar_latency_p95`, `no_slots`, `booking_409`, `checkout_preview_mismatch`, `queue_failure`, `payment_finalize_error`, `city_assignment_conflict`.

## التسليم

`CITY-09-RELEASE-READINESS-REPORT.md` يضم: PRs/Commits، migrations وbackfill dry-run، flags، خطوات Stage، monitoring، rollback rehearsals (إن نُفذت)، Owner gate checklist، ومخاطر مفتوحة. كل عنصر غير منفذ يوضع `Pending` لا `Passed`.
