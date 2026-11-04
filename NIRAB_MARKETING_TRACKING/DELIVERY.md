# تسليم MKT-01 إلى MKT-08

التاريخ: 2026-11-05 — نُفذت المراحل الثماني محلياً في Backend وتطبيق العميل، وأُعد تقرير مستقل لكل مرحلة. هذا تسليم للكود والإعدادات والوثائق، وليس اعتماد تشغيل داخل حسابات الإعلانات.

## حالة المراحل

| المرحلة | ما اكتمل محلياً | التقرير |
|---|---|---|
| MKT-01 | عقد الأحداث، المصادر، سياسة المال والباقات والبيانات المسموح بها | MKT-01-EVENT-CONTRACT.md |
| MKT-02 | فئات الموافقة، الرفض والسحب، Consent Mode v2، إيصال يراجعه الخادم وربط جلسة/حساب | MKT-02-CONSENT-MATRIX.md |
| MKT-03 | مسار Web واحد عبر GTM، أحداث الخدمات والبنرات والسلة والـcheckout، إسناد آمن | MKT-03-WEB-EVENT-MAP.md |
| MKT-04 | تحويلات بعد commit، إيصالات مالية، Outbox مستقل، dedup/retry/retention ومراقبة | MKT-04-FINANCIAL-IDEMPOTENCY.md |
| MKT-05 | إدارة IDs حسب البيئة والنطاق، RBAC وتدقيق وفصل أسرار ENV | MKT-05-CONFIGURATION-GUIDE.md |
| MKT-06 | Google/Meta Web وحاوية GTM محلية وخطوات إعداد الحسابات | MKT-06-GOOGLE-META-SETUP.md |
| MKT-07 | TikTok/Snap/X Web، عقود الموصلات، ومنع أي إرسال غير مكتمل/غير مصرح | MKT-07-PLATFORM-CONTRACTS.md |
| MKT-08 | Firebase/Meta/TikTok Native بموافقة وATT ومعرّفات بناء، وإغلاق نطاق AdSense الاختياري | MKT-08-MOBILE-ADSENSE-CLOSURE.md |

## السلوك الناتج

- لا تحميل GTM أو بكسلات تسويق قبل موافقة صالحة ومعرّف مفعّل. جميع flags معطّلة افتراضياً. إيصال staging لا يسمح بإرسال production.
- لا تكرار GA4 بين GTM وFirebase Web. البيانات المصدّرة محددة الحقول، وروابط الصفحات والمُحيل مصفّاة. يحتفظ الخادم بإسناد الحملات مشفراً ويعيد حقول UTM الآمنة فقط للقياس عند السماح.
- لا add_to_cart عند فشل API أو إعادة إرسال الكمية نفسها. السعر وفرق الكمية من استجابة الخادم، وتتبع الإضافات الناجحة منفصل إذا فشلت إضافة لاحقة في batch.
- لا purchase لمجرد إنشاء حجز نقدي أو فتح success. يشترط تحصيلاً موثقاً، مع استبعاد تمويل المحفظة واستهلاك الباقات والضمان. تُحسب الباقة مرة عند شرائها، والحجز المتكرر مرة على مستوى المجموع.
- سحب الموافقة يوقف القياس المحلي ويُلغي الرسائل المعلقة بعد حفظ السحب. فشل الشبكة يظهر للمستخدم ويُبقي الجهاز في حالة منع، مع إعادة المحاولة عند العودة. لا يمكن سحب إشارة وصلت سابقاً إلى مزود خارجي.
- عُزل التسويق عن Odoo وعن نجاح الحجز/الدفع. لم تُضف SDKs إعلانية إلى Provider أو Serviceman. لا تغطي أداة الخصوصية زر إكمال الحجز؛ إعادة فتحها في قائمة الحساب على Native وزر مستقل على Web.

## التحقق الفعلي المنفذ

