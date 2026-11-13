# CITY-03 | نموذج البيانات والقيود والترحيل الآمن

## مبدأ التصميم

استعمل الجداول الحالية للـProvider/Services/Cart/Booking، ولا تنشئ نسخًا موازية من Entity موجودة. أضف **سجل مدن قانوني/تشغيلي** ومستوى تحقق العنوان. القرار النهائي للأعمدة يعتمد على Gate A، لكن يجب أن يحفظ الثوابت الآتية. الترحيلات مكتوبة فقط؛ لا تُنفّذ على الإنتاج بوساطة الوكيل.

## A. City Registry (مقترح اسمي)

`cities` (إذا لم يوجد ما يكافئه حرفيًا ويمكن ترقيته بأمان):

| الحقل | النوع/القاعدة |
|---|---|
| `id` | UUID، مفتاح أساسي (مطابق لأسلوب المشروع) |
| `country_code` | `SA`، مع تصميم يسمح GCC لاحقًا دون تفعيل مدن خارج السعودية الآن |
| `canonical_code` | رمز جغرافي ثابت من المصدر، فريد ضمن البلد |
| `name_ar`, `name_en` | أسماء عرض؛ ليست مفاتيح تحقق |
| `administrative_level` | `city`، ليس province/region |
| `timezone` | `Asia/Riyadh` حاليًا |
| `status` | `draft/active/paused/retired`، default `draft` |
| `geo_verification_status` | `unverified/verified/review_required` |
| `boundary_version_id` | مصدر تعريف حدود المدينة المعتمد إن كان به جدول منفصل |
| `legacy_zone_id` | nullable/FK أو mapping بديل يُقرّ بعد Gate A؛ الحفاظ على التوافق |
| `published_at`, `published_by`, `paused_at` | توقيت وفاعل التغيير، مع Audit Log مستقل |
| timestamps | حسب أعراف Laravel |

الرمز `canonical_code` لا يخترع من اسم المستخدم؛ يُربط ببيانات إدارية/جغرافية معتمدة. يمنع تكرار نفس المدينة بأسماء لغوية مختلفة.

## B. جدول حدود المدينة

`city_boundary_versions` أو جدول مطابق لتصميم المشروع:

- `id`, `city_id`, `geometry` من نوع POLYGON أو MULTIPOLYGON وفق المصدر، SRID 4326 **بعد التحقق من دعم MySQL الفعلي**.
- `source_name`, `source_reference`, `source_license`, `source_date`, `imported_at`, `reviewed_by`, `checksum`, `version`, `status`.
- حد **نسخة واحدة منشورة** لكل مدينة؛ نسخ Draft تحفظ لمراجعة التغيير. تغيير الحدود يخضع لمعاينة أثر على عناوين/حجوزات مستقبلية.
- Check geometry validity، bounds، SRID، ثبات Longitude/Latitude، فهرس مكاني حيث يدعمه schema، ولا تسمح import غير موثوق.
- لا تخترع شكل المدينة من rectangle أو circle ولا تحوّل Polygon خدمة فرع قديم تلقائيًا إلى حدود مدينة.

## C. الربط الحصري للمدينة والفرع

`city_branch_assignments` كجدول **التعيينات الحالية فقط**:

- `id`, `city_id` NOT NULL UNIQUE, `provider_id` NOT NULL UNIQUE، `assigned_by`, `assigned_at`, timestamps.
- مفتاحان Foreign Key إلى cities/providers مع `ON DELETE RESTRICT`، لا cascade على الحجوزات أو المال.
- يدعم انتقال الفرع بإزالة/تعديل السجل الحالي داخل Transaction مقفل مع كتابة Audit مستقل (لا تحتفظ بصفوف تاريخية داخل نفس الجدول الفريد).
- قرار إعادة تعيين مدينة بفرع آخر إذا فيها حجوزات مستقبلية **محجوز/مدفوع**: يُمنع تلقائيًا ويُحال إلى إجراء إدارة مع أثر وتأكيد وحفاظ على الفرع الأصلي لكل حجز. لا تعدل `bookings.provider_id` رجعيًا.
- إذا اختير إضافة `city_id UNIQUE` إلى `providers` بدلاً من جدول الربط بعد Gate A، أثبت المعنى نفسه (current-only + audit + historical immunity) ولا تجمع مع جدول علاقة متناقض.

## D. عنوان العميل

