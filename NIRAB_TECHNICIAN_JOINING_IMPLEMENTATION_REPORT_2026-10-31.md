# NIRAB Clean — تقرير تنفيذ انضمام الفنيين جغرافياً ومراجعة الفروع

التاريخ: 2026-10-31 — النطاق: Backend وAdmin Web وProvider Web وProvider Flutter وServiceman Flutter.

## حالة التسليم

تم تنفيذ تغييرات الشيفرة في المستودعات الثلاثة المعنية. كلمة **Implemented** في هذا التقرير تصف التنفيذ في المصدر والفحص الساكن، ولا تعني النشر أو نجاح التشغيل الحي.

| المرحلة | الحالة | المنفذ |
|---|---|---|
| A0 / عقد التكامل | Implemented | عقد الحقول والحالات والاستجابات والمسارات، مع توسعة النظام الموجود |
| A1–A3 / البيانات والمطابقة والتقديم | Implemented | Migration إضافية، ترشيحات متعددة، تخزين خاص، موقع ورضا، حجز هوية الطلب المعلق، تتبع مطهر |
| A4–A6 / القرار والصلاحيات وAPI والإشعارات | Implemented | معاملة بقفل مركزي، تفويض نطاق الفرع، 409 عند الحسم، مستندات محمية، إشعارات دائمة وPush اختياري بعد commit |
| B / لوحتا الويب | Implemented | فلاتر وقوائم وتفاصيل وقرارات ومرفقات وإشعارات ومفتاح استقبال مستقل لكل فرع |
| C / تطبيق مدير الفرع | Implemented | Repository وModel وController، ترقيم صفحات، مراجعة آمنة، مستندات JWT، قبول/اعتذار، انتقال لتهيئة الفني، توجيه إشعار مستقل |
| D / تطبيق الفني | Implemented | نموذج بخطوتين، نقطة عمل مؤكدة، خريطة اختيارية وGPS وبديل إحداثيات، خصوصية وتفضيلات وخبرة، حالات التوجيه والتتبع |

لم يتم إنشاء commits أو push. التغييرات متاحة في working trees للمراجعة. مستودع العميل **User app and web** بقي بلا تعديل.

## السلوك المنفذ

- الطلب المركزي واحد وحالته `pending/approved/rejected`. الترشيحات حالات مستقلة `eligible/declined/approved/closed`، وحالة التوجيه `matched/unmatched/all_declined` مع دعم null القديم.
- المطابقة تعتمد `GeoService::containing()` وسياسة `ST_Contains` الحالية و`Point(longitude, latitude)`، والمناطق الفعالة ومضلعات الفرع. تشمل جميع المناطق المتداخلة الفعالة، ثم تزيل تكرار الفرع وترتب أولوية النطاق ثم UUID الفرع/النطاق. لا تستخدم خدمة توزيع الحجوزات أو سعة الحجز أو `intake_enabled`.
- يستحق الفرع الترشيح عندما يكون غير محذوف ونشطاً ومعتمداً، ومديره نشطاً، ومفتاح `technician_recruitment_enabled` مفعلاً، ويغطي نطاقه الفعلي النقطة مع تطابق المنطقة. الحقول الوصفية من المتقدم لا تمنح تغطية. لم توجد سياسة حدود سعودية مستقلة في خدمة المطابقة المرجعية؛ النقطة الصحيحة غير المغطاة تحفظ للمراجعة المركزية.
- رفض مدير الرياض 1 يغلق ترشيحه فقط. قبول الرياض 2 يقفل السجل المركزي أولاً، يعيد التحقق من الأهلية، ينشئ User وServiceman واحدين، ويغلق الترشيحات الأخرى. محاولة قبول لاحقة ترجع 409. هذا استدلال من مسار الشيفرة؛ لم يجر اختبار تزامن حي.
- لا حساب أو access token أثناء الانتظار. الحساب الموافق عليه `provider-serviceman` نشط للدخول بالمسار الحالي؛ لا تمنح مهارات أو ورديات أو فريقاً تلقائياً. آلية Availability الحالية تمنع الجاهزية دون المهارات والوردية.
- الرفض النهائي للمراجع المركزي ذي النطاق العام فقط، ويحرر `pending_phone/pending_email`. الاستثناء الجغرافي للسوبر أدمن حصراً مع سبب من 10 إلى 2000 حرف وسجل audit. موظف الإدارة يرى ويوافق ضمن نطاقه فقط، ومدير الفرع لا يختار فرعاً من body.
- ملفات الهوية لا تنشر في public/R2 العام. الوصول عبر stream مصادق عليه مع `private, no-store` و`nosniff`. الطلبات القديمة تقرأ من local عند null لاسم disk. يبقى مرجع مستندات محمي في تفاصيل الفني بعد القبول؛ الفرع الذي خسر الإسناد يرى حالة مغلقة دون بيانات الهوية أو الموقع.
- الصورة الشخصية وحدها تنسخ إلى public قبل قرار قاعدة البيانات، مع cleanup عند فشل المعاملة وتسجيل metadata للصورة مرة واحدة. لا تفترض ذرية filesystem مع DB.
- يسجل audit أحداث submitted/matched/branch_declined/approved/rejected/override دون كلمات مرور أو رقم هوية أو مسارات مستندات أو إحداثيات دقيقة.
- الإشعارات داخل النظام مستقلة عن التسويق، مفلترة بالمستلم ونطاق الطلب، ذات dedupe ومقروء/غير مقروء. تفتح التفاصيل علامة القراءة. Push اختياري، يسجل في outbox بعد commit، ويستخدم `technician_application` و`application_id` بدلاً من booking، مع إعادة فحص صلاحية المستلم عند التسليم. فشل تسجيل قناة Push لا يعكس القرار؛ تبقى الإشعارات الداخلية والتتبع متاحين.

