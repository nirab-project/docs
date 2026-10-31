# NIRAB Clean — تقرير إغلاق تصحيحات تدقيق انضمام الفنيين

التاريخ: **2026-10-31**. المرجع: أمر التصحيحات وتدقيق ما بعد التنفيذ المؤرخان في اليوم نفسه، مع تقرير التنفيذ الأساسي. هذا التقرير يوثق التغييرات اللاحقة على المصادر المحلية الفعلية، لا على ZIP قديمة أو الإنتاج.

## نتيجة التصحيحات

| البند | الحالة | النتيجة وحدود الدليل |
|---|---|---|
| J-01 / رابط مستندات الأدمن | Implemented | اختيار مسار admin/provider بحسب هوية المستخدم الخادمية، مع استمرار نطاق الطلب والمرفقات |
| J-02 / حفظ تعديل الفني دون إعادة الهوية | Implemented | إثبات وجود وثائق خاصة فعلية، محرر غير ملزم بإعادة رفعها، تحقق خادمي مشترك، وحماية آخر وثيقة legacy |
| J-03 / إعادة مطابقة pending | Implemented | مسار POST إداري وزر وتأكيد وصلاحية مركزية ومعاملة وقفل، إضافة دون تكرار وإغلاق معلل وحفظ القرارات وإشعار المستلمين الجدد فقط |
| J-04 / التخزين الدائم | متطلب نشر يدوي | لم تُفحص أو تُعدّل volumes أو إعدادات الإنتاج؛ يلزم تخزين خاص دائم وصور شخصية public دائمة |
| J-05 / دليل iOS والاعتمادات | Source verified / Build pending | ملفات iOS موجودة ومتتبعة في Git محلياً. صُححت قائمة التصدير المستقبلية. حزمة maps ما زالت غير محلولة في lock؛ لم يحدث build أو pub get |
| J-06 / إثبات ملكية التواصل | قرار تشغيلي مطلوب قبل الإطلاق | لم تُضف OTP أو يُفعّل مزود. أعلام verified بقيت كما هي، ولا يُدّعى أن موافقة المراجع تحقق تقني من الملكية |

**Implemented تعني أن التصحيح موجود في الشيفرة وفُحص ساكناً؛ لا تعني نجاح تشغيل حي أو جاهزية نشر نهائية.** لم تُنشأ commits أو push في هذه الجولة.

## J-01 — المسار الصحيح والحدود الخادمية

- الخدمة المشتركة تبني اسم اللوحة من `User.user_type` الفعلي فقط: [TechnicianIdentityDocuments.php:31](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianIdentityDocuments.php:31>). لا تستخدم request body أو query أو `adminContext` كإذن.
- الرابط يظهر فقط إذا كان الطلب Approved مربوطاً بنفس الفني والمستخدم، وسمح `scopedQuery` و`canReadDocuments` بفتحه: [TechnicianIdentityDocuments.php:43](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianIdentityDocuments.php:43>).
- الـpartial يستهلك الاسم الخادمي `admin.technician-applications.show` أو `provider.technician-applications.show`: [_application-documents.blade.php:5](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/Provider/Serviceman/_application-documents.blade.php:5>). لا رابط عام أو Storage::url للهوية.
- محررا الأدمن والفرع يمرران سياق الوثائق من الخادم بعد صلاحية `technicians.manage` للفرع. بقيت صلاحية `provider_update` الإدارية الموجودة مطلوبة أيضاً. لا يستطيع موظف محدود الفرع الحصول على وثائق فرع آخر.
- إذا نُقل فني لاحقاً، لا يتحول مرجع الهوية المعتمدة إلى مسار upload عام بسبب اختلاف الفرع. إثبات وجود الوثائق يجري داخلياً بناءً على رابط الفني والمستخدم؛ معرف الطلب ورابطه لا يكشفان إلا إن سمح نطاق المراجعة الأصلي بفتحه.

## J-02 — الوثائق الأصلية تكفي للحفظ

