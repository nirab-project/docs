# S05 — المدن والإتاحة والجدولة والزيارات
## 1. المدينة والفرع: قيود ثابتة
احتفظ بـ cities/city_boundary_versions/city_geographic_sources/city_branch_assignments وبـlegacy_zone_id اللازم للتهيئة. unique(city_id) وunique(provider_id) في assignment لا يلغيان. الحدود تأتي من سجل مصدر معتمد؛ لا point-in-city اعتماداً على اسم Google أو centroid فقط. عنوان العميل داخل مدينة منشورة ومصدر معتمد؛ أي عدم تطابق revision أو إيقاف المدينة/الفرع يعطل تأكيد جديد برسالة قابلة للمعالجة. لا اختيار فرع للعميل ولا تفرع إلى أقرب مزود مستقل.

نقل الربط من legacy catalog إلى city_offers يتطلب تعديل `CityServiceabilityFacade`, `CityBranchResolver`, `BranchReadinessService`, CityCartContext وavailability adapters دون تغيير قرار الجغرافيا. حفظ إعدادات المدينة لا يفعّل catalog تلقائياً. لا `max(1,capacity)` لتغطية سعة صفر: صفر يعني غير قابل للحجز حتى تهيئة المالك.

## 2. مصدر الإتاحة
مدخلات: city/address verified + item/offer versions + normalized answers/quantities + requirements + dates/rule + desired time + timezone. مخرجات: days مع available count/reason؛ slots بمعرف opaque مربوط context/hash وstart/end/window_kind وcapacity info مناسبة للعميل. لا تعرض IDs موظفين أو سعة داخلية حساسة إذا لم تلزم الواجهة.

حساب السعة يشمل ساعات عمل الفرع، الإجازات، ورديات الفنيين، المهارات، حجم الفريق، الحجوزات الفعلية، holds غير المنتهية، مدة العمل وbuffers والتنقل، الحد اليومي والموارد الخاصة إن فُعّلت. `totalCapacity` لا يساوي بالضرورة عدد الفنيين. فترات الاستلام/الوصول لا تعني أن مدة التنفيذ طول النافذة؛ الحجز يحتجز الفترات التشغيلية الفعلية.

مصدر واحد للقراءة والحجز عبر `ResourceAvailabilityPort`. لا تبقِ محركين أحدهما يقرأ legacy bookings والآخر V2 في الإنتاج. استبدل استعلامات Booking/BookingRepeat داخل خدمات الإتاحة القائمة بـV2 repository أو أعد هيكلتها؛ كل entrypoint يمر بهذا المنفذ، بما فيها الفني والإدارة وإعادة الجدولة والاشتراكات.

## 3. التزام السعة
- فحص availability GET/POST هو advisory. كل commit يعيد الفحص تحت lock.
- الحجز المؤقت ينشأ عند checkout confirm وليس عند رؤية calendar؛ له expires_at، ولا يمدد بالrefresh إلى الأبد.
- branch locks مرتبة + reservations للموارد؛ القراءة داخل transaction بعد القفل وبعزل READ COMMITTED. لا الاعتماد على `COUNT < limit` خارج transaction.
- الخدمة التي تحتاج 2 فنيين لا تحجزهما إن كان أحدهما في leave أو hold متداخل؛ team skill policies صريحة. service bundle يتطلب اتحاد متطلبات مكوناته.
- capacity load للزيارة مرة واحدة؛ pickup وdelivery موارد منفصلة في windows مختلفة؛ parent order ليس زيارة.
- تعليق الفرع يمنع جديداً، لكنه يفتح قائمة معالجة الحجوزات القائمة ولا يلغيها بصمت.

## 4. أنواع الجدولة
| النمط | التصرف |
|---|---|
| once | تاريخ/وقت واحد لكل group |
| selected_dates | قائمة مواعيد صريحة؛ تزيل التكرارات بعد normalization وتمنع الماضي |
| recurrence_plan | يوم/أسبوع/فاصل معروف + count أو until إلزامي؛ معاينة جميع occurrences قبل الالتزام |
| prepaid_redemption | اختيار زيارة أو عدة زيارات حتى حد الرصيد والصلاحية |
| pickup_delivery | موعد استلام مؤكد + تسليم متوقع أو مؤكد وفق policy |
| inspection | booking مستقل هدفه معاينة، لا موعد تنفيذ الخدمة النهائية |

العميل يمكنه طلب «موعد مشترك» فيحسب تقاطع إتاحة المجموعات ثم يتحقق من الموارد مجتمعة؛ خدمة تتطلب إخلاء موقع لا تتزامن مع فريق آخر. عدم وجود تقاطع لا يجعل كل الخدمات unavailable؛ اقترح مواعيد مستقلة. ترتيب slots يثبت timezone ولا يتبع timezone الجهاز.

