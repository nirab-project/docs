# تتبع المتطلبات والتنفيذ والقبول

كل متطلب أدناه في النطاق الإلزامي. الحالات يدوية/آلية تكتب مع الكود، والتنفيذ في PH-12 فقط. الحالة الحالية لجميعها: PLANNED.

| ID | المتطلب | المرحلة | المواصفة | حالات القبول |
|---|---|---|---|---|
| R01 | فصل الكود/الاختبار/VPS ومنع النشر | PH-00,PH-11,PH-12 | [01-AGENT-EXECUTION-CONTRACT](01-AGENT-EXECUTION-CONTRACT.md) | M-001, M-002 |
| R02 | حفظ التهيئة والأسرار والعلاقات الداخلية | PH-00,PH-11 | [S10-RESET-PRESERVATION-SEEDS](specs/S10-RESET-PRESERVATION-SEEDS.md) | M-001, M-003, M-100 |
| R03 | مدينة معتمدة وفرع واحد وGIS حقيقي | PH-01,PH-04 | [S05-CITY-SCHEDULING-ENTITLEMENTS](specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md) | M-003, M-004, M-005, M-006, M-007, M-008 |
| R04 | محتوى غني واستوديو خدمة بلا كود مخصص | PH-02,PH-08,PH-09 | [S02-SERVICE-STUDIO-FORMS](specs/S02-SERVICE-STUDIO-FORMS.md) | M-009, M-010, M-011, M-020 |
| R05 | نماذج شرطية ومتداخلة ومتكررة | PH-02,PH-08,PH-09 | [S02-SERVICE-STUDIO-FORMS](specs/S02-SERVICE-STUDIO-FORMS.md) | M-011, M-012, M-013, M-014, M-015 |
| R06 | وسائط ومرفقات خاصة آمنة | PH-02,PH-09,PH-10 | [S02-SERVICE-STUDIO-FORMS](specs/S02-SERVICE-STUDIO-FORMS.md) | M-016, M-017 |
| R07 | نسخ وسجلات ثابتة للحجوزات الجديدة | PH-01,PH-02 | [S01-DOMAIN-DATA-STATES](specs/S01-DOMAIN-DATA-STATES.md) | M-018, M-019 |
| R08 | محرك أسعار واحد بوحدات نقدية صحيحة | PH-03 | [S03-PRICING-OFFERS-POLICIES](specs/S03-PRICING-OFFERS-POLICIES.md) | M-021, M-022, M-031 |
| R09 | خصومات/ضريبة/رسوم/تخصيص واسترداد صحيح | PH-03,PH-06 | [S03-PRICING-OFFERS-POLICIES](specs/S03-PRICING-OFFERS-POLICIES.md) | M-023, M-024, M-025, M-032, M-060 |
| R10 | باقات مركبة وكمية وإضافات فعلية | PH-02,PH-03 | [S03-PRICING-OFFERS-POLICIES](specs/S03-PRICING-OFFERS-POLICIES.md) | M-026, M-027, M-028 |
| R11 | تمييز السعر المجهول والمجاني والمعاينة | PH-03,PH-07,PH-09 | [S03-PRICING-OFFERS-POLICIES](specs/S03-PRICING-OFFERS-POLICIES.md) | M-029, M-030 |
| R12 | سلة متعددة الخدمات والمدن بأقسام واضحة | PH-05,PH-09 | [S04-CART-CHECKOUT-ORDERS](specs/S04-CART-CHECKOUT-ORDERS.md) | M-033, M-034, M-035 |
| R13 | Subset وباي ناو وجدولة مجموعات منفصلة | PH-05,PH-09 | [S04-CART-CHECKOUT-ORDERS](specs/S04-CART-CHECKOUT-ORDERS.md) | M-034, M-036, M-037 |
| R14 | هوية الضيف ودمج السلة دون انتحال | PH-01,PH-05,PH-09 | [S04-CART-CHECKOUT-ORDERS](specs/S04-CART-CHECKOUT-ORDERS.md) | M-038, M-039 |
| R15 | تأكيد ذري وidempotency ومنع التكرار | PH-01,PH-05 | [S04-CART-CHECKOUT-ORDERS](specs/S04-CART-CHECKOUT-ORDERS.md) | M-040, M-041, M-044 |
| R16 | Preview/hash/TTL وإعادة الموافقة | PH-03,PH-05 | [S04-CART-CHECKOUT-ORDERS](specs/S04-CART-CHECKOUT-ORDERS.md) | M-042, M-043 |
| R17 | نوافذ الوصول والمدد والتوافق والتوقيت | PH-04,PH-09 | [S05-CITY-SCHEDULING-ENTITLEMENTS](specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md) | M-045, M-051, M-056 |
| R18 | السعة والفرق والholds والسباقات | PH-04,PH-05 | [S05-CITY-SCHEDULING-ENTITLEMENTS](specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md) | M-006, M-007, M-044, M-046, M-047, M-048, M-054, M-055 |
| R19 | إعادة جدولة ذرية وإلغاء مستقل | PH-04,PH-05 | [S05-CITY-SCHEDULING-ENTITLEMENTS](specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md) | M-049, M-050 |
| R20 | تكرار محدود وحجز مستقل لكل زيارة | PH-04,PH-06 | [S05-CITY-SCHEDULING-ENTITLEMENTS](specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md) | M-052, M-053 |
| R21 | الدفع والمحفظة والتحويل والنقد والاسترداد | PH-05,PH-06 | [S07-FINANCE-INTEGRATIONS-SECURITY](specs/S07-FINANCE-INTEGRATIONS-SECURITY.md) | M-055, M-076, M-085, M-087, M-088, M-089, M-090, M-091, M-092, M-103 |
| R22 | طلبات أسعار ومعاينات واضحة | PH-07,PH-08,PH-09 | [S06-QUOTES-FULFILLMENT](specs/S06-QUOTES-FULFILLMENT.md) | M-057, M-058, M-059, M-060, M-064 |
| R23 | إصدارات عروض وقبول/رفض/انتهاء | PH-07 | [S06-QUOTES-FULFILLMENT](specs/S06-QUOTES-FULFILLMENT.md) | M-061, M-062 |
| R24 | زيادات نطاق بموافقة العميل | PH-07,PH-10 | [S06-QUOTES-FULFILLMENT](specs/S06-QUOTES-FULFILLMENT.md) | M-063 |
| R25 | باقات زيارات ورصيد وصلاحية واسترداد | PH-06,PH-09 | [S05-CITY-SCHEDULING-ENTITLEMENTS](specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md) | M-065, M-066, M-067, M-068, M-069, M-076 |
| R26 | عضوية وتجديد مالي منفصل عن الجدولة | PH-06,PH-09 | [S05-CITY-SCHEDULING-ENTITLEMENTS](specs/S05-CITY-SCHEDULING-ENTITLEMENTS.md) | M-070, M-071, M-072 |
| R27 | غسيل ملابس واستلام ومعالجة وتسليم | PH-07,PH-09,PH-10 | [S06-QUOTES-FULFILLMENT](specs/S06-QUOTES-FULFILLMENT.md) | M-073, M-074, M-075 |
| R28 | أصول العميل والقوائم التابعة للسيارة | PH-02,PH-07,PH-09 | [S06-QUOTES-FULFILLMENT](specs/S06-QUOTES-FULFILLMENT.md) | M-077 |
| R29 | العميل والويب والهوية وRTL والدفع العائد | PH-09 | [S08-ADMIN-CUSTOMER-APPS](specs/S08-ADMIN-CUSTOMER-APPS.md) | M-020, M-078, M-079, M-086 |
| R30 | لوحة الفرع وتطبيقه وتطبيق الفني | PH-08,PH-10 | [S09-PROVIDER-SERVICEMAN](specs/S09-PROVIDER-SERVICEMAN.md) | M-080, M-081, M-082, M-083, M-084, M-085, M-086 |
| R31 | الصلاحيات والخصوصية وحماية الأسرار | PH-01..PH-11 | [S07-FINANCE-INTEGRATIONS-SECURITY](specs/S07-FINANCE-INTEGRATIONS-SECURITY.md) | M-017, M-031, M-039, M-064, M-080, M-097 |
| R32 | تقارير مالية وتشغيلية وتصدير دون ازدواج | PH-06,PH-08,PH-11 | [S07-FINANCE-INTEGRATIONS-SECURITY](specs/S07-FINANCE-INTEGRATIONS-SECURITY.md) | M-093, M-094 |
| R33 | Odoo والإشعارات/outbox وتكاملات محفوظة | PH-11 | [S07-FINANCE-INTEGRATIONS-SECURITY](specs/S07-FINANCE-INTEGRATIONS-SECURITY.md) | M-095, M-096 |
| R34 | تصفير آمن وجرد وخطة واستعادة | PH-11,PH-12 | [S10-RESET-PRESERVATION-SEEDS](specs/S10-RESET-PRESERVATION-SEEDS.md) | M-098, M-099, M-100, M-101, M-102, M-103, M-106 |
| R35 | Seeders مرجعية ومحلية منفصلة وآمنة | PH-11,PH-12 | [S10-RESET-PRESERVATION-SEEDS](specs/S10-RESET-PRESERVATION-SEEDS.md) | M-104, M-105 |
| R36 | تسع خدمات مرجعية تعمل من البداية للنهاية | PH-02..PH-11 | [S06-QUOTES-FULFILLMENT](specs/S06-QUOTES-FULFILLMENT.md) | M-107, M-108, M-109, M-110, M-111, M-112, M-113, M-114, M-115 |
| R37 | إغلاق المسارات القديمة وcache/client gates | PH-01,PH-09,PH-11 | [S08-ADMIN-CUSTOMER-APPS](specs/S08-ADMIN-CUSTOMER-APPS.md) | M-116, M-117 |
| R38 | اختبارات محلية وأدلة وبناء وتسليم يدوي | PH-12 | [PH-12-LOCAL-FULL-VALIDATION](local-validation/PH-12-LOCAL-FULL-VALIDATION.md) | M-118, M-119 |

## تغطية الأسطح
Backend + Admin Blade + Provider Blade + Customer Flutter Web/Android + Provider Flutter Android + Serviceman Flutter Android. iOS والأجهزة/المزودات غير المتاحة لها أدلة معلقة لا اعتماد وهمي. لا يسقط scope تطبيق بسبب نجاح backend فقط.

## أي متطلب جديد أثناء التنفيذ
يضاف ID جديد ومواصفة ومرحلة واختبار وقرار أثر قبل CODE-READY. تعديل صياغة داخلية لا يسقط متطلباً مالياً أو أمنياً.