## واجهة API الفعلية

جذر واجهة الفرع: `/api/v1/provider/technician-applications` تحت `auth:api` و`actch:provider_app`، مع تفويض `technicians.manage` والتحقق من الفرع داخل الخدمة لكل إجراء.

| HTTP | المسار | الاستخدام |
|---|---|---|
| POST | `/api/v1/serviceman/auth/registration` | تقديم Multipart جديد؛ 201 |
| POST | `/api/v1/serviceman/auth/application-status` | الهاتف وكلمة المرور في body؛ 200 |
| GET | `/api/v1/serviceman/auth/application-options` | أسماء/IDs الخدمات الفعالة للتفضيلات الاختيارية، بحد 200 |
| GET | جذر واجهة الفرع | قائمة `status=pending/closed/all`, `page`, `limit` حتى 100 |
| GET | `/unread-count` تحت الجذر | عداد إشعارات المستلم |
| GET | `/notifications` تحت الجذر | إشعارات مرقمة للمستلم |
| POST | `/notifications/{notification_uuid}/read` تحت الجذر | تعليم إشعار يملكه المستخدم كمقروء |
| GET | `/{application_uuid}` تحت الجذر | التفاصيل المصرح بها وحالة ترشيح فرعه |
| GET | `/{application_uuid}/attachments/{profile|0|1|2}` تحت الجذر | صورة محمية، دون URL عام |
| POST | `/{application_uuid}/approve` تحت الجذر | إسناد لفرع الهوية الحالية، دون provider_id |
| POST | `/{application_uuid}/decline` تحت الجذر | `reason` داخلي مطلوب؛ اعتذار فرع فقط |

الحقول الجديدة المطلوبة للتقديم: `work_latitude`, `work_longitude`, `location_source=gps/map_pin/manual`, `privacy_consent=1`. الاختيارية: `work_location_label`, `city`, `district`, `experience_years` من 0 إلى 80، `preferred_service_ids[]` بحد 30، `note`. بقيت حقول الاسم والتواصل وكلمة المرور وتأكيدها والهوية وصور 1+1–3 بحد 5 MB للصورة. حُظرت حقول الإسناد والحالة والمراجع والمنطقة المرسلة من المتقدم.

استجابة التقديم والتتبع تحت `content`: `id`, `status`, `routing_status`, `matched_branches_count`, `branch_name` بعد القبول فقط، `rejection_reason` المركزي بعد الرفض فقط، `submitted_at`, `reviewed_at`. بقيت response codes `technician_application_submitted/technician_application_status`.

