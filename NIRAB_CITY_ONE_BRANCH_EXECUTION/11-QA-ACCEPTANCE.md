# CITY-11 | مصفوفة الجودة والقبول الشامل

## سياسة الاختبار

الوكيل يجري فقط فحوص المصدر الخفيفة والمسموح بها، ويجهز fixtures/automated small tests وAcceptance Checklist. **لا يجري Booking/Payment/DB على الإنتاج** ولا migrations فعلية. تنفيذ سيناريوهات التشغيل أدناه مسؤولية صاحب المشروع في Staging/Production بعد الموافقة والنشر المخطط.

## درجة الأهمية

- `P0`: يمنع إعلان الكود جاهزًا إذا لم يعالج.
- `P1`: يمنع تفعيل City Mode لجميع العملاء حتى يُختبر.
- `P2`: تحسينات UX أو حالة طرفية يجب توثيقها/تأجيلها بموافقة.

## مصفوفة حالات القبول

| ID | المستوى | السيناريو | النتيجة الإلزامية |
|---|---|---|---|
| GEO-01 | P0 | نقطة داخل مدينة active + branch ready | resolve verified + can save |
| GEO-02 | P0 | داخل مدينة draft/paused | منع إنشاء/تحديث العنوان |
| GEO-03 | P0 | خارج أي مدينة مدعومة | رفض واضح 422، لا حفظ |
| GEO-04 | P0 | مدينة selected != مدينة point | MISMATCH + موافقة تغيير، لا bypass |
| GEO-05 | P0 | spoof city_id/zone_id/provider_id | Backend لا يثق بالقيم ويرفض التلاعب |
| GEO-06 | P0 | إحداثيات مقلوبة/خارج المدى/NaN | 422 no write |
| GEO-07 | P0 | نقطة على حدود المدينة | سلوك جغرافي موثق وثابت، مع اختبار MySQL |
| GEO-08 | P0 | حدود مدينتين متداخلة | ambiguous لا اختيار عشوائي |
| GEO-09 | P0 | Geo service/DB failure | error/retry لا false unsupported |
| GEO-10 | P1 | عنوان قديم خارج مدينة نشطة | يبقى مع وسم غير صالح لحجز جديد |
| GEO-11 | P1 | تحديث عنوان مستخدم إلى مدينة غير مدعومة | PUT مرفوض كـPOST |
| GEO-12 | P1 | free service_address في checkout | لا bypass لحارس city |
| GEO-13 | P1 | guest يحفظ عنوان دون توكن principal | 401، لا تسريب بيانات |
| CITY-01 | P0 | نشر مدينة دون فرع | منع Publish |
| CITY-02 | P0 | ربط مدينة بفرعين | 409 unique conflict |
| CITY-03 | P0 | ربط فرع بمدينتين | 409 unique conflict |
| CITY-04 | P0 | Publish دون Geo Verified | منع وتفسير GEO_SOURCE_NOT_APPROVED |
| CITY-05 | P0 | Publish بفرع intake disabled | منع |
| CITY-06 | P1 | Pause مدينة ذات حجوزات مدفوعة | يمنع new؛ يحتفظ بالقديمة |
| CITY-07 | P1 | تغيير city branch مع حجوزات مستقبلية | impact preview/confirmation ورفض صامت |
| CITY-08 | P0 | مدير فرع ينشر/يربط مدينة عبر API | 403 |
| CITY-09 | P1 | مدينة active بلا خدمات مهيأة | لا تظهر قابلة للحجز |
| CAT-01 | P0 | منزل الرياض بعد اختيار الرياض | خدمات فرع الرياض المصرح بها فقط |
| CAT-02 | P0 | خدمة admin_enabled=false | مخفية عن الحجز حتى بعد inherited sync |
| CAT-03 | P0 | خدمة team 3 بدون فريق مؤهل | not ready/blocked cart |
| CAT-04 | P0 | فريق مهيأ لكن ممتلئ اليوم | الخدمة تظهر؛ تقويم اليوم ممتلئ |
| CAT-05 | P0 | fixed service مع variant غير صالح | 422 ولا Cart Item |
| CAT-06 | P0 | quote service | طلب عرض مستقل، لا Fixed cart |
| CAT-07 | P0 | Add to cart بلا عنوان verified | منع وطلب العنوان |
| CAT-08 | P0 | Cart mixed Riyadh+Jeddah | 409 ولا نقل/حذف صامت |
| CAT-09 | P1 | تغيير عدد/Options يؤثر team | إعادة تحقق وتحديث السعر/المدة |
| CAT-10 | P1 | خدمة في Cart أصبحت غير جاهزة | status needs_review، منع checkout بلا حذف صامت |
| CAL-01 | P0 | فرع مغلق الأحد | Closed، بلا Slots |
| CAL-02 | P0 | فنيان مطلوبان لكن ورديتهما لا تتقاطع | No Slots |
| CAL-03 | P0 | إجازة/حجز قائم للفني | Slot يعكس التعارض |
| CAL-04 | P0 | مدة 3 ساعات وبداية قبل نهاية دوام بساعة | No Slot |
| CAL-05 | P0 | سلة عدة خدمات تحتاج موارد متعارضة | لا وقت موهوم |
| CAL-06 | P0 | 7 أيام بفحص تلقائي | كل يوم verified/unknown/error بدون false negative |
| CAL-07 | P0 | timeout API | error/retry لا unavailable |
| CAL-08 | P0 | تغيير City/Address أثناء Fetch | تجاهل استجابة قديمة |
| CAL-09 | P0 | Daily من 4 أيام ويوم واحد غير متاح | يرفض الجدول كله ويعرض يوم التعارض |
| CAL-10 | P0 | Weekly يحترم أيام الأسبوع فقط | لا تواريخ إضافية |
| CAL-11 | P0 | Custom مختلف الوقت لكل زيارة | dates صحيحة وكل الزيارات متحققة |
| CAL-12 | P0 | same_time repeat | تحقق جميع الأيام من خطة واحدة |
| CAL-13 | P0 | Timezone جهاز مختلف | مواعيد Saudi ثابتة |
| BOOK-01 | P0 | آخر Slot يتسابق عليه عميلان | حجز واحد فقط، الثاني 409 بلا خصم مزدوج |
| BOOK-02 | P0 | تغيير branch assignment بعد preview | snapshot يفشل بدل نقل صامت |
| BOOK-03 | P0 | سعر يتغير بين preview/confirm | checkout_price_changed وموافقة جديدة |
| BOOK-04 | P0 | Wallet + bank transfer/card + cash | لا انحراف في amounts/idempotency |
| BOOK-05 | P0 | Subscription/Package visit مع city | أهلية مثبتة وعدد زيارات صحيح |
| BOOK-06 | P0 | Gateway callback مكرر | لا حجوزات أو خصومات مزدوجة |
| BOOK-07 | P0 | تاريخي multi-branch | يعرض ويتابع ويُسترد دون تغيير |
| BOOK-08 | P1 | Quote accepted + chosen slot | تحقق branch/time الأصلي |
| OPS-01 | P0 | Serviceman جدة يستقبل حجز الرياض | رفض إسناد |
| OPS-02 | P1 | Pause مدينة وحجز قديم قيد التنفيذ | يسمح بالتنفيذ المصرح به |
| OPS-03 | P1 | مدير الفرع يعدّل وردية | تقويم العميل ينعكس بعد invalidation |
| OPS-04 | P1 | Workflow API Provider القديم | لا regression في Auth/OTP |
| UX-01 | P1 | Android 360px / Web 506px / Desktop | لا Overflow، RTL/LTR سليم |
| UX-02 | P1 | عدم منح إذن GPS | اختيار يدوي صالح |
| UX-03 | P1 | فشل تحقق عنوان | زر حفظ معطل ورسالة retry |
| UX-04 | P1 | تغيير المدينة وسلة ممتلئة | موافقة صريحة، لا حذف صامت |
| UX-05 | P1 | خطأ خدمة غير متاحة | رسالة بجانب الخدمة لا خطأ عام فقط |
| SEC-01 | P0 | guest id متوقع وغير موثق | 401 وفق PaymentPrincipal الحقيقي |
| SEC-02 | P0 | مدير فرع يكتب فرع آخر | 403/404 Scoped |
| PERF-01 | P1 | قائمة مدن وخدمات متعددة | Pagination + no N+1 |
| PERF-02 | P1 | calendar over limit | 422 capped بلا طلب غير محدود |

