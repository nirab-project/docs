# S08 — لوحة الإدارة وتطبيق العميل والويب
## 1. لوحة الأدمن
استخدم Laravel Blade/layout/design tokens الموجودة، لا تطبيق إدارة جديد خارج المشروع. أضف navigation لاستوديو الخدمات، العروض، التسعير، الطلبات، الجدولة، عروض الأسعار، الاستحقاقات، التشغيل، المالية. لا تعرض مفاتيح التكامل في صفحات التجارة.

| الشاشة | المطلوب المتكامل |
|---|---|
| Services list | بحث/تصفية draft/published/paused، نسخ خدمة، status مستقل عن city enable، تحذير incomplete configuration |
| Service editor | tabs محتوى/وسائط/نموذج/عروض/متطلبات/سياسات/رحلة/نشر؛ autosave draft مع version conflict |
| Forms builder | إضافة مكونات وترتيبها وقوالب repeater/conditional؛ اختيار scopes والحدود؛ معاينة عربية/إنجليزية ومراجعة cycles |
| Offers editor | single/composite/quantity/visit/membership؛ روابط مكونات حقيقية وسعات ووحدات؛ extras تشغيلية لا نص فقط |
| Price rules | forms لقواعد محدودة، ترتيب/basis/min/trigger review، preview بtrace قابل للفهم، بلا محرر eval |
| City availability | المدن والفرع المحفوظان؛ تفعيل العرض وإعدادات الوقت/السعة/المهارات؛ readiness يشرح السبب بدلاً من تعديل الجغرافيا |
| Simulator | حالة synthetic مع سعر ومدة ومجموعة/موارد/خطوات؛ لا آثار مالية أو outbox |
| Orders | تفاصيل مشتريات/groups/bookings/payments/refunds، timeline audit، لا زر يغير paid دون collection |
| Calendar | فلاتر مدينة/فرع/فريق، تمييز holds/inspection/pickup/delivery؛ تحريك موعد يستدعي reschedule API |
| Quote desk | incoming→review→inspection→version→sent؛ مسار موافقة العميل، history |
| Packs/memberships | grants/remaining/reserved/consumed/expiry/renewals؛ adjustments مخولة بسبب، لا edit balance مباشر |
| Finance | bank receipt review، cash reconciliation، refund queue، exceptions، export/invoice، Odoo outbox monitor |
| Cutover status | قراءة readiness/generation فقط؛ لا زر reset أو عرض secrets/manifest الخام |

تحديث dashboard/التقارير والبحث العام وروابط البنرات وlanding للعمل مع service ids V2. روابط خدمة قديمة موقوفة تعرض unavailable/redirect مدروس لا 500؛ لا نسخ بنرات منافس. إدارة المحتوى الحالية غير التجاري تبقى كما هي. حذف خدمة قديمة لا يحذف ملفات الترويسة/شعار الفرع.

## 2. العميل Flutter: ملفات اللمس الحالية
- `lib/feature/service/model/service_model.dart` وواجهات/controllers/repositories تحت `lib/feature/service/`.
- `lib/feature/cart/controller/cart_controller.dart` وباقي cart؛ `lib/helper/checkout_helper.dart`.
- `lib/feature/checkout/controller/checkout_controller.dart`, `repo/checkout_repo.dart`, `view/checkout_screen.dart`, `model/checkout_booking_summary.dart`.
- `lib/feature/booking/`, `lib/feature/visit_package/view/visit_packages_screen.dart`, `lib/feature/subscription/`.
- `lib/feature/city/city_context.dart`, `city_picker.dart`, `city_calendar.dart`؛ حافظ قرار المدينة ولا تتجاوز التحقق لتعرض خدمة.
- `lib/util/app_constants.dart`, `lib/helper/route_helper.dart`, `lib/api/remote/client_api.dart`، الترجمات والثيم والnotifications/deeplinks.

أضف models typed تحت namespace commerce_v2 ولا تستخدم dynamic maps في كل widget. مصدر الحقيقة في السعر/السعة الخادم، ولا parsing يستبدل null بـ0 في quote-required. renderer للنموذج والخطوات لا يعتمد على اسم خدمة. إعادة استخدام GetX/النمط الحالي أولى من تغيير state management كله.

