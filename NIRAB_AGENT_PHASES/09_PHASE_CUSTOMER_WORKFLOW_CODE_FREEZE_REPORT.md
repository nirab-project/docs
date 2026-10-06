# المرحلة 9 — تقرير إكمال السورس

تاريخ التنفيذ: 2026-10-06.

اكتملت تعديلات المرحلة في Backend وتطبيق العميل/الويب والفرع والفني. التحقق محصور في الصياغة والتنسيق والتحليل والبناء، وفق طلب المستخدم. لم تُشغّل تطبيقات أو خوادم أو اختبارات تشغيلية أو migrations أو خدمات خارجية.

## ما تم تنفيذه

- المواعيد: اختيار Slots من Backend للحجوزات العادية والمتكررة والعروض، مع Loading/Error/Empty/Retry. الحساب يستخدم مدة الخدمة، المهارات، الورديات، الإجازات، الحجوزات، travel buffer، سعة الفرع والفريق. تُراجع إتاحة الخدمة والوقت مجدداً داخل transaction مع قفل الفرع عند التأكيد.
- التتبع: خريطة العميل أثناء `on_the_way` تعرض العميل والفني وETA وآخر تحديث. يتوقف طلب الموقع عند فقدان صلاحية الحجز أو نهاية الحالة، وتتوقف التحديثات عند غياب الشاشة/توقف التطبيق. تُعرض stale location بوضوح، وفشل التتبع لا يمنع عرض تفاصيل الحجز.
- الفني: رفع الموقع خلال الحالات النشطة فقط، مع التحقق من الإسناد الحالي، ومنع الطلبات المتداخلة ومعالجة رفض الإذن وتعطيل الموقع وtimeout. بقيت أدلة Check-in/Check-out والصور وOTP ضمن مسار التنفيذ الموجود.
- الحالات والفريق: إضافة `assigned` بمعنى مستقل، وتوحيد الانتقالات المسموحة من Backend. إسناد جميع أعضاء الفريق وإظهار أدوارهم وحالاتهم؛ إجراءات القيادة/الإكمال تبقى خاضعة لصلاحيات Backend. دعم الزيارات المتكررة دون الاعتماد على `serviceman_id` وحده.
- المالية: عرض حالات الخدمة والدفع والفاتورة منفصلة، وتصحيح عرض المبلغ المدفوع. الفاتورة المنشورة ورابط PDF المؤقت يأتيان من NIRAB Backend وتكامل Odoo الموجود؛ تظهر Pending عند عدم وصولها بعد.
- العروض: عرض الصلاحية والحالة والشروط والحجز المحوّل، ومنع قبول المنتهي. التحويل محمي بالأقفال وإعادة الطلب لا ينشئ حجزاً آخر. يحمل الدفع رقم العرض المختار وسعره بعد خصم المعاينة، ويستخدم التحقق الرقمي الموجود، بما في ذلك الجزء المدفوع من المحفظة عند الدفع الرقمي الجزئي.
- الترجمة: تحديث العربية والإنجليزية في التطبيقات الثلاثة مع استخدام localization الحالي.

## الملفات

القائمة الكاملة لكل ملف معدل/جديد في `phase09_verification/changed-files.json`.

| المشروع | ملفات معدلة | ملفات جديدة |
|---|---:|---:|
| Admin Panel | 24 | 1 |
| User app and web | 27 | 3 |
| Provider app | 10 | 1 |
| Service man app | 9 | 0 |

الملفات الجديدة في السورس:

- `Admin Panel/Modules/BookingModule/Services/BookingWorkflowService.php`
- `User app and web/lib/feature/booking/widget/booking_workflow_panel.dart`
- `User app and web/lib/feature/checkout/widget/order_details_section/available_slot_picker.dart`
- `User app and web/lib/feature/checkout/widget/order_details_section/available_repeat_time_picker.dart`
- `Provider app/lib/feature/booking_details/widget/booking_workflow_panel.dart`

أضيف هذا التقرير وملفات نتائج التحقق في Docs. لم تُضف migrations أو حزم أو credentials أو routes جديدة، ولم تُعدّل إعدادات قاعدة البيانات؛ بقيت MySQL 8 + Spatial.

## عقود API المعدلة

- `GET /api/v1/customer/booking/availability/slots`: يعيد `slots` فعلية ووقت البداية/النهاية والفرع وtimezone؛ يقبل `quotation_id` و`branch_selection`. عند إرسال `starts_at` يعيد تحقق الوقت المطلوب.
- `GET /api/v1/customer/booking/dispatch-tracking/{booking_id}`: يدعم `booking_repeat_id` المملوك لنفس الحجز ويعيد حالة التتبع وstale وموقع العميل والفني وETA.
- تفاصيل الحجز العادي/الزيارة للعميل والفرع والفني: إضافة `workflow` بحالات الخدمة/الدفع/الفاتورة والانتقالات والفريق وoverride. بيانات الفريق العامة لا تحتوي أرقام اتصال.
- تأكيد الطلب وقبول العرض والدفع: إعادة فحص الموعد والإتاحة، ودعم `quotation_id` لتثبيت العرض المطلوب و`branch_selection` للحجز العادي. تفاصيل طلب العرض تعيد `quotation_payable_amount` من Backend ليتطابق إجمالي الواجهة مع سعر التحويل.
- قبول الإسناد وتحديث الموقع والانتقالات: استخدام الـroutes الموجودة مع تشديد الحالات والصلاحيات. الفواتير تستخدم invoice API ورابط PDF الموقع الموجودين.

