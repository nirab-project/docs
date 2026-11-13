# تدقيق المصادر المرفوعة — أساس هذه الحزمة

هذه مراجعة ثابتة لمسارات الخدمات والسلة والمال والجدولة والمدن والتطبيقات ذات الصلة. ليست اختبار تشغيل، ولا ضماناً لمراجعة كل سطر، ولا وصفاً مؤكداً لما هو منشور على VPS. ينبغي مقارنة المستودع الفعلي مع هذه اللقطة في PH-00.

## الأرشيفات والبصمات
| الأرشيف | SHA-256 |
|---|---|
| `01-NIRAB-BACKEND-SOURCE.zip` | `9019c82eeb4bf53dfec3cab0d497a070a2701c7159b4ce6caec1e3a9b3e6992e` |
| `02-NIRAB-CUSTOMER-SOURCE.zip` | `8c30a91741e8eb53ba956647465cd94953201fd9fb71ee11b0599dff063f6be6` |
| `03-NIRAB-PROVIDER-SOURCE.zip` | `0c13e047ee3fc299db3b4a530e67096e00f40052bb1cbd695f7e80f32f9373b4` |
| `04-NIRAB-SERVICEMAN-SOURCE.zip` | `eb0804a67067c9b81d1bc7df840bd021296ad5efdf15fe6d94669f8ddc6ac359` |

## الإصدارات من lock المرفوع، لا توصية بآخر إصدار
| الحزمة | النسخة |
|---|---|
| `laravel/framework` | `v12.62.0` |
| `laravel/passport` | `v12.4.3` |
| `matanyadaev/laravel-eloquent-spatial` | `4.8.0` |
| `nwidart/laravel-modules` | `v12.0.5` |

لا تحدّث major dependencies في بداية المشروع. استخدم locks الحالية؛ عند مشكلة build حقيقية في PH12 عالج الحد الأدنى ووثق سبب تغيير lock. Flutter SDK المناسب يتحقق من pubspec/lock والبيئة المتاحة، ولا يُفترض من الذاكرة إصداراً آخر.

## أدلة الملفات
| المرجع | مسار داخل الأرشيف ونطاق السطور | النتيجة |
|---|---|---|
| E01 | `01-NIRAB-BACKEND-SOURCE/composer.json` : 7–42 | Laravel ^12 وPHP ^8.2 وPassport/Modules/Spatial؛ الإصدارات الدقيقة مثبتة في composer.lock. |
| E02 | `01-NIRAB-BACKEND-SOURCE/Modules/ServiceManagement/Services/PricingEngine.php` : 14–45 | المحرك الحالي يرفض non-fixed ويستدعي ServiceRequirements ويستخدم float/round. |
| E03 | `01-NIRAB-BACKEND-SOURCE/Modules/ServiceManagement/Services/PricingEngine.php` : 80–95 | tax/total بثلاث منازل وprice source؛ مطلوب adapter مالي وإعادة بناء الحساب. |
| E04 | `01-NIRAB-BACKEND-SOURCE/Modules/CartModule/Http/Controllers/Api/V1/Customer/CartController.php` : 54–67 | هوية guest في السلة ليست guest_id وحده؛ لا نضعف الحماية في V2. |
| E05 | `01-NIRAB-BACKEND-SOURCE/Modules/CartModule/Http/Controllers/Api/V1/Customer/CartController.php` : 76–121 | CityCartContext والسلة الثابتة snapshots؛ لا quote cart عادي. |
| E06 | `01-NIRAB-BACKEND-SOURCE/Modules/BookingModule/Services/BranchCheckoutService.php` : 19–30 | Preview منفصلة بصلاحية 15 دقيقة في اللقطة. |
| E07 | `01-NIRAB-BACKEND-SOURCE/Modules/BookingModule/Services/BranchCheckoutService.php` : 141–176 | Confirm داخل transaction وقفل snapshot/cart/payment والتحقق من الأموال. |
| E08 | `01-NIRAB-BACKEND-SOURCE/Modules/BookingModule/Services/CheckoutScheduleInput.php` : 11–43 | regular/repeat وقائمة dates normalized؛ هذه قاعدة انطلاق لا مواعيد لكل عنصر. |
| E09 | `01-NIRAB-BACKEND-SOURCE/Modules/ServiceManagement/Http/Controllers/Api/V1/Admin/ServiceFieldController.php` : 62–91 | fields/options وتعديل السعر/المدة/عدد الفنيين؛ schema الحالي لا منشئ nested كامل. |
| E10 | `01-NIRAB-BACKEND-SOURCE/Modules/CustomerSubscriptionModule/Http/Controllers/VisitPackageController.php` : 25–40 | visits مقيدة 4/8/12، monthly=30؛ يجب فصل عدد الزيارات عن cadence. |
| E11 | `01-NIRAB-BACKEND-SOURCE/database/migrations/2026_11_13_000001_create_city_registry.php` : 10–44 | مدن وحدود spatial وunique المدينة/الفرع؛ نحتفظ بها. |
| E12 | `01-NIRAB-BACKEND-SOURCE/database/migrations/2026_11_13_000002_add_city_geographic_registry.php` : 10–30 | مصادر جغرافية واعتمادات وraw_geojson محمية. |
| E13 | `01-NIRAB-BACKEND-SOURCE/app/Services/City/CityMode.php` : 8–28 | strict config/MySQL/READ COMMITTED guards. |
| E14 | `01-NIRAB-BACKEND-SOURCE/app/Services/City/CityBranchResolver.php` : 10–27 | الفرع يشتق من المدينة مع readiness وحدود؛ لا multi branch picker. |
| E15 | `01-NIRAB-BACKEND-SOURCE/Modules/ProviderManagement/Services/BranchCapacityPolicy.php` : 18–42 | dailyLoad يقرأ Booking/booking_repeats؛ lock على provider؛ يلزم تغيير مصدر القراءة V2. |
| E16 | `01-NIRAB-BACKEND-SOURCE/Modules/ProviderManagement/Entities/Provider.php` : 95–138 | provider owner/zone/availability/servicemen relationships محمية. |
| E17 | `01-NIRAB-BACKEND-SOURCE/Modules/UserManagement/Entities/Serviceman.php` : 24–51 | الفني في UserManagement ويرتبط بالمستخدم/الفرع/الفريق/المهارات/الورديات. |
| E18 | `01-NIRAB-BACKEND-SOURCE/Modules/PaymentModule/Entities/Setting.php` : 14–22 | إعدادات الدفع في addon_settings؛ ليست معاملات قابلة للحذف. |
| E19 | `01-NIRAB-BACKEND-SOURCE/Modules/PaymentModule/Entities/PaymentRequest.php` : 14–17 | سجل الدفع وتشفير finalization_result يعتمد على إعدادات/APP_KEY. |
| E20 | `01-NIRAB-BACKEND-SOURCE/Modules/PaymentModule/Entities/PaymentRefund.php` : 17–24 | قرارات refund immutable ولا حذف عادي؛ reset أداة محكومة منفصلة. |
| E21 | `01-NIRAB-BACKEND-SOURCE/database/seeders/DatabaseSeeder.php` : 7–17 | DatabaseSeeder فارغ في الأرشيف؛ لا يعيد الشركة وحده. |
| E22 | `01-NIRAB-BACKEND-SOURCE/app/Console/Commands/DatabaseRefresh.php` : 44–63 | Demo reset يقوم db:wipe وحذف storage؛ ليس أداة التحويل المطلوبة. |
| E23 | `02-NIRAB-CUSTOMER-SOURCE/lib/util/app_constants.dart` : 1–10 | Customer baseUrl إنتاج ثابت في المصدر. |
| E24 | `03-NIRAB-PROVIDER-SOURCE/lib/util/app_constants.dart` : 1–12 | Provider baseUrl إنتاج ثابت في المصدر. |
| E25 | `04-NIRAB-SERVICEMAN-SOURCE/lib/utils/app_constants.dart` : 1–11 | Serviceman baseUrl إنتاج ثابت؛ مسار utils مختلف. |