- إثبات الوثائق هو وجود 1–3 مسارات أصلية وكل ملفاتها فعلياً على disk الخاص أو local القديم، لا قيمة hidden input: [TechnicianIdentityDocuments.php:52](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianIdentityDocuments.php:52>). تعذر الوصول للتخزين يعامل كغياب إثبات ويظهر سبب واضح.
- تحقق الحفظ مشترك بين Web Admin وWeb Provider، مع حماية نفس الهوية المعتمدة في Provider API أيضاً: [TechnicianIdentityDocuments.php:59](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianIdentityDocuments.php:59>).
- شاشة التعديل تعرض الهوية المعتمدة ومرجع الوثائق، وتحذف شرط إعادة الرفع لهذه الحالة؛ يتحقق JavaScript من الإثبات الخادمي ولا يطلب proxy غير موجود: [edit.blade.php:106](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/Provider/Serviceman/edit.blade.php:106>), [edit.blade.php:350](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/Provider/Serviceman/edit.blade.php:350>).
- تُحفظ تعديلات الاسم والتواصل وكلمة المرور والصورة الشخصية عبر المسار الحالي دون رفع الهوية مجدداً عندما تكون الوثائق الأصلية متاحة. تغيير hidden flag أو رقم الطلب في المتصفح لا يثبت وجود ملفات.
- **لم يُضف استبدال للهوية المعتمدة.** رقمها ونوعها ووثائقها الأصلية للقراءة في هذا المحرر، ويتحقق الخادم من مساواة حقول الهوية للقيم المخزنة ويرفض رفع صور هوية إضافية لهذه الحالة. لا نسخ إلى User public/R2، ولا حذف للوثائق الخاصة عند حفظ الملف. استبدال الهوية لاحقاً يحتاج سياسة احتفاظ وقراراً منفصلاً. إذا أغفل طلب API حقول الهوية أثناء تعديل بيانات الملف، يحافظ الخادم على قيمها الأصلية من قاعدة البيانات؛ أما القيم المختلفة المرسلة صراحة فتُرفض.
- الفني التقليدي ذو `User.identification_image` ما زال يستخدم بياناته وصوره السابقة؛ صيغ أسماء الملفات النصية و`{image, storage}` مدعومة، مع public/S3 التاريخي وفق metadata الموجودة. لا تُنقل الملفات القديمة أو يُعاد نشر وثائق الطلب الخاصة.
- في نموذج legacy على الويب، إن لم تبق وثيقة فعلية صالحة يلزم ملف صورة صحيح؛ validator الصور الموجود يتحقق من النوع والحجم قبل التخزين. الصور الجديدة تُضاف إلى القائمة الحالية ولا تحذف الأصل. بقاء أكثر من صورتين قديمتين لا يمنع تعديل البيانات دون إضافة صور؛ حد الإضافة في محرر الويب صورتان.
- حذف صورة legacy يثبت أن الاسم تابع فعلاً للمستخدم ويقفل صف User، ويرفض حذف آخر وثيقة صالحة دون بديل موجود. لا يمكن تمرير مسار وثيقة خاصة إلى هذا الحذف: [TechnicianIdentityDocuments.php:116](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianIdentityDocuments.php:116>). حذف الملف الفيزيائي القديم يأتي بعد commit فقط، وتبقى ملفات الطلب الخاصة مستقلة.

## J-03 — إعادة مطابقة صريحة وآمنة

المسار الجديد هو **`POST /admin/technician-applications/{application_uuid}/rematch`** باسم `admin.technician-applications.rematch`، تحت admin وweb/CSRF، ومحدد المعدل بـ10/دقيقة. لا يوجد مقابل له في Provider Web أو Provider API.