قائمة الفرع تحت `content` تعيد `{applications: paginator, unread_count, recruitment_enabled}`. عناصر القائمة: ID والاسم والمدينة والحالة وحالة ترشيح الفرع وتاريخ التقديم فقط. التفاصيل تضيف البيانات المصرح بها و`preferred_services` بالأسماء و`identity_images_count` و`can_approve` و`serviceman_id`. عند الإسناد لفرع آخر تبقى تفاصيل مختصرة مع `assigned_elsewhere=true`.

الأخطاء: 401 بيانات تتبع/جلسة غير صحيحة، 403 بلا صلاحية، 404 خارج النطاق، 409 سبق الحسم أو زوال الأهلية أو نقص المستندات، 422 تحقق الحقول، 429 حد المعدل. أخطاء الميزة JSON موحدة: `response_code=technician_application_error`, `message`, `content=null`, `errors`. بقي معالج الحجم الإجمالي الحالي لـ413. حدود التقديم 5/دقيقة والتتبع 10/دقيقة لكل IP ولكل هوية هاتف HMAC بصورة مستقلة، والخيارات 20/دقيقة لكل IP.

لم يضف endpoint معاينة موقع؛ كان اختيارياً في الخطة. المطابقة الحاسمة عند التقديم وعند الموافقة.

## واجهات الويب والتطبيقات

- الأدمن: `/admin/technician-applications` مع الحالة والتوجيه والمدينة والمنطقة والفرع المرشح والبحث وعدد الانتظار والترشيحات. التفاصيل تعرض النقطة للمصرح والفروع وقراراتها وأسبابها الداخلية، والقبول العادي من المؤهلين الحاليين، والاستثناء الخاص، والرفض المركزي المعلل.
- مدير الفرع: `/provider/technician-applications` مع pending/closed/all وعداد إشعارات، مرفقات محمية، قبول لفرعه فقط، واعتذار داخلي لا يرفض الطلب نهائياً. توجد روابط في القائمة ومساحة عمل الفرع، وانتقال لتفاصيل الفني بعد القبول.
- إدارة استقبال الفنيين: داخل معالج الفرع الحالي `/admin/branch-onboarding/{id}`. الإجراء `technician-recruitment` يتطلب `branches.publish` وصلاحية إدارة الفرع المركزية. المفتاح منفصل عن استقبال الحجوزات، والفرع الجديد يبدأ false. تعرض الحالة في قائمة الفروع.
- Provider Flutter: شاشة طلبات الانضمام ضمن Branch Operations وإشعارات التطبيق، مع تحديث يدوي وترقيم وتحميل/فراغ/فشل وتعطيل الإجراءات أثناء الإرسال وتأكيد القبول. 409 يعرض توضيحاً ويعيد تحميل الحالة. المستندات تحمل بالتوكن دون روابط عامة أو حفظ على القرص، وتزال صورة المستند من image cache عند إغلاقها. القبول يقود لشاشة المهارات والورديات الحالية مع اختيار الفني الجديد.
- Serviceman Flutter: بيانات أساسية ثم موقع العمل والتفضيلات. لا إرسال بلا نقطة مؤكدة أو رضا. الخريطة لا تعتمد المركز الافتراضي كنقطة. يمكن GPS أو إدخال إحداثيات وتأكيدها دون خدمة خرائط. لا طلب موقع في الخلفية. كلمة المرور لا تدخل query أو preferences، وتبقى في ذاكرة جلسة التتبع فقط وتزال عند الخروج. refresh محلي بفاصل 10 ثوانٍ إضافة إلى حدود الخادم.
- العربية والإنجليزية كاملتان للنصوص الجديدة: 53 مفتاحاً جديداً في Provider و36 في Serviceman. اللغتان bn/hi تحويان fallback إنجليزياً لهذه المفاتيح؛ لم يدّع ترجمتها إلى البنغالية أو الهندية. استخدمت الواجهات أنماط NIRAB والثيم وخط Norsal الموجود للعربية.

## التحقق المنفذ وحدوده

| الفحص | النتيجة الفعلية |
|---|---|
| `php -l` | PASS على 27 ملف PHP عادياً جديداً/معدلاً؛ دون إقلاع Laravel |
| `dart format` | نجح تحليل الصياغة والتنسيق لـ14 ملف Dart في Provider و5 في Serviceman |
| `git diff --check` | PASS في المستودعات الثلاثة بإعدادات نهايات الأسطر الأصلية |
| تدقيق JSON ساكن | 8 قواميس صالحة بلا مفاتيح مكررة، وتغطية المفاتيح الجديدة بين اللغات، ومراجع النصوص الحرفية العربية/الإنجليزية موجودة |
| قراءة وبحث المصدر | تطابق الحقول والمسارات، قيود النطاق والقفل، private attachments، فصل حالات الفرع، واشتراط المهارات/الورديات الحالي |