## حدود الأرشيف والاختبار المحلي
- Backend لا يتضمن في جذره vendor أو Dockerfile/Compose أو phpunit.xml أو tests؛ يجب تجهيز بيئة الاختبار والـbootstrap والتحقق من الموجود في المستودع الحي، لا الادعاء بأنها جاهزة. توجد migrations أصلية لجداول users/providers/services/bookings؛ لم يُنفذ استرجاعها النظيف هنا.
- Customer يتضمن android وweb، ولا يتضمن ios في هذه اللقطة. Provider يتضمن android ولا web/ios. Serviceman يتضمن android وios ولا web. لا تدّعِ اختبار منصة لم تُبنَ.
- الجداول الجغرافية تشير إلى zones؛ الإذن بحذف الخدمات القديمة ليس إذناً بحذف zones أو مصادر الحدود. العمل الحالي على المدينة واحد/فرع واحد أقوى من اقتراح عام سابق بتعدد الفروع.
- موصلات Odoo وPayment وCommunication موجودة، لكن نجاحها الخارجي لا يثبت من وجود الملفات. اختبارها المحلي يستخدم stubs، والتفعيل الحقيقي مهمة قبول منفصلة للمالك.

## ما لم نأخذه حقيقة عن نيراب
واجهات /api/v2/commerce والجداول commerce_ وService Studio هي متطلبات إنشاء جديدة. لا نسبة اكتمال من وصفها. عروض/سعات المنافس مصدر فهم UX فقط؛ لا ننقل أسعاراً أو مفاتيح أو هويات أو endpoints خارجية إلى fixtures.

## خريطة التأثير التي يجب إغلاقها
Controllers/API + Blade + Flutter repositories/models/routes + observers/hooks + jobs/scheduler + exports/PDF + analytics + FCM deep links + Odoo mappers + gateway finalization/refunds + wallet/pack/membership purchases + bootstrap/config. ابحث عن كل read/write إلى legacy services/carts/bookings/booking_repeats، ولا تعلن إلغاء legacy قبل تحويل جميع المستهلكين أو حجبهم صراحة.