- افحص جدول عنوان العميل الموجود وأضف `resolved_city_id` (أو `city_id` في موضع موحد) nullable في مرحلة التوافق، `location_verified_at`, `city_boundary_version_id` أو `verification_source` حسب الحاجة.
- لا تعتبر `zone_id` المرسل من Flutter مصدراً للثقة؛ Back-end يحله من `city_id`/عنوان محفوظ معاد التحقق منه.
- لا تحفظ عنوانًا جديدًا إلا إذا: Coordinates صحيحة + تقع داخل **مدينة واحدة** منشورة قابلة للخدمة + الفرع المربوط صالح وفق policy.
- لا تحذف عناوين سابقة تقع خارج تغطية جديدة؛ ضع حالتَها `unsupported_or_revalidation_required` في الاستجابة/API دون محوها.
- عند تغيّر الحدود أو إيقاف المدينة: لا تعدّل عنوان العميل صامتًا؛ أبطل صلاحيته لإنشاء حجز جديد حتى إعادة التحقق.

## E. Cart / Snapshot / Booking

- لا تضف `provider_id` متناقضًا يختاره العميل؛ استخدم resolution ثابت من city في context؛ أبقِ الحقول القديمة للتوافق عند الحاجة.
- حفظ context واحد للسلة الجديدة `city_id`, `address_id`, `branch_id`, `context_revision` على مستوى checkout/session/snapshot وفق schema القائم (لا تضاعف state بلا داع).
- Snapshot يضم city/branch/zone/version/price/items/visits والملكية/المدة/التوقيع كالموجود، كي لا تتبدل الخطة بعد الموافقة.
- أضف حقل City لبيانات الحجز الجديدة فقط إذا كان مفيدًا وليس بديلًا لـ `provider_id`؛ احتفظ بالأرشفة والأرقام ومسار الدفع القديم.
- إن كانت DB حالياً تحفظ `bookings` لعدة فروع، لا تدمجها ولا تُعد كتابة نتائج الدفع القديمة.

## F. علاقة Zone الحالية

- حافظ على `zone_id` كطبقة توافق حتى الانتهاء من جميع Pricing/Cart/Booking/Payment/Quote flows.
- إذا كان لكل مدينة Zone تشغيلية واحدة: اربط mapping فريدًا صالحًا، ووثقه وقيده حسب النظام.
- إذا كانت Zone مشتركة لمدن أو مجزأة: لا تُجبر على mapping خاطئ؛ أنشئ mapping explicit مع اختيار pricing zone مضبوطة بالتعريف، واختبر الضرائب والقسائم والعروض. لا تخمّن زونًا من الفرع الأقرب.
- لا تمس `branch_service_areas` القديمة في سجلّات الحجز؛ أزل اعتماد **الحجوزات الجديدة في city mode** عليها بعد التطابق.

## خطة migrations دون تنفيذ

1. Migration additive لجدول المدن/العلاقة/الحدود (أو extend existing وفق Gate A).
2. Migration nullable لحقول العنوان الجديدة وأية فهارس.
3. Migration لعلاقات أو قيود فريدة بعد معالجة التكرارات في بيانات الاختبار؛ لا تضف `UNIQUE` قبل تقرير توافق البيانات.
4. أمر Artisan `nirab:city-audit --dry-run` يعرض مرشحي المدن والفروع ومشاكل التداخل واليتامى والازدواج، **قراءة فقط**.
5. أمر `nirab:city-backfill --dry-run` ثم `--apply --confirm ...` خارج نطاق تنفيذ الوكيل الفعلي؛ يجب بناء قواعد آمنة، ولا تجعل التنفيذ الافتراضي `apply`.
6. لا تُحوّل كل الفروع تلقائيًا إلى مدن منشورة؛ الرياض وجدة مرشحتان تحتاجان مراجعة تعريف الحدود وربط الفرع وتوافق الخدمات.

## فحوص سلامة البيانات

- لا يوجد أكثر من branch حالي للمدينة ولا أكثر من city حالية للفرع.
- لا مدينة `active` دون حدود verified أو آلية تحقق بديلة موثوقة معتمدة ودون فرع صالح.
- لا ازدواج أسماء يُخفي مدينة مختلفة.
- لا عنوان جديد `verified` خارج مدينة صالحة.
- لا تزامن متعارض عند طلبين يربطان الفرع نفسه في الوقت نفسه: استعمل DB unique+transaction والتقاط conflict.
- لا `ON DELETE CASCADE` يطال Bookings/Payments/Addresses.

## مخرجات المرحلة

تقرير `CITY-02-DATA-REPORT.md`: مخطط حقول وفهارس نهائي قبل/بعد، ملفات migration الجديدة، شرح `up/down`, سياسة legacy، dry-run outputs **على fixtures فقط**، والقيود أو حالات الحظر للنشر.