ظهرت إنذارات CRLF عند محاولة فحص بمعامل `core.autocrlf=false`؛ أعيد الفحص بإعدادات المستودعات الأصلية ونجح، دون تغيير إعدادات Git أو تطبيع جماعي للملفات.

**لم يتم تشغيل البيئة، ولم تُشغّل اختبارات ثقيلة، ولم تُبنَ التطبيقات، ولم تُنفذ Migrations.** كذلك لم ينفذ artisan أو Docker/Compose أو pub get أو emulator أو Queue/Scheduler أو إرسال Push/Email/SMS أو اتصال بقاعدة حية. لم يتغير مستودع العميل أو منطق الحجوزات أو الدفع أو Odoo أو مفاتيح Google/Firebase أو ملفات environment حقيقية.

هذا ليس إثبات تشغيل MySQL Spatial أو تنافس حقيقي أو تجميع Flutter/Swift/Kotlin/Blade أو تسليم Push. `dart format` لا يساوي analyze/build. ملفات اختبارات دورة الانضمام القديمة ما زالت تعتمد مخططاً/توقعات سابقة (ومنها SQLite وغياب الموقع وقبول مكرر)؛ لم تشغّل أو تعرض كدليل نجاح، وتحتاج مواءمة قبل استئناف حزمة الاختبارات الآلية لاحقاً. لا توجد سياسة تحقق اتصال جديدة أو OTP للمتقدم؛ بقيت أعلام التحقق 0 دون ادعاء تحقق هاتف/بريد.

## سجل الملفات والمستودعات

### Admin Panel

الجذر: `C:\Users\PC\Desktop\Projects\nirab-project\Admin Panel`. HEAD المرجعي: `6d0cfb4`. Commit جديد: لا. ملفات معدلة: 29، جديدة: 10.