- المسار الفعلي: [web.php:13](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Routes/web.php:13>). الإجراء في controller: [TechnicianApplicationController.php:78](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Http/Controllers/Web/Admin/TechnicianApplicationController.php:78>). الزر مرتبط بالمسار مع تأكيد، ومتاح فقط للطلب المعلق والمراجع المخول: [show.blade.php:42](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/admin/applications/show.blade.php:42>).
- الصلاحية للسوبر أدمن أو admin-employee النشط صاحب `technicians.manage` ونطاق `all_branches` فقط: [TechnicianApplicationService.php:35](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianApplicationService.php:35>). الموظف المحدود يظل قادراً على المراجعة المصرح بها سابقاً، لكنه لا يطلق إعادة توجيه تؤثر على فروع خارج نطاقه. مدير الفرع ممنوع من هذه العملية.
- يبدأ `DB::transaction` بقفل الطلب عبر `findScopedApplication(..., true)` ثم التحقق من pending، ويستخدم matcher الجغرافي الحالي لجميع الفروع: [TechnicianApplicationService.php:37](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianApplicationService.php:37>). لا User أو Serviceman أو إسناد أو booking capacity ينشأ هنا.
- الطلب المحسوم يرجع 409، والطلب القديم بلا نقطة يرجع 422/سبب عدم وجود موقع ويكون الزر معطلاً. لا تُستخدم نقطة افتراضية، ولا `provider_id` أو منطقة أو إحداثيات من body لاتخاذ المطابقة.
- الترشيح المفقود يضاف مرة واحدة فقط؛ الترشيح eligible الذي ما زال مؤهلاً يحدث نطاقه وأولويته دون تغيير تاريخ أول تطابق أو قرار بشري. القفل المركزي والقيد الفريد الحالي `(application_id, provider_id)` يمنعان التكرار عند التزامن.
- eligible الذي فقد أهلية الفرع أو تغطيته يصبح closed مع `closure_reason=eligibility_lost` و`closed_at`. يشمل ذلك سجلات الفروع المحذوفة ذات provider_id=null، دون إسقاط أي سجل من التاريخ. لا يتغير decided_by/decided_at/decline_reason بفعل الإغلاق الجغرافي.
- declined وclosed وapproved لا تعاد فتحها بواسطة rematch. عودة تغطية فرع أُغلق ترشيحه لا تعكس قرار الإغلاق تلقائياً. التدخل الاستثنائي المعلل الموجود للسوبر أدمن يبقى عملية موافقة مستقلة، وليس إعادة فتح آلية؛ يُحفظ رمز الإغلاق السابق في audit عند هذا التدخل.
- حالة التوجيه بعد العملية: matched عند وجود ترشيح eligible حالي؛ all_declined عندما تكون جميع الترشيحات المطابقة حالياً declined؛ unmatched عند غياب ترشيح قابل للمراجعة أو بقاء المطابقين مغلقين فقط. تنقل الواجهة للمراجعة المركزية هذه الحالة دون تغيير الحالة المركزية pending.
- يحدث zone_id المستنتج وrouting_at، ويسجل `technician.application.rematched` حالة التوجيه وأعداد الإضافة والإغلاق والأهلية فقط، دون هوية أو مرفقات أو إحداثيات دقيقة. `matched_branches_count` في الاستجابة الحالية يستبعد الترشيحات declined/closed التاريخية، مع بقاء اسم الحقل وشكل الاستجابة.
- الإشعار يستخدم الحدث الموجود `application_matched`، ويُقصر على الفروع المضافة والمستخدمين الذين لم يتلقوا أي إشعار سابق لهذا الطلب، مع dedupe القائم، ولا يحاول Push إلا عند إنشاء إشعار جديد وبعد commit: [TechnicianApplicationNotifier.php:51](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianApplicationNotifier.php:51>), [TechnicianApplicationNotifier.php:19](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianApplicationNotifier.php:19>). لا إعادة تنبيه للفروع/المستخدمين السابقين عند تكرار الضغط.
- آلية التحديث في هذه الجولة **يدوية وصريحة لكل طلب**. لا bulk ولا queue ولا scheduler جديد عند تفعيل فرع أو تعديل مضلع. تمت مراجعة recruitment ومسارات حفظ المناطق، وأضيف إرشاد ورابط إلى طابور pending داخل معالج الفرع للمراجع المركزي. هذا الاختيار يمنع تغيير ترشيحات أعداد من الطلبات دون فتحها ومراجعتها.

### Migration إضافية فقط