| الفحص | النتيجة |
|---|---|
| PHP lint | 22 ملف PHP/Blade متغيراً أو جديداً؛ ناجح |
| ترجمة Blade وفحص PHP الناتج | 4 قوالب؛ ناجح دون تشغيل Laravel |
| Dart format | الملفات المتغيرة؛ ناجح |
| Dart analyze المحدود | الملفات المتغيرة وملفّات analytics؛ No issues found |
| JavaScript + GTM JSON + ملفات المنصات | JS syntax، 6 وسوم GTM، 3 ملفات XML؛ ناجح |
| verify-web.cjs | 25 تحقق موافقة وخصوصية وschema وdedup وبوابات مزودين؛ دون شبكة |
| verify-finance.php | 12 تحققاً لسياسة التحصيل والتوزيع المالي؛ دون خدمات |
| verify-pipeline.php | 23 تحققاً للإيصالات والتشفير والبيئة وOutbox والسحب والتكرار؛ SQLite داخل الذاكرة وموصل وهمي فقط |
| git diff --check | ناجح في مستودعي Backend والعميل |

المجموع: **60 تحققاً خفيفاً**، إضافة إلى فحوص الصياغة والتحليل. اختبار SQLite أنشأ وحذف fixture داخل الذاكرة فقط؛ لم يقرأ .env ولم يلمس قاعدة المشروع. لم يُشغَّل Docker أو محاكي أو E2E أو بناء Flutter/Gradle أو بيئة Laravel كاملة، ولم تُنفَّذ migration على قاعدة المشروع أو الإنتاج، ولم يُنفَّذ دفع أو إرسال حدث إعلاني حقيقي.

هذه الفحوص لا تثبت صحة الاستقبال/الإسناد داخل المنصات أو كامل مسارات الدفع الحية. وصول GA4/Google Ads للأحداث المالية يعتمد على قراءة واجهة العميل للحدث الموثق عند فتحها/عودتها؛ لم يُضف Google Measurement Protocol أو Google Ads Offline API خادمي مباشر. حالات القبول اليدوي لكل مرحلة موثقة في تقريرها.

## متطلبات التفعيل الخارجية المتبقية

1. مراجعة سياسة الموافقة، وتوفير IDs الحقيقية وحسابات النشر وأسرار الخادم. المعرّفات النموذجية في الاختبارات محلية ولا تتصل بأي حساب.
2. تطبيق migration التسويق بعد النسخ الاحتياطي والمراجعة، وتجهيز queue غير sync وcache مشترك والأداة المجدولة. توجد تعليمات rollback؛ لم تُنفذ هذه الخطوات.
3. استيراد ومراجعة GTM-NIRAB-WEB.json في staging ثم Tag Assistant/DebugView/Test Events ونشر الحاوية بقرار المالك. ملف الاستيراد لم يُختبر داخل حساب GTM فعلي.
4. Snap CAPI ينتظر binding معتمداً لـSnapMatchingData لأن ScCid وحده لا يحقق متطلبات المطابقة؛ لن يُرسل طلب ناقص. X CAPI ينتظر صلاحيات Ads/Developer والعقد الرسمي. خرائط X Web تحتاج event IDs المولدة في الحساب.
5. إعداد Android/Xcode/Firebase/TikTok/Meta ومراجعة الشبكة وATT وSDKs على أجهزة فعلية. مصدر Customer iOS موجود في هذا المشروع خلافاً لحدود ZIP في تقرير التدقيق، لكنه لم يُبنَ أو يُعتمد حياً.
6. AdSense اختياري وغير مركّب/مفعّل: ينتظر قرار المواقع والمواضع وموافقة الحساب وسجل ads.txt الصحيح. لا يوجد AdMob أو AdSense داخل Native.

## الملفات المتغيرة وسبب كل تعديل

ملف FILES.json يحتوي قائمة الملفات وبصمة SHA-256 لكل ملف تنفيذ. أُضيفت تقارير MKT الثمانية وملفات التحقق وحاوية GTM إلى هذا المجلد.