| النوع | الملف |
|---|---|
| Modified | [Modules/AdminModule/Resources/views/layouts/partials/_aside.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/AdminModule/Resources/views/layouts/partials/_aside.blade.php>) |
| Modified | [Modules/PromotionManagement/Lib/Promotion.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/PromotionManagement/Lib/Promotion.php>) |
| Modified | [Modules/ProviderManagement/Entities/Provider.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ProviderManagement/Entities/Provider.php>) |
| Modified | [Modules/ProviderManagement/Resources/views/admin/provider/detail/serviceman-list.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ProviderManagement/Resources/views/admin/provider/detail/serviceman-list.blade.php>) |
| Modified | [Modules/ProviderManagement/Resources/views/admin/provider/index.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ProviderManagement/Resources/views/admin/provider/index.blade.php>) |
| Modified | [Modules/ProviderManagement/Resources/views/layouts/partials/_aside.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ProviderManagement/Resources/views/layouts/partials/_aside.blade.php>) |
| Modified | [Modules/SMSModule/Jobs/DeliverCommunication.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/SMSModule/Jobs/DeliverCommunication.php>) |
| Modified | [Modules/ServicemanModule/Entities/TechnicianApplication.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Entities/TechnicianApplication.php>) |
| Modified | [Modules/ServicemanModule/Http/Controllers/Api/V1/Provider/ServicemanController.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Http/Controllers/Api/V1/Provider/ServicemanController.php>) |
| Modified | [Modules/ServicemanModule/Http/Controllers/Api/V1/Serviceman/TechnicianApplicationController.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Http/Controllers/Api/V1/Serviceman/TechnicianApplicationController.php>) |
| Modified | [Modules/ServicemanModule/Http/Controllers/Web/Admin/TechnicianApplicationController.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Http/Controllers/Web/Admin/TechnicianApplicationController.php>) |
| Modified | [Modules/ServicemanModule/Resources/views/Provider/Serviceman/details.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/Provider/Serviceman/details.blade.php>) |
| Modified | [Modules/ServicemanModule/Resources/views/Provider/Serviceman/edit.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/Provider/Serviceman/edit.blade.php>) |
| Modified | [Modules/ServicemanModule/Resources/views/admin/applications/index.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/admin/applications/index.blade.php>) |
| Modified | [Modules/ServicemanModule/Resources/views/admin/applications/show.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/admin/applications/show.blade.php>) |
| Modified | [Modules/ServicemanModule/Routes/api/v1/api.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Routes/api/v1/api.php>) |
| Modified | [Modules/ServicemanModule/Routes/web.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Routes/web.php>) |
| Modified | [Modules/ServicemanModule/Services/TechnicianApplicationService.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianApplicationService.php>) |
| Modified | [app/Exceptions/Handler.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/app/Exceptions/Handler.php>) |
| Modified | [app/Http/Controllers/BranchOnboardingController.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/app/Http/Controllers/BranchOnboardingController.php>) |
| Modified | [app/Http/Middleware/ProtectPersonalData.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/app/Http/Middleware/ProtectPersonalData.php>) |
| Modified | [app/Providers/RouteServiceProvider.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/app/Providers/RouteServiceProvider.php>) |
| Modified | [bootstrap/app.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/bootstrap/app.php>) |
| Modified | [config/filesystems.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/config/filesystems.php>) |
| Modified | [public/assets/admin-module/js/nirab-onboarding.js](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/public/assets/admin-module/js/nirab-onboarding.js>) |
| Modified | [resources/lang/ar/technician_applications.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/resources/lang/ar/technician_applications.php>) |
| Modified | [resources/lang/en/technician_applications.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/resources/lang/en/technician_applications.php>) |
| Modified | [resources/views/branch/navigation.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/resources/views/branch/navigation.blade.php>) |
| Modified | [resources/views/branch/onboarding.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/resources/views/branch/onboarding.blade.php>) |
| Added | [Modules/ServicemanModule/Database/Migrations/2026_10_31_100000_add_technician_application_routing.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Database/Migrations/2026_10_31_100000_add_technician_application_routing.php>) |
| Added | [Modules/ServicemanModule/Entities/TechnicianApplicationBranch.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Entities/TechnicianApplicationBranch.php>) |
| Added | [Modules/ServicemanModule/Entities/TechnicianApplicationNotification.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Entities/TechnicianApplicationNotification.php>) |
| Added | [Modules/ServicemanModule/Http/Controllers/Api/V1/Provider/TechnicianApplicationController.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Http/Controllers/Api/V1/Provider/TechnicianApplicationController.php>) |
| Added | [Modules/ServicemanModule/Http/Controllers/Web/Provider/TechnicianApplicationController.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Http/Controllers/Web/Provider/TechnicianApplicationController.php>) |
| Added | [Modules/ServicemanModule/Resources/views/Provider/Serviceman/_application-documents.blade.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Resources/views/Provider/Serviceman/_application-documents.blade.php>) |
| Added | [Modules/ServicemanModule/Services/TechnicianApplicationExceptionRenderer.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianApplicationExceptionRenderer.php>) |
| Added | [Modules/ServicemanModule/Services/TechnicianApplicationMatcher.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianApplicationMatcher.php>) |
| Added | [Modules/ServicemanModule/Services/TechnicianApplicationNotifier.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/Modules/ServicemanModule/Services/TechnicianApplicationNotifier.php>) |
| Added | [config/technician_applications.php](<C:/Users/PC/Desktop/Projects/nirab-project/Admin Panel/config/technician_applications.php>) |

### Provider app

الجذر: `C:\Users\PC\Desktop\Projects\nirab-project\Provider app`. HEAD المرجعي: `b374747`. Commit جديد: لا. ملفات معدلة: 14، جديدة: 4.

