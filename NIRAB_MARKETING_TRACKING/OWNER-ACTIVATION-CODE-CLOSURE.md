# إغلاق متطلبات Flutter وF-01/F-02 — 2026-11-05

نُفذ فقط النطاق البرمجي المطلوب من دليل المالك. لم تُفعّل حسابات، ولم تُغيّر إعدادات حية، ولم تُطبّق migration أو تُشغّل queue/scheduler أو Docker أو بناء تطبيق/محاكي.

## Flutter Native

أُضيف config/marketing.native.example.json بكل أعلام الموبايل ومعرّفات Meta/TikTok العامة؛ جميع المزودين معطّلون افتراضياً. الموجّه الحالي يدعم هذه الأعلام بالفعل، ولذلك لم يُحتج تعديل Dart أو إنشاء Firebase project جديد.

أداة tool/prepare_marketing_build.py تتحقق من تطابق البيئة، وأنواع الأعلام، وتبعيتها للمفتاح الرئيسي، ومعرّفات المنصة، وتطابق Meta بين Dart وموارد النظام. ترفض الحقول غير المعروفة وحقول أسرار CAPI، ولا تطبع قيم ENV أو محتوى الملف عند الخطأ. تتلقى Native SDK Client Token من ENV فقط؛ لا تضعه في Dart defines.

Android يستعمل ENV NIRAB_FACEBOOK_APP_ID وNIRAB_FACEBOOK_CLIENT_TOKEN عبر Gradle الموجود. على iOS أُضيف Marketing.xcconfig إلى Debug وRelease (ويستعمل Profile إعداد Release الموجود). أُزيل مخطط TikTok الثابت من Info.plist ليأتي من إعداد البناء. المخرجات المحلية مستثناة من Git، ولا توجد معرفات شركة مخمّنة.

### استعمال المالك لاحقاً — لم يُشغّل build

داخل User app and web، انسخ النموذج إلى config/marketing.native.local.json وعدّل الأعلام وIDs الحقيقية. وفّر متغيرَي Meta Native في ENV عند الحاجة. يبقى الملف المحلي خارج Git. إعداد production يحتاج MKT_STAGING=false؛ staging يحتاج true.

```text
python tool/prepare_marketing_build.py --platform android --environment staging --config config/marketing.native.local.json --check-only
python tool/prepare_marketing_build.py --platform android --environment staging --config config/marketing.native.local.json
flutter build appbundle --dart-define-from-file=build/marketing/android/defines.json
```

على جهاز macOS المزود بأدوات Apple، بعد توفير إعدادات التطبيق والتوقيع وSDK IDs:

```text
python3 tool/prepare_marketing_build.py --platform ios --environment staging --config config/marketing.native.local.json
flutter build ipa --dart-define-from-file=build/marketing/ios/defines.json
```

تجهيز iOS يولّد ios/Flutter/Marketing.local.xcconfig لإعداد Meta ومخطط TikTok من نفس القيم؛ الملف مستثنى من Git. اسم native Client Token ليس رمز CAPI. لا يزال ربط Firebase القائم بالـGA4 Property وفحص Android/iPhone وATT وSDK diagnostics عملاً خارجياً يحتاج حسابات/أجهزة المالك.

## F-01: GA4 Native admin ID

تفعيل ga4 لنطاق Android/iOS لم يعد يتطلب G-ID. يمكن ترك identifier فارغاً لاستعمال Firebase platform configuration القائم، أو إدخال Firebase App ID الصحيح للمنصة للتوثيق فقط؛ يُحفظ باسم firebase_app_id. يرفض G-… للهاتف، ويرفض Firebase Android ID في نطاق iOS والعكس. يظل G-… مطلوباً عند تفعيل Web/Landing. عُدّل شرح نموذج الأدمن بالعربية والإنجليزية. لا توجد migration أو تغييرات على Firebase نفسه.

## F-02: أصل صفحة CAPI

أُضيف WebEventOrigin وإعدادات مستقلة للبيئة والنطاق. لا يُشتق الأصل من APP_URL أو من رابط يرسله العميل. تُقبل HTTPS origin فقط، بلا path خاص أو query أو fragment أو بيانات دخول. شراء العميل الموثق يستعمل customer_web، حتى إن كانت الموافقة قد بدأت على Landing. نفس الأصل يمر إلى Meta وTikTok وSnap. عند غياب إعداد صالح يُرفض الإرسال بسبب source_origin_unconfigured، وتبقى إعادة المحاولة بإشراف المالك بعد الضبط.

مفاتيح النشر الجديدة، فارغة في .env.example:

```dotenv
MARKETING_STAGING_LANDING_ORIGIN=
MARKETING_STAGING_CUSTOMER_WEB_ORIGIN=
MARKETING_PRODUCTION_LANDING_ORIGIN=
MARKETING_PRODUCTION_CUSTOMER_WEB_ORIGIN=
```

يضع المالك أصل الواجهة الفعلي، مثل https://app.nirabclean.com/ إن كان هو النطاق المعتمد، دون مسارات أو عنوان API. لم تُعدّل .env الحية أو cache الإعدادات.

## الملفات الخاصة بهذه المتابعة

- Backend: MarketingIntegrationsController.php، integrations.blade.php، config/marketing.php، PlatformConnector.php، WebEventOrigin.php، .env.example.
- العميل: .gitignore، config/marketing.native.example.json، tool/prepare_marketing_build.py، ios/Flutter/Marketing.xcconfig، Debug.xcconfig، Release.xcconfig، ios/Runner/Info.plist.
- التحقق: verify-owner-activation.php وverify-native-build.py داخل هذا المجلد.

## نتائج التحقق الخفيف

- 25 تحققاً لـF-01/F-02 باستدعاء منطق الأدمن وموصلات HTTP الوهمية، وSQLite داخل الذاكرة فقط؛ لا قاعدة مشروع أو شبكة حقيقية.
- 13 تحققاً لتجهيز Native، ونجاح check-only لـAndroid وiOS؛ دون توليد إعدادات مالك أو تنفيذ build.
- PHP lint للملفات المتغيرة، وترجمة Blade وفحص الصياغة الناتجة؛ ناجح.
- XML وسلسلة تضمين Xcode وgit diff --check؛ ناجح.

لم تتغير الحدود المقصودة في التقرير: Snap Matching/X CAPI/AdSense وGoogle server-side وتفعيل الحسابات خارج هذا الطلب. لم تُنشأ commits تلقائية أو يُنشر شيء.