`2026_10_31_110000_add_technician_nomination_closure_metadata.php` تضيف إلى technician_application_branches الحقلين nullable: `closure_reason VARCHAR(32)` و`closed_at TIMESTAMP`. تبقى حالات API الأربع كما هي، ولا تنشأ حالات جديدة تتطلب تعديلاً في Flutter. لا backfill أو تحديث بيانات موجودة؛ لا تعديل لأي migration سابقة. يوثّق الإغلاق النهائي مستقبلاً أيضاً assigned_elsewhere/application_rejected مع إبقاء الإغلاقات الأقدم وأسبابها الأصلية.

## J-05 — أدلة iOS والاعتمادات بدون بناء

ملفات المصدر التالية موجودة فعلياً في working tree المحلي ومتتبعة في Git داخل Service man app. فُحص نصها فقط، ولم تُعدّل في هذه الجولة:

| الملف | دليل المصدر | SHA-256 المحلي |
|---|---|---|
| [ios/Runner/AppDelegate.swift](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/ios/Runner/AppDelegate.swift>) | import GoogleMaps وتهيئة GMSServices من إعداد NIRABMapsAPIKey فقط إذا لم يكن فارغاً/placeholder | `4caeb2b24869965ef7d3ed002471f27833b8b59bae4fc02009a152c453069a37` |
| [ios/Runner/Info.plist](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/ios/Runner/Info.plist>) | وصف إذن الموقع أثناء الاستخدام وNIRABMapsAPIKey=$(NIRAB_MAPS_API_KEY) | `86fde5da2b6e3e34eba7f1613236b311477720fb8b361bed59e141c7ec710ad4` |
| [ios/Podfile](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/ios/Podfile>) | سياق اعتماد iOS، لم يُشغّل CocoaPods | `a9de0fa495f7e294bb1f8fb7944362396ea11569db3300c173faaf2ada20cdfe` |
| [ios/Runner.xcodeproj/project.pbxproj](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/ios/Runner.xcodeproj/project.pbxproj>) | إعداد مشروع iOS المصدر، لم يُشغّل Xcode | `ee065a777a7961373f0b3d426a7b89e2fe1430314bea4c8d50a946334e57aa25` |
| [pubspec.yaml](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/pubspec.yaml>) | google_maps_flutter ^2.17.0؛ Dart ^3.10؛ Flutter >=3.38 | `1cc8b38cef327bcaf81115389a366d146a7b4cee667f9c4160527de76873452f` |
| [pubspec.lock](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/pubspec.lock>) | لا يحتوي google_maps_flutter؛ الحَل الحالي يعلن Dart >=3.11 وFlutter >=3.41 | `5000f2e22c4048de9188c118b49a5807e3da40e7eba0e7fc618dc572308a895a` |

سبب فجوة ZIP في أداة التصدير المحلية أن `$flutterPaths` كانت تشمل Android ولا تشمل iOS. عُدّلت قائمة **Service man app فقط** في Export-NirabSources.ps1 لإدراج واشتراط AppDelegate.swift وInfo.plist وPodfile وproject.pbxproj، دون pods أو مفاتيح توقيع. **لم تُشغّل أداة التصدير ولم يُنتج ZIP جديد**؛ الدليل الحالي هو الملفات المحلية أعلاه وتصحيح القائمة بالنظر. يلزم تضمينها فعلياً في حزمة التسليم التالية والتحقق من محتواها عند تصديرها لاحقاً.

لم يُحدّث pubspec.lock أو Podfile.lock يدوياً ولم يُشغّل pub get. وجود import GoogleMaps لا يثبت ربط الاعتماد أو نجاح Swift/iOS. يلزم حل الاعتمادات ومراجعة متطلبات SDK الفعلية وPods ثم التحليل والبناء في مرحلة إصدار مصرح بها.

## التحقق الذي نُفّذ فعلاً

| الفحص الساكن | النتيجة |
|---|---|
| php -l على PHP العادي الجديد/المعدل | PASS: 12 ملفاً، تشمل الخدمات والترحيل وcontrollers وmodel وroutes وlang ذات الصلة |
| git diff --check | PASS في Backend، وProvider، وServiceman، وCustomer؛ التطبيقات الثلاثة بلا تغييرات في هذه الجولة |
| JSON parsing وفحص التكرار/مفاتيح الميزة | 8 قواميس موجودة صالحة، واتساق مفاتيح العربية/الإنجليزية؛ لم يتغير أي JSON |
| قراءة وبحث ثابتان | مواضع التفويض والروابط وشرط الوثائق وحذف الصورة وإعادة المطابقة والإشعارات، ووجود ملفات iOS وغياب maps من lock |
| Dart format | لم يُشغّل في هذه الجولة؛ لا توجد تغييرات Dart تستدعيه |