| النوع | الملف |
|---|---|
| Modified | [assets/language/ar.json](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/assets/language/ar.json>) |
| Modified | [assets/language/bn.json](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/assets/language/bn.json>) |
| Modified | [assets/language/en.json](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/assets/language/en.json>) |
| Modified | [assets/language/hi.json](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/assets/language/hi.json>) |
| Modified | [lib/common/model/notification_body.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/common/model/notification_body.dart>) |
| Modified | [lib/feature/dashboard/model/dashboard_serviceman_model.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/dashboard/model/dashboard_serviceman_model.dart>) |
| Modified | [lib/feature/notifications/view/notification_screen.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/notifications/view/notification_screen.dart>) |
| Modified | [lib/feature/serviceman/operations/operations_api.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/serviceman/operations/operations_api.dart>) |
| Modified | [lib/feature/serviceman/view/branch_operations_screen.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/serviceman/view/branch_operations_screen.dart>) |
| Modified | [lib/feature/serviceman/view/serviceman_details.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/serviceman/view/serviceman_details.dart>) |
| Modified | [lib/feature/serviceman/view/technician_operations_screen.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/serviceman/view/technician_operations_screen.dart>) |
| Modified | [lib/feature/splash/view/splash_screen.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/splash/view/splash_screen.dart>) |
| Modified | [lib/helper/notification_helper.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/helper/notification_helper.dart>) |
| Modified | [lib/util/app_constants.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/util/app_constants.dart>) |
| Added | [lib/feature/serviceman/controller/technician_joining_controller.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/serviceman/controller/technician_joining_controller.dart>) |
| Added | [lib/feature/serviceman/model/technician_joining_request.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/serviceman/model/technician_joining_request.dart>) |
| Added | [lib/feature/serviceman/repository/technician_joining_repository.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/serviceman/repository/technician_joining_repository.dart>) |
| Added | [lib/feature/serviceman/view/technician_joining_requests_screen.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Provider app/lib/feature/serviceman/view/technician_joining_requests_screen.dart>) |

### Service man app

الجذر: `C:\Users\PC\Desktop\Projects\nirab-project\Service man app`. HEAD المرجعي: `ef522b8`. Commit جديد: لا. ملفات معدلة: 13، جديدة: 1.

| النوع | الملف |
|---|---|
| Modified | [android/app/build.gradle.kts](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/android/app/build.gradle.kts>) |
| Modified | [android/app/src/main/AndroidManifest.xml](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/android/app/src/main/AndroidManifest.xml>) |
| Modified | [assets/language/ar.json](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/assets/language/ar.json>) |
| Modified | [assets/language/bn.json](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/assets/language/bn.json>) |
| Modified | [assets/language/en.json](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/assets/language/en.json>) |
| Modified | [assets/language/hi.json](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/assets/language/hi.json>) |
| Modified | [ios/Runner/AppDelegate.swift](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/ios/Runner/AppDelegate.swift>) |
| Modified | [ios/Runner/Info.plist](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/ios/Runner/Info.plist>) |
| Modified | [lib/feature/auth/controller/technician_application_controller.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/lib/feature/auth/controller/technician_application_controller.dart>) |
| Modified | [lib/feature/auth/model/technician_application.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/lib/feature/auth/model/technician_application.dart>) |
| Modified | [lib/feature/auth/repository/technician_application_repo.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/lib/feature/auth/repository/technician_application_repo.dart>) |
| Modified | [lib/feature/auth/view/technician_application_screen.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/lib/feature/auth/view/technician_application_screen.dart>) |
| Modified | [pubspec.yaml](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/pubspec.yaml>) |
| Added | [lib/feature/auth/view/application_work_location_screen.dart](<C:/Users/PC/Desktop/Projects/nirab-project/Service man app/lib/feature/auth/view/application_work_location_screen.dart>) |

### User app and web

الجذر: `C:\Users\PC\Desktop\Projects\nirab-project\User app and web`. HEAD المرجعي: `a702b2b`. Commit جديد: لا. ملفات معدلة: 0، جديدة: 0.

بلا تغييرات.

## متطلبات النشر والمراقبة اليدوية — لم تنفذ الآن