| الملف | السبب |
|---|---|
| `Admin Panel/.env.example` | مفاتيح البيئة معطّلة افتراضياً ومن دون أسرار. |
| `Admin Panel/Modules/AdminModule/Resources/views/layouts/partials/_aside.blade.php` | رابط إدارة التسويق للمشرف الرئيسي. |
| `Admin Panel/Modules/CartModule/Http/Controllers/Api/V1/Customer/CartController.php` | إرجاع فرق الكمية الحقيقي بعد نجاح الحفظ. |
| `Admin Panel/app/Console/Kernel.php` | جدولة الإرسال والاستدراك والتنظيف. |
| `Admin Panel/app/Http/Controllers/MarketingIntegrationsController.php` | صلاحيات المشرف، تحقق IDs، تدقيق التغييرات وإعادة المحاولة. |
| `Admin Panel/app/Http/Controllers/MarketingTrackingController.php` | إعدادات عامة آمنة وإيصالات موافقة وقراءة/إقرار الأحداث الموثقة. |
| `Admin Panel/app/Jobs/DeliverMarketingEvent.php` | قفل، فحص موافقة، حالات تسليم وإعادة محاولة محدودة. |
| `Admin Panel/app/Models/MarketingConsent.php` | موافقة حسب الغرض/البيئة/السياسة مع إسناد مشفّر. |
| `Admin Panel/app/Observers/MarketingSourceObserver.php` | مراقبة مصادر القياس بعد نجاح commit. |
| `Admin Panel/app/Providers/AppServiceProvider.php` | تسجيل المسارات ومراقبي المصادر بعد المعاملة. |
| `Admin Panel/app/Services/Marketing/ConsentService.php` | إيصال عشوائي مجزأ، سحب الموافقة ومسح الإسناد. |
| `Admin Panel/app/Services/Marketing/ConversionRecorder.php` | تحويلات من إيصالات مالية موثوقة ومنع تكرار دلالي. |
| `Admin Panel/app/Services/Marketing/FinancialEventPolicy.php` | سياسة اكتمال التحصيل وتوزيع مبلغ الإيصال. |
| `Admin Panel/app/Services/Marketing/MarketingSettings.php` | إرجاع flags وIDs العامة فقط حسب النطاق والبيئة. |
| `Admin Panel/app/Services/Marketing/PlatformConnector.php` | موصلات Meta/TikTok/Snap اختيارية بحواجز الموافقة وبيئة الاختبار. |
| `Admin Panel/app/Services/Marketing/SnapMatchingData.php` | عقد امتداد مطابقات Snap دون جمع افتراضي لبيانات العملاء. |
| `Admin Panel/config/marketing.php` | إعدادات محمية ومسارات تسويق؛ لا أسرار في الاستجابة. |
| `Admin Panel/database/migrations/2026_11_05_000001_create_marketing_tracking_tables.php` | جداول التسويق المستقلة وفهارسها ومسار rollback؛ لم تطبق على قاعدة المشروع. |
| `Admin Panel/public/assets/marketing/tracking.js` | جسر Web مشترك، موافقة وخرائط أحداث ومزودين عبر GTM. |
| `Admin Panel/resources/views/layouts/landing/app.blade.php` | جسر الموافقة في Landing وروابط الانتقال الموثوقة. |
| `Admin Panel/resources/views/marketing/integrations.blade.php` | واجهة الإعدادات ومراقبة الحالات دون عرض payload/PII. |
| `Admin Panel/resources/views/welcome.blade.php` | تمييز روابط انتقال العميل لنقل إيصال الموافقة. |
| `Admin Panel/routes/console.php` | أوامر Outbox والاستدراك والتنظيف دون تشغيلها. |
| `Admin Panel/routes/marketing.php` | إعدادات محمية ومسارات تسويق؛ لا أسرار في الاستجابة. |
| `User app and web/android/app/build.gradle.kts` | توليد موارد Meta من إعدادات النشر. |
| `User app and web/android/app/src/main/AndroidManifest.xml` | تعطيل الجمع التلقائي والأغراض الإعلانية افتراضياً. |
| `User app and web/android/app/src/main/res/values/string.xml` | إزالة قيم Meta التجريبية؛ تُولَّد الموارد من Gradle. |
| `User app and web/ios/Runner/Info.plist` | موافقة افتراضية مرفوضة وهوية Meta من إعدادات Xcode. |
| `User app and web/lib/api/remote/client_api.dart` | إرفاق إيصال الموافقة الحالي بطلبات العميل، بما فيها multipart. |
| `User app and web/lib/common/widgets/cookies_view.dart` | واجهة عربية/إنجليزية لتصنيفات الموافقة والرفض والتعديل. |
| `User app and web/lib/common/widgets/service_center_dialog.dart` | إزالة حدث الإضافة المفترض من الحوار. |
| `User app and web/lib/common/widgets/service_widget_vertical.dart` | انطباع مرئي واختيار خدمة دون أسماء/نصوص حرة. |
| `User app and web/lib/feature/auth/controller/facebook_login_controller.dart` | قراءة ATT واستعمال Limited Login دون طلب موافقة تسويق عند الدخول. |
| `User app and web/lib/feature/cart/controller/cart_controller.dart` | نتيجة batch واضحة، وأحداث نجاح وفرق كمية وسعر موثق. |
| `User app and web/lib/feature/checkout/controller/checkout_controller.dart` | begin_checkout بعد اعتماد لقطة السعر. |
| `User app and web/lib/feature/create_post/controller/create_post_controller.dart` | generate_lead بعد نجاح طلب عرض السعر. |
| `User app and web/lib/feature/home/controller/banner_controller.dart` | select_promotion عند اختيار البنر. |
| `User app and web/lib/feature/home/widget/banner_view.dart` | view_promotion للبنر المرئي الفعّال. |
| `User app and web/lib/feature/menu/account_screen.dart` | إعادة فتح إعدادات الخصوصية من الحساب دون تغطية زر الحجز. |
| `User app and web/lib/feature/service/controller/service_details_controller.dart` | view_item بعد نجاح أحدث استجابة للخدمة. |
| `User app and web/lib/helper/analytics/analytics_helper.dart` | موجّه Native/Web، موافقة وATT، عزل SDKs، أحداث موثقة وتحقق عند العودة/انتهاء الإيصال. |
| `User app and web/lib/helper/analytics/facebook_analytics.dart` | بوابة Meta App Events وأحداث شراء موثقة. |
| `User app and web/lib/helper/analytics/firebase_analytics.dart` | مدخل قديم متوافق يمر عبر بوابات الموافقة. |
| `User app and web/lib/helper/analytics/google_tag_manager.dart` | جسر JSON عبر DOM دون حقن JavaScript. |
| `User app and web/lib/helper/analytics/marketing_impression.dart` | قياس ظهور البطاقة مرة عند ظهور نصفها في الشاشة. |
| `User app and web/lib/helper/analytics/tiktok_analytics.dart` | تعديل محلي ضمن مراحل التسويق. |
| `User app and web/lib/main.dart` | بدء القياس بعد Firebase وواجهة التطبيق، ومراقب التنقل ولوحة الموافقة الأولى. |
| `User app and web/web/index.html` | إزالة GTM placeholder وnoscript وإضافة الجسر المحلي. |
| `User app and web/web/marketing_tracking.js` | جسر Web مشترك، موافقة وخرائط أحداث ومزودين عبر GTM. |

## إعادة الفحوص الخفيفة

من جذر المشروع:

```text
node Docs/NIRAB_MARKETING_TRACKING/verify-web.cjs
php Docs/NIRAB_MARKETING_TRACKING/verify-finance.php
php Docs/NIRAB_MARKETING_TRACKING/verify-pipeline.php
```

التعديلات موجودة في المستودعات الحالية للمراجعة، ولم تُنشر أو تُدفع إلى remote ولم تُنشأ commits تلقائية. لا توجد حاجة لتشغيل Docker لمراجعتها.

متابعة محددة من دليل المالك: نُفذت تجهيزات Flutter وF-01/F-02؛ التفاصيل ونتائج التحقق في [OWNER-ACTIVATION-CODE-CLOSURE.md](OWNER-ACTIVATION-CODE-CLOSURE.md).