**لم تُشغّل البيئة أو Laravel/Artisan أو خادم أو Docker/Compose/Dokploy أو تطبيق أو emulator أو اختبارات آلية/ثقيلة/E2E. لم تُنفّذ Migration أو build أو pub get أو Gradle أو Xcode أو Queue/Scheduler أو إرسال Push/Email/SMS.** لم تتغير إعدادات أو أسرار إنتاج، ولا خدمات العملاء والحجوزات والمدفوعات/Odoo. الشيفرة لم تُختبر بقاعدة MySQL Spatial أو rendering Blade أو تزامن حي؛ لا يدّعي هذا التقرير هذه النتائج.

## الملفات المعدلة والجديدة مع السبب

### Admin Panel

HEAD المرجعي الحالي: `e4042c4`. ملفات معدلة: 14؛ جديدة: 2.

| النوع | الملف | السبب |
|---|---|---|
| Modified | [Modules/ProviderManagement/Http/Controllers/Web/Admin/ProviderController.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ProviderManagement/Http/Controllers/Web/Admin/ProviderController.php>) | تمرير سياق الوثائق المصرح به لمحرر الأدمن، والتحقق الخادمي والحذف المحمي |
| Modified | [Modules/ServicemanModule/Entities/TechnicianApplicationBranch.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Entities/TechnicianApplicationBranch.php>) | cast لتاريخ الإغلاق الجديد |
| Modified | [Modules/ServicemanModule/Http/Controllers/Api/V1/Provider/ServicemanController.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Http/Controllers/Api/V1/Provider/ServicemanController.php>) | تطبيق حارس الهوية المعتمدة أيضاً على تحديث Provider API دون تغيير مسار النجاح |
| Modified | [Modules/ServicemanModule/Http/Controllers/Web/Admin/TechnicianApplicationController.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Http/Controllers/Web/Admin/TechnicianApplicationController.php>) | تفويض وتنفيذ POST rematch وتمرير حالة الزر |
| Modified | [Modules/ServicemanModule/Http/Controllers/Web/Provider/ServicemanController.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Http/Controllers/Web/Provider/ServicemanController.php>) | سياق التعديل وإثبات الوثائق وحماية حذف آخر وثيقة |
| Modified | [Modules/ServicemanModule/Resources/views/Provider/Serviceman/_application-documents.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/Provider/Serviceman/_application-documents.blade.php>) | رابط آمن يختار اسم route من الهوية الخادمية |
| Modified | [Modules/ServicemanModule/Resources/views/Provider/Serviceman/edit.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/Provider/Serviceman/edit.blade.php>) | إزالة إعادة رفع الهوية المعتمدة وربط JS بالإثبات الخادمي ودعم legacy ورسائل الحذف |
| Modified | [Modules/ServicemanModule/Resources/views/admin/applications/show.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/admin/applications/show.blade.php>) | زر إعادة المطابقة وتأكيده وآخر تحديث وسبب الإغلاق |
| Modified | [Modules/ServicemanModule/Routes/web.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Routes/web.php>) | مسار إداري CSRF/UUID محدود المعدل لإعادة المطابقة |
| Modified | [Modules/ServicemanModule/Services/TechnicianApplicationNotifier.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianApplicationNotifier.php>) | إشعار الفروع/المستلمين الجدد فقط ومنع إعادة محاولة Push المكررة |
| Modified | [Modules/ServicemanModule/Services/TechnicianApplicationService.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianApplicationService.php>) | معاملة rematch وسياسة التاريخ وحالة التوجيه والعداد وmetadata الإغلاق |
| Modified | [resources/lang/ar/technician_applications.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/resources/lang/ar/technician_applications.php>) | نصوص عربية للوثائق وإعادة المطابقة وأسباب الإغلاق |
| Modified | [resources/lang/en/technician_applications.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/resources/lang/en/technician_applications.php>) | المقابل الإنجليزي لكل النصوص الجديدة |
| Modified | [resources/views/branch/onboarding.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/resources/views/branch/onboarding.blade.php>) | إرشاد مركزي لإعادة المطابقة الصريحة بعد تغير الجاهزية/التغطية |
| Added | [Modules/ServicemanModule/Database/Migrations/2026_10_31_110000_add_technician_nomination_closure_metadata.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Database/Migrations/2026_10_31_110000_add_technician_nomination_closure_metadata.php>) | Migration إضافية nullable لسبب/وقت الإغلاق دون مساس بالترحيلات السابقة |
| Added | [Modules/ServicemanModule/Services/TechnicianIdentityDocuments.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianIdentityDocuments.php>) | مرجع مشترك للتفويض وإثبات الوثائق الأصلية والتحقق والحذف الآمن |