## فحوص الوكيل الخفيفة

- `git diff --check`, `php -l` لكل PHP معدل، فحص JSON للعربية والإنجليزية، `node --check` للـJS المستقل.
- `dart format` على الملفات المعدلة، `flutter analyze` محدد **إذا** Dependencies متوفرة دون إعداد بيئة ثقيلة.
- Unit tests صغيرة لـCityResolver (in-memory fixture/test DB فقط إذا بيئة متاحة ومصرح بها) وCityBranchResolver وDecision DTO وCalendar guards.
- تحقق static من Routes/Policies وDB uniqueness/migration code review قبل أي تشغيل.
- راجع سلوك hold/lock/transactions والتوافق المالي بالشفرة، ولا تدّعِ فحص race على DB فعلية إذا لم يُنفذ.

## تقرير النتائج لكل حالة

جهز `reports/CITY-ACCEPTANCE-RESULTS.md` بأعمدة `ID | source verified | unit status | manual pending | evidence | blocker | owner`. لا تضع `PASS` لفحص runtime لم يُجرَ. صنّف `NOT RUN / PASS / FAIL / BLOCKED` بوضوح.

## شرط go/no-go

- لا نشر دون اعتماد مصدر الحدود الجغرافية، تثبيت migrations/unique constraints، تشغيل API/Flutter على Staging، نجاح P0 مالي/حجوزات/أمان، وموافقة صاحب المشروع.
- أي Regression في Payment/Refund/Existing bookings أو قبول عناوين غير مدعومة = **NO GO** مهما كانت الواجهة جميلة.