المواعيد المتكررة finite فقط؛ إذا تعذر occurrence لا تُنقل تلقائياً ليوم آخر. في checkout جديد: all-or-none للجدول المختار، أو عرض اقتراح جديد يقبله العميل. في باقة قائمة: يحجز ما اختاره العميل كعملية ذرية محددة؛ يوضح غير المجدول ولا ينقص رصيده.

## 5. إعادة الجدولة والإلغاء
احجز الموعد البديل وافحصه قبل تحرير القديم ضمن transaction واحدة. لا تفقد الموعد القديم إذا فشل البديل. التغيير إلى عنوان آخر يعيد city/price/resources؛ تغيير مدينة يتطلب إعادة إنشاء المسار التجاري وليس تبديل branch قسري. يظل الإلغاء مستقلاً عن refund status. booking_completed immutable؛ correction عبر event إداري موثق لا rewind صامت.

سياسة no-show وما بعد البدء ورسوم الإلغاء تعرض قبل الشراء، ويعتمدها المالك. عند نقص معلومات لا تُخترع رسوم: يحتاج تدخل إداري. تعويض إلغاء الفرع يعيد visit credit أو يفتح refund حسب وسيلة الشراء، ولا يعاقب العميل برسوم no-show.

## 6. الاستحقاقات والزيارات
`available = total_units - reserved_units - consumed_units`؛ reserve عند تأكيد الموعد، consume عند completion، release عند إلغاء مؤهل. قيد unique(event_key) يمنع double consume/release. إذا no_show يستهلك حسب policy، مع audit/notification؛ لا تختزل cancelled وno_show.

صلاحية زيارة تتحقق عند تاريخ الخدمة لا مجرد تاريخ حجزها. لا يمكن حجز بعد expires_at. إذا كانت الزيارة داخل الصلاحية ثم تسبب الفرع بتأجيل خارجها، تمديد مبرر مسجل أو refund؛ لا انتهاء صامت. انتهاء pack لا يلغي confirmed visit تلقائياً دون reconciliation.

4 غسلات خلال 30 يوماً تعني total_units=4 وvalidity_days=30؛ الحد الأدنى بين الاستلامات حقل مستقل. كل غسلة تشمل pickup/process/delivery ولا تُستهلك 3 أرصدة. excess weight أو extra unit بعد الوزن يحتاج قبول زيادة قبل التحصيل. مقاسات الأكياس والوزن وعدد القطع التقريبي حقول منفصلة: ممنوع استنتاج `bagsCount×bagSize` إذا تعريف bagSize ليس محدداً صراحة.

## 7. التجديد المالي
manual renewal مدعوم دائماً عند صلاحية المنتج؛ auto renewal يعمل فقط بميزة enabled وتفويض دفع/محفظة واضح صالح + gate لمزود الدفع. لا تخزين بطاقة. لكل دورة `agreement_id + cycle_start` unique؛ verified payment مرة واحدة → grant مرة واحدة. فشل الدفع لا يعطي رصيداً ولا يحجز مواعيد غير ممولة. إلغاء التجديد لا يلغي الرصيد المدفوع الحالي.

اقتراح مواعيد تلقائي ليس ضماناً؛ توليد confirmed visits يحتاج رصيداً فعالاً وcapacity commit. إن فشلت السعة بعد التجديد تبقى الزيارات غير مجدولة مع إخطار وإجراءات إعادة جدولة/استرداد معلنة، لا schedule وهمي.

## 8. مهام الجدولة المطلوبة
expiry holds، انتهاء quotes/previews، إغلاق الطلبات القديمة غير المدفوعة، expiry entitlements، renewal attempts، outbox retries، تذكير موعد، إخطار تعثر الجدولة. جميعها idempotent وbatch bounded وclock injectable. تكتب في مراحل الكود، وتُشغل لأول مرة في PH-12 مع ساعة اختبار؛ لا تغيير ساعة VPS. لا تعطل expiry handling لمجرد تعطل worker: قراءة expires_at نفسها تمنع hold قديم من احتكار السعة.

## حجز الموارد قبل التعيين الظاهر
عند confirmation يحجز المخطط موارد مؤهلة فعلية أو pool ذا ضمان قابل للإثبات؛ لا يعد بموعد اعتماداً على عدد موظفين مجمل لا يراعي مهاراتهم. يمكن إبقاء تعيين الفني غير ظاهر للعميل حتى اعتماد الفرع، لكن أي إعادة تعيين تنقل reservation ذرياً إلى مورد متاح. لا يحرر حجز الموارد قبل نجاح البديل.