### Provider app

HEAD المرجعي الحالي: `07c0a1a`. ملفات معدلة: 0؛ جديدة: 0.

بلا تغييرات.

### Service man app

HEAD المرجعي الحالي: `d80c2a2`. ملفات معدلة: 0؛ جديدة: 0.

بلا تغييرات. تمت قراءة ملفات الاعتماد وiOS في تطبيق الفني فقط لإثبات حالتها الحالية.

### User app and web

HEAD المرجعي الحالي: `a702b2b`. ملفات معدلة: 0؛ جديدة: 0.

بلا تغييرات.

### ملفات جذر مساحة العمل (خارج Git المستودعات)

- Export-NirabSources.ps1: إضافة مصادر iOS اللازمة إلى قائمة تصدير الفني واشتراط وجودها؛ مراجعة مصدر فقط دون تشغيل.
- Docs/TECHNICIAN_JOINING_API_CONTRACT_2026-10-31.md: ملحق مسار rematch وسياسة الوثائق والعداد مع بقاء العقود العامة الحالية.
- Docs/NIRAB_TECHNICIAN_JOINING_CORRECTIONS_REPORT_2026-10-31.md: هذا التقرير، بالمراجع الفعلية والأسباب وأدلة iOS ومتطلبات الإطلاق.

## متطلبات النشر الفعلي — قائمة مستقلة، لم تنفذ الآن

1. قرار المشرف بتطبيق الترحيلات السابقة اللازمة للميزة ثم migration الجديدة 2026_10_31_110000 بالترتيب، بعد نسخة احتياطية ومراجعة MySQL 8. لا يكفي نشر PHP الذي يكتب metadata الإغلاق دون مخططها الجديد.
2. persistent private storage مشترك بين نسخ الخدمة: TECHNICIAN_APPLICATION_DISK وTECHNICIAN_DOCUMENTS_ROOT. إبقاء ملفات local القديمة بمساراتها، واستمرار storage الخاص والصور الشخصية public عبر إعادة نشر الحاوية، وأذونات ونسخ احتياطية واحتفاظ معتمدة. لا R2 عام للهوية. هذه مسؤولية المشرف وليست تغييراً نفذه الوكيل.
3. تفعيل استقبال طلبات الفنيين لكل فرع من واجهة الإدارة بصلاحية branches.publish وفق الجاهزية. بعد تغيير التفعيل أو المنطقة/المضلع: المراجع المركزي يفتح كل طلب pending متأثر ويستخدم إعادة المطابقة. العملية لا تعمل bulk أو تلقائياً.
4. للهواتف: حل الاعتمادات لاحقاً في بيئة الإصدار المصرح بها، ومراجعة lock وSDK الفعلي وPods وAndroid/iOS. لا تحديث يدوي للـlock. إعداد مفاتيح الخرائط المقيّدة والفوترة وdart-define الخاص بالخريطة عند الرغبة، ثم اختبارات الأذونات وGPS والبديل اليدوي. الخريطة لا تعد جاهزة من النصوص فقط.
5. تصدير مصدر جديد لاحقاً والتحقق من احتوائه ملفات iOS الأربعة أعلاه، مع إبقاء مفاتيح التوقيع والـpods خارج الحزمة. لم تُنتج حزمة جديدة الآن. مراجعة إعدادات Android الحالية ومفتاحه عبر NIRAB_MAPS_API_KEY وإعداد iOS المناظر دون وضع أسرار في Git.
6. يبقى Push اختيارياً ومغلقاً ما لم يقرره المشرف ويجهز outbox/worker وFCM. راقب إشعارات التطبيق وdedupe وحالات التسليم والتنقل إلى technician_application، وفشل ما بعد commit دون تكرار قرار الموافقة.
7. احسم سياسة إثبات ملكية الهاتف/البريد في J-06 قبل اعتماد الإطلاق، بناءً على مزود متاح وسياسة تشغيلية؛ لا ترفع verified إلى 1 يدوياً، ولا تعتبر مراجعة المستند تحقق اتصال تقنياً. لم تُضف خدمة اتصال أو تُفعّل قناة خارجية في هذه الجولة.

