# تسلسل التنفيذ

| المرحلة | الهدف | نمط التنفيذ |
|---|---|---|
| [PH-00](PH-00-BASELINE-AND-DEPENDENCIES.md) | تثبيت اللقطة وخريطة التبعيات | كود فقط |
| [PH-01](PH-01-FOUNDATIONS-SCHEMA-CONTRACTS.md) | أساس V2 والمخطط والعقود | كود فقط |
| [PH-02](PH-02-CATALOG-FORMS-CONTENT.md) | الكتالوج والنماذج والمحتوى | كود فقط |
| [PH-03](PH-03-OFFERS-PRICING-POLICIES.md) | العروض ومحرك التسعير | كود فقط |
| [PH-04](PH-04-CITY-AVAILABILITY-SCHEDULING.md) | الإتاحة والموارد والجدولة | كود فقط |
| [PH-05](PH-05-CART-CHECKOUT-ORDERS.md) | السلة والطلبات وإتمام الحجز | كود فقط |
| [PH-06](PH-06-FINANCE-PACKS-MEMBERSHIPS.md) | المال والمحفظة والاستحقاقات | كود فقط |
| [PH-07](PH-07-QUOTATIONS-FULFILLMENT.md) | عروض الأسعار والتشغيل المتخصص | كود فقط |
| [PH-08](PH-08-ADMIN-AND-PROVIDER-WEB.md) | لوحات الإدارة والفرع على الويب | كود فقط |
| [PH-09](PH-09-CUSTOMER-FLUTTER.md) | تطبيق العميل وFlutter Web | كود فقط |
| [PH-10](PH-10-PROVIDER-SERVICEMAN-FLUTTER.md) | تطبيق مدير الفرع وتطبيق الفني | كود فقط |
| [PH-11](PH-11-INTEGRATION-RESET-SEEDS-CODE-FREEZE.md) | إغلاق التكاملات وأدوات التصفير والـSeeders | كود فقط |
| [PH-12](../local-validation/PH-12-LOCAL-FULL-VALIDATION.md) | إنشاء البيئة المحلية وكل الاختبارات والبناء | المرحلة التشغيلية النهائية المستقلة |

بعد PH-12 لا توجد مرحلة تنفيذ VPS للوكيل. يوجد تسليم دليل للمالك فقط. الإصلاحات التي تكشفها الاختبارات تتم ضمن PH-12 ثم يعاد regression؛ لا يبدأ نشر تلقائي.