## سلوك Legacy المعزول

- واجهات اختيار الوقت الحر/ASAP استُبدلت بمنتقي Slots في المسارات المعدلة.
- أزيل تحديث التتبع غير المقيد من شاشة تفاصيل العميل، وأزيل كشف موقع الفني من التتبع غير المصادق بالهاتف.
- قوائم تحميل الفاتورة القديمة في تطبيق العميل والفرع استُبدلت بعرض الفاتورة المحاسبية المنشورة عبر Backend.
- في Branch Mode، قبول عرض والدفع له يمران عبر مسار Quotation المحمي؛ يبقى المسار القديم خارج Branch Mode. لم تُحذف جداول Legacy.
- لا توجد Google Routes جديدة؛ بقي `StraightLineEtaProvider` وواجهته الحالية.

## نتائج التحقق

- `php -l`: نجح على 25 ملف PHP جديداً/معدلاً؛ وأعيد على الملفات التي تلقت تعديلات الإنهاء. السجل: `phase09_verification/php-syntax-final.log`.
- `composer validate --no-check-publish`: exit 0؛ تحذيرات موجودة عن بعض قيود الإصدارات `*`. السجل: `phase09_verification/composer-validate.log`.
- تنسيق Dart: نجح `dart format --output=none --set-exit-if-changed` على ملفات Dart الـ44 المعدلة/الجديدة: exit 0 و0 ملفات تحتاج إعادة تنسيق. السجل: `phase09_verification/dart-format-check.log`.
- تحليل تطبيق العميل: exit 0 باستخدام `--no-pub --no-fatal-infos`؛ لا Errors أو Warnings، وبقيت 19 ملاحظة Info في السورس القائم. السجل: `phase09_verification/flutter-analyze-customer-final.log`.
- بناء Customer Web: نجح release build بـ `flutter build web --no-pub --no-web-resources-cdn --no-wasm-dry-run`، exit 0؛ المخرجات في `User app and web/build/web`. السجل: `phase09_verification/flutter-build-web-customer.log`.
- `git diff --check`: نجح في المستودعات الأربعة. ملفات JSON العربية/الإنجليزية الستة قابلة للتحليل بنجاح.
- تطبيقَا الفرع والفني: نجح parsing/formatting لملفات Dart المعدلة. لا يوجد `.dart_tool/package_config.json` فيهما، لذلك لم يمكن إجراء analyze/build موثوق دون تهيئة dependencies. لم تُثبّت حزم أو تُجهّز بيئة جديدة. لم يُبنَ iOS على Windows.

## المطلوب لاحقاً عند النشر

- تهيئة حزم تطبيقَي الفرع والفني في بيئة البناء المعتمدة واستكمال analyze/build لهما قبل إعلان Code Freeze شاملاً لكل المنصات.
- التأكد من تفعيل Branch Mode وExecution وفق `config/nirab.php` و`config/nirab_execution.php`، وتوفر schema المراحل السابقة على MySQL 8.
- ضبط بيانات دوام الفروع وسعتها، مهارات وورديات وإجازات الفنيين، عضوية الفريق وقائده، مدة الخدمة وعدد الفنيين وإتاحتها للفروع. تُستخدم إعدادات `config/nirab_dispatch.php` الحالية للتأخير وtravel buffer وحداثة الموقع وETA.
- توحيد timezone المستخدمة في بيانات الدوام والحجوزات؛ الـAPI يعيد timezone والمنتقي يحافظ على وقت الفرع. إعداد Laravel الحالي UTC.
- توفير إعدادات Google Maps الحالية للمنصات المستهدفة وأذونات الموقع؛ لا يلزم API جديد لـRoutes.
- إعداد تكامل Odoo والـqueue/outbox والتخزين الخاص وHTTPS/APP_URL وفق إعدادات المشروع الحالية، ليصل مرجع الفاتورة وPDF وتعمل الروابط المؤقتة. إعداد بوابة الدفع يبقى ضمن التكامل السابق.
- تحقق Staging من السباقات، حالات الفريق، التتبع والأذونات، الدفع والفواتير مطلوب قبل النشر؛ لم يُنفذ ضمن هذه المهمة استجابةً لقيد عدم تشغيل البيئة/الاختبارات الثقيلة.

بعد استكمال بوابة البناء وStaging، تُحصر التغييرات اللاحقة في Bug Fix وفق الخطة.