## خطة تحقق يدوي بعد النشر — غير منفذة الآن

- افتح تعديل الفني المعتمد من الأدمن المصرح ضمن الفرع، وتأكد أن رابط الوثائق يبدأ admin. افتحه من مدير الفرع فتأكد أنه يبدأ provider. موظف خارج نطاقه أو دون technicians.manage لا يرى أو ينزّل الوثائق؛ تزوير adminContext أو hidden flag لا يمنحه إذناً.
- احفظ الاسم/الهاتف/البريد/الصورة الشخصية لفني معتمد ذي 1–3 وثائق خاصة دون إعادة رفعها. جرّب وثائق local القديمة أيضاً. مع انقطاع التخزين أو فقد وثيقة أصلية يجب منع الحفظ برسالة الاستعادة، لا نشر هوية بديلة إلى public. تبديل رقم الهوية/نوعها أو رفع صور للهوية المعتمدة في هذا المحرر يرفض خادمياً دون حذف الأصل.
- جرّب فني legacy بأسماء صور نصية وبصيغة image/storage. حفظه دون رفع مع وجود وثيقة صالحة ينجح؛ فقد كل الوثائق يتطلب صورة صحيحة. محاولة حذف آخر وثيقة بلا بديل/وثائق خاصة ترفض، وحذف ملف غير تابع له أو مسار خاص يرفض دون لمس الملف.
- قدّم طلباً unmatched، ثم فعّل فرعاً يغطي نقطته، ثم استخدم rematch. يجب إضافة ترشيحه مرة واحدة وإشعار المستلمين الجدد فقط. كرر العملية أو نفذها بالتزامن: لا صفوف أو إشعارات مكررة.
- غيّر التغطية أو عطّل استقبال الفرع ثم rematch: eligible القديم يغلق مع eligibility_lost والوقت، دون نسبة رفض بشري زائف. declined وclosed السابقان لا يعاد فتحهما عند إعادة التفعيل. راجع matched/all_declined/unmatched وrouting_at والعداد الظاهر وفق السياسة الموضحة.
- جرّب rematch على approved/rejected فيرجع 409، وعلى طلب legacy بلا نقطة فيرجع 422/الزر معطل. جرّب مدير فرع وموظف إدارة محدود الفرع فتُرفض إعادة التوجيه. بيانات provider_id أو إحداثيات مزورة لا تغير الفرع أو الموقع المخزّن.
- شغّل سيناريو رفض A محلياً ثم قبول B، وموافقتين متزامنتين، وrematch متزامناً مع الموافقة. يجب أن يبقى حساب فني واحد، وتفوز المعاملة التي تقفل الطلب وتقرر أولاً، ولا تغير rematch قراراً نهائياً. تبقى المهارات/الورديات شرطاً للجاهزية التشغيلية.
- تحقق من بقاء الهوية والصورة الشخصية بعد إعادة النشر، ثم مراجعة الإشعارات والمرفقات بالتوكن/الجلسة. اجمع أدلة تشغيل MySQL Spatial وBlade وFlutter/Android/iOS وتسليم Push في مرحلة القبول المصرح بها؛ لا يعد هذا التقرير بديلاً عنها.