1. راجع التغييرات وانشر Backend والتطبيقين بصورة منسقة؛ الإصدارات القديمة من نموذج التسجيل لا ترسل الموقع/الرضا المطلوبين. أبقِ تتبع الطلبات القديمة فعالاً. لا تغيّر إعداد MySQL 8 + Spatial.
2. المشرف وحده يقرر تطبيق Migration الجديدة `2026_10_31_100000_add_technician_application_routing.php` بعد النسخ الاحتياطي ومراجعة FK وترتيب migrations السابقة. لم تعدّل migration المنشورة في 2026-10-18. القيم القديمة nullable، وكل الفروع تبدأ استقبال الفنيين false.
3. ثبّت مخزن الهوية الدائم الخاص والمشترك بين نسخ الخدمة. الإعدادات الجديدة: `TECHNICIAN_APPLICATION_DISK=technician_documents` افتراضياً، و`TECHNICIAN_DOCUMENTS_ROOT` لمسار mount خاص خارج الإصدار/صورة الحاوية. يمكن إعداد disk مستقل خاص بدلاً منه. لا تختَر public أو bucket عام. احتفظ بملفات local القديمة ومساراتها خلال الانتقال وبمجلد الصور الشخصية public بصورة دائمة. لم ينشئ الوكيل volumes أو secrets أو ينسخ ملفات إنتاج.
4. من واجهة إدارة كل فرع، فعّل «استقبال طلبات انضمام الفنيين» بعد مراجعة المدير والمنطقة والمضلعات وحالة الفرع. التعطيل يوقف الترشيحات الجديدة والقبول لهذا الفرع، ويبقي الترشيحات السابقة للمراجعة. الطلبات القديمة دون نقطة تحتاج الاستثناء المركزي المعلل؛ لا تخترع إحداثيات لها.
5. dependency الجديدة لتطبيق الفني `google_maps_flutter: ^2.17.0`، وبيئة Dart أصبحت ^3.10 وFlutter >=3.38 لتوافقها. لم ينفذ pub get ولم يحدث pubspec.lock أو Podfile.lock بالتخمين. على المشرف حل الاعتمادات ومراجعة ملفات القفل والتجميع في مرحلة الإطلاق المصرح بها.
6. الخرائط مغلقة افتراضياً حتى تهيئة المفاتيح والفوترة والقيود الأصلية. لتفعيل خريطة الفني في إصدار لاحق: `NIRAB_APPLICATION_MAPS_ENABLED=true` كـdart-define، ومفتاح Android من Gradle property أو environment باسم `NIRAB_MAPS_API_KEY`، وإعداد Xcode بنفس الاسم لقيمة `NIRABMapsAPIKey` في Info.plist. لم تكتب مفاتيح فعلية. GPS/التأكيد اليدوي متاحان عند بقاء الخريطة غير مفعلة؛ اختبر الأذونات ورفضها وغياب الشبكة على الأجهزة.
7. الإشعارات الداخلية تعمل من جداول الميزة بعد الترحيل. Push يبقى اختيارياً وبلا إرسال الآن: `TECHNICIAN_APPLICATION_PUSH_ENABLED=false` افتراضياً. لتفعيله لاحقاً تحقق أيضاً من إعدادات `nirab_communications` وFCM وdispatcher/worker الحالي، ثم راقب pending/failed وlast_error. عند فشل تسجيل outbox بعد commit يبقى الإشعار الداخلي مرجعاً لإعادة المحاولة التشغيلية؛ لا تعاود قرار الموافقة.
8. تحقق يدوياً بعد النشر من مثال فرعين: عرض نفس الطلب مرة لكل فرع، اعتذار فرع دون رفض مركزي، قبول الآخر وإغلاق الأول، وموافقتين متزامنتين تنتجان حساباً واحداً و409. تحقق من 403/404 لمستخدم خارج النطاق ومنع المرفقات للفرع المغلق بعد قبول غيره.
9. تحقق من طلب بلا تغطية، حافة ST_Contains، فروع معطلة/غير معتمدة، تعطيل الاستقبال بين التقديم والقرار، طلب قديم دون موقع، ملفات قديمة/مفقودة وفشل نسخ الصورة. راقب orphan cleanup وسعة التخزين وفق سياسة احتفاظ معتمدة دون حذف أوراق حية عشوائياً.
10. تحقق من دخول الفني الموافق عليه، ظهور مرجع هويته المحمي، ثم إعداد مهاراته ووردياته وإثبات عدم جاهزيته التشغيلية قبل ذلك. راجع متطلبات تحقق ملكية الهاتف/البريد في السياسة التشغيلية إن أريد إضافة تفعيل اتصال منفصل لاحقاً؛ لا تضبط أعلام التحقق يدوياً.
11. تحقق من الإشعارات دون تكرار، وإعادة فحص الصلاحية بعد سحبها، والتنقل من Push إلى طلب فني لا إلى حجز، والتتبع العام دون تسريب أسماء المرشحين أو أسباب اعتذار الفروع أو المستندات.