## 3. تجربة العميل المطلوبة
التصفح يبدأ بمدينة/عنوان مخدوم؛ المحتوى يمكن عرضه read-only قبل التحقق مع زر اختيار الموقع، لكن لا وعود booking بلا خدمة مؤهلة. صفحة الخدمة تعرض كل المحتوى وتسمح اختيار نوع المسار/العرض. نموذج steps يحفظ draft محلياً بختم schema/version ويعيد validation عند العودة أو تحديث الإصدار؛ بيانات مرفقات خاصة لا تحفظ برابط عام.

سلة متعددة الخدمات تعرض ready/pending quote/packs، groups حسب city/address، موعد لكل group مع خيار موعد مشترك إن أمكن، واستكمال حقول ناقصة. زر buy_now لا ينقل العناصر الأخرى معه ولا يحذفها. تكرار بند بنفس config يدمج quantity فقط إذا offer يسمح؛ اختلاف الغرفة/السيارة/العنوان يبقى بنداً مستقلاً.

Review يعرض breakdown واضحاً: subtotal/discount/tax/fees/total/due-now/due-later، سياسة الإلغاء، المدة والفترة والتوقيت، عدد الزيارات ومتى تبدأ الصلاحية. لا دفع حتى يوافق العميل على preview الحالي. ضغط مزدوج disable UI مع idempotency key لا يعتمد فقط على disable.

بعد الدفع تعرض order مركزي وbookings مستقلة. restarting app يعيد الاستعلام عن attempt/order ولا يفترض فشل الدفع. رابط gateway untrusted يعرض processing حتى server verifies. failed/expired/price-changed/city-paused لها screens واضحة تحفظ السلة. دعم wallet وcash/bank/gateway حسب eligibility المتقاطع لا حسب قائمة ثابتة بالواجهة.

My visits: رصيد/محجوز/مستهلك/متبقي، جدولة واحدة/عدة، اقتراح تكرار وقبول التعارضات، إلغاء وفق policy، تجديد واضح لا default auto charge. My quotes: المرفقات/النسخ/الصلاحية/قبول ورفض/موعد معاينة. My assets: سيارة محفوظة/إزالة/تعديل، والحجز يحتفظ snapshot السابق.

## 4. الهوية والإتاحة واللغة
احتفظ باسم نيراب والحزمة وFirebase والsigning configs. طبّق الخطوط والألوان الحالية في الواجهات الجديدة، RTL مع أرقام أسعار سليمة، لوحة مفاتيح أعداد مناسبة، labels لreaders، focus/error summaries، touch targets، loading/empty/error/retry/disabled states، عرض 360px/tablet/desktop. لا مقاطع تصميم وهمية غير قابلة للنقر.

## 5. فصل البيئات
كل التطبيقات الثلاثة لديها `baseUrl` إنتاج hard-coded باللقطة. استبدله بـAppEnvironment typed: `NIRAB_ENV=local|production`, `NIRAB_API_BASE_URL`, `NIRAB_API_CONTRACT=2`, `NIRAB_DISABLE_EXTERNAL_SERVICES` من compile-time config. local لا يقبل prod hostname مهما كانت dotenv. Android emulator uses host bridge 10.0.2.2 فقط إذا local approved؛ هاتف داخل LAN يحتاج baseUrl صريح ومحمي، لا fallback إلى الإنتاج. لا APP_KEY أو secret في Flutter.

production release ما زال يقرأ النطاق المعتمد ولكن لا ينشره الوكيل. local fake providers للخرائط/FCM/analytics/login الخارجية، مع اختبار التطبيق نفسه لا مجرد mocks لواجهة HTTP. لا تغيّر package name الحقيقي من أجل الاختبار إلا applicationIdSuffix في flavor debug إن احتاج coexistence.

## 6. إصدارات قديمة
API config يعيد min_supported_commerce_contract وgeneration. عميل contract<2 عند تفعيل V2 لا يستطيع mutate legacy commerce routes ويعرض update_required؛ auth والحصول على config لا يحجبان برسالة مبهمة. invalidate local caches/token relation safely؛ لا bootstrap V1 data في widgets V2. اختبار cache old cart على web refresh/app restart إلزامي.
