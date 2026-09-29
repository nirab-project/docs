# تقرير مواءمة Demandium مع نموذج NIRAB

## 1. نموذج NIRAB الحقيقي

NIRAB ليست Marketplace يضم مزودين مستقلين.

النموذج الصحيح هو:

```text
                         NIRAB
                    شركة واحدة فقط
                          │
             الإدارة المركزية / Admin
                          │
          ┌───────────────┼───────────────┐
          │               │               │
      فرع الرياض        فرع جدة        فرع الدمام
          │               │               │
       الفنيون          الفنيون          الفنيون
          │               │               │
        الفرق             الفرق             الفرق
```

في الكود:

```text
Provider   → Branch
Serviceman → Technician / Employee
```

مع الاحتفاظ بالبنية الحالية قدر الإمكان بدل إعادة بناء المشروع من الصفر.

---

# 2. علاقة العميل مع NIRAB والفروع

العميل يتعامل مع NIRAB كشركة واحدة، لكنه يستطيع كذلك الوصول مباشرة إلى فرع NIRAB الموجود في مدينته أو القريب منه.

يوجد مساران للحجز:

### الحجز السريع

```text
العميل
↓
يختار الخدمة
↓
يحدد موقعه
↓
النظام يحدد المدينة / Zone
↓
يختار أقرب أو أنسب فرع تلقائياً
↓
Booking
```

### الحجز المباشر من الفرع

```text
العميل
↓
فروع NIRAB
↓
يختار فرع مدينته
↓
يرى معلومات وخدمات الفرع
↓
يحجز مباشرة
```

إذن لا نخفي الفروع عن العميل.

لكن نزيل فكرة أن كل فرع شركة مستقلة تنافس بقية الفروع.

---

# 3. ما الموجود حالياً في Demandium؟

Demandium مبني أصلاً على:

```text
Marketplace
↓
Providers مستقلون
↓
Servicemen
↓
Commission أو Subscription
```

ولهذا توجد حالياً:

- Providers.
- Provider pages.
- Nearby Providers.
- Provider services.
- Provider reviews.
- Provider wallets.
- Provider earnings.
- Withdrawals.
- Commission.
- Subscription plans.
- تسويات مالية بين Admin وProvider.

بعض هذه الأجزاء مفيد لنا وبعضها يتعارض مع نموذج NIRAB.

---

# 4. ما الذي سنحتفظ به من Provider؟

لن نحذف Provider.

سنحول معناه إلى:

```text
Provider = NIRAB Branch
```

ونستفيد من:

- اسم الفرع.
- موقع الفرع.
- المدينة / Zone.
- مدير الفرع.
- الفنيين التابعين له.
- الخدمات التي يستطيع تنفيذها.
- الحجوزات.
- التقييمات.
- أوقات العمل.
- التقارير.

وفي تطبيق العميل تصبح:

```text
Nearby Providers
```

مثلاً:

```text
فروع NIRAB القريبة منك
```

بدلاً من مزودين مستقلين.

---

# 5. الخدمات

الخدمات تكون مركزية تابعة لـNIRAB.

```text
                NIRAB Services
                     │
        ┌────────────┼────────────┐
        │            │            │
      الرياض        جدة         الدمام
```

لا نحتاج إنشاء نسخة مستقلة من نفس الخدمة لكل فرع.

لكن يمكن تحديد أن كل فرع:

```text
يقدم الخدمة ✅
لا يقدم الخدمة ❌
```

وبذلك تستطيع الفروع المختلفة تقديم مجموعات مختلفة من خدمات NIRAB عند الحاجة.

---

# 6. أنواع التسعير

نحتاج نوعين رئيسيين من الخدمات:

## Fixed Price

خدمة سعرها واضح.

مثال:

```text
تنظيف مكيف
120 ريال
```

والمشروع يدعم بالفعل:

- السعر.
- Variations.
- Quantity.
- Zone pricing.

وهذا متوافق معنا.

## Quote Required

خدمة لا يمكن تحديد سعرها مسبقاً.

مثل:

- تنظيف مبنى.
- أعمال كبيرة.
- نقل.
- أعمال تحتاج معاينة.
- خدمات تعتمد على المساحة أو العدد أو المكونات.

المشروع يحتوي بالفعل على `BidModule` يمكن استخدامه كأساس.

لكن نحوله من:

```text
عدة Providers يتنافسون على العميل
```

إلى:

```text
العميل
↓
طلب عرض سعر
↓
فرع NIRAB
↓
عرض سعر رسمي
↓
العميل يقبل
↓
Booking
```

---

# 7. بيانات طلب عرض السعر

نضيف حقولاً ديناميكية لكل خدمة.

مثلاً:

```text
المساحة
عدد الغرف
عدد الأدوار
عدد الوحدات
نوع الخدمة
المكونات
الصور
المرفقات
الملاحظات
```

بحيث تختلف الأسئلة المطلوبة من خدمة إلى أخرى.

---

# 8. موقع العميل والفروع

المشروع يدعم بالفعل:

```text
GPS
Latitude / Longitude
Zones
Polygon
```

وبالتالي يستطيع تحديد مدينة أو منطقة العميل.

نستفيد من ذلك لاختيار الفروع المناسبة.

إذا كان العميل في الرياض:

```text
Customer
↓
Riyadh Zone
↓
فروع NIRAB داخل الرياض
```

---

# 9. اختيار الفرع

حالياً Demandium يستطيع إرسال الطلب لعدة Providers ليقبله أحدهم.

هذا لا يناسبنا.

نضيف:

```text
Branch Assignment Engine
```

ليختار الفرع الأنسب بناءً على:

- مدينة العميل.
- Zone.
- المسافة.
- زمن الوصول.
- توفر الخدمة.
- أوقات العمل.
- عدد الفنيين المتاحين.
- ضغط الفرع.
- الموعد المطلوب.
- قدرة الفرع التشغيلية.

والعميل يستطيع أيضاً تجاوز الاختيار التلقائي واختيار فرع معين بنفسه.

---

# 10. الفني

المشروع يدعم حالياً فنيًا واحدًا للحجز:

```text
Booking
↓
Serviceman
```

وهذا يناسب المهام البسيطة.

لكن لا يوجد Dispatch Engine كامل يقوم باختيار أقرب فني تلقائياً.

نضيف:

```text
Technician Dispatch Engine
```

ويختار:

> أقرب فني مؤهل ومتاح

وليس أقرب فني جغرافياً فقط.

يعتمد الاختيار على:

- الموقع الحالي.
- الفرع.
- المهارات.
- التخصص.
- حالة الفني.
- جدول العمل.
- الحجوزات الحالية.
- الموعد.
- زمن الوصول.

---

# 11. المهام التي تحتاج فريقاً

المشروع حالياً يعتمد أساساً على فني واحد لكل Booking.

نحتاج إضافة:

```text
execution_type

single
team
```

ثم:

```text
Booking
│
├── Team Leader
├── Technician
├── Technician
└── Technician
```

بحيث الخدمة تستطيع تحديد:

```text
default_technicians
min_technicians
max_technicians
```

ويستطيع النظام أو مدير الفرع تكوين الفريق المناسب.

---

# 12. صلاحيات الفرع والإدارة

مدير الفرع يرى ويدير:

- حجوزات فرعه.
- الفنيين.
- الفرق.
- عروض الأسعار.
- الجدول.
- الإيرادات الخاصة بالفرع.
- التقييمات.
- العمليات اليومية.

أما الإدارة المركزية:

```text
NIRAB Admin
```

فلديها رؤية وتحكم كامل في:

- كل الفروع.
- كل العملاء.
- كل الفنيين.
- كل الفرق.
- كل الخدمات.
- كل الحجوزات.
- كل المدفوعات.
- كل الإيرادات.
- كل عروض الأسعار.
- كل المناطق.
- كل التقارير.

---

# 13. النموذج المالي

هذه أهم نقطة اختلاف عن Demandium.

لا يوجد:

```text
Provider Commission
Provider Subscription
Provider Wallet
Provider Withdrawal
Provider Earnings Payable
```

لأن جميع الفروع تابعة لنفس الشركة.

مثال:

```text
Booking = 500 ريال
Branch = Riyadh
```

النتيجة:

```text
NIRAB Revenue        = 500
Riyadh Branch Revenue = 500 لأغراض التقرير
Commission            = لا يوجد
Provider Payable      = لا يوجد
Withdrawal            = لا يوجد
```

إيراد الفرع مجرد تصنيف داخلي لمعرفة أداء الفرع، وليس مبلغاً مستحقاً لشركة مستقلة.

---

# 14. الدفع

الدفع يكون لصالح NIRAB المركزية.

```text
Customer
↓
Tap / Tabby / Tamara
↓
NIRAB
```

ثم نسجل الفرع المرتبط بالحجز لأغراض التقارير والمحاسبة.

لا يوجد تقسيم مالي بين Admin وProvider.

---

# 15. إغلاق الطلب

حالياً:

- الفني يستطيع إكمال الطلب.
- Provider يستطيع إكماله.
- Admin يستطيع تغيير الحالة.
- العميل لا يقوم حالياً بوضع Completed مباشرة.
- يوجد Booking OTP.

النموذج الأفضل لـNIRAB:

```text
Assigned
↓
On The Way
↓
Ongoing
↓
Work Finished
↓
Customer Verification
↓
Completed
```

الفني أو قائد الفريق:

```text
Work Finished
```

والعميل يؤكد التنفيذ عبر:

```text
OTP
أو
زر تأكيد من التطبيق
```

ومدير الفرع أو الإدارة يستطيع Force Complete عند الحاجة مع تسجيل السبب والأدلة.

---

# 16. فصل حالة التنفيذ عن الدفع

يجب ألا يعني:

```text
Completed
```

دائماً:

```text
Paid
```

نحتفظ بحالات مستقلة:

```text
booking_status
payment_status
invoice_status
odoo_sync_status
```

خصوصاً عند وجود عملاء شركات أو دفع آجل.

---

# 17. Odoo

لا يوجد حالياً ربط جاهز مع Odoo.

لكن يمكن إضافته بشكل كامل.

النموذج:

```text
NIRAB
= النظام التشغيلي

Odoo
= النظام المالي والمحاسبي
```

NIRAB يدير:

- الخدمات.
- العملاء.
- الحجوزات.
- الفروع.
- الفنيين.
- الفرق.
- التنفيذ.

Odoo يدير:

- الفواتير.
- VAT.
- ZATCA.
- القيود المحاسبية.
- المدفوعات.
- التسويات.
- Credit Notes.
- التقارير المالية.

---

# 18. ما الذي نحتفظ به؟

نحتفظ ونطور:

```text
Customer App
Provider App → Branch Manager App
Serviceman App
Admin Panel
Services
Categories
Subcategories
Variations
Quantity
Zones
GPS
Addresses
Bookings
Scheduling
OTP
Reviews
Notifications
BidModule → Quote System
Payment Infrastructure
Firebase
Localization
```

---

# 19. ما الذي نلغي مفهومه؟

نلغي:

```text
Independent Providers
Marketplace competition
Provider Commission
Provider Subscription
Provider Wallet
Provider Withdrawals
Admin ↔ Provider Settlement
```

لكن لا نحذف Provider ككيان تقني؛ نحوله إلى Branch.

---

# 20. ما الذي نضيفه؟

```text
Branch Mode
Branch Auto Assignment
Direct Branch Booking
Branch Pages
Dynamic Service Fields
Fixed / Quote Pricing Types
Quote Workflow
Technician Skills
Technician Live Location
Dispatch Engine
Technician Availability
Team Bookings
Team Leader
Customer Completion Verification
Audit Logs
Odoo Integration
```

---

# الشكل النهائي للنظام

```text
                             NIRAB
                               │
                    Central Administration
                               │
             ┌─────────────────┼─────────────────┐
             │                 │                 │
        Riyadh Branch     Jeddah Branch     Dammam Branch
             │                 │                 │
       Technicians/Teams Technicians/Teams Technicians/Teams


                           CUSTOMER
                              │
              ┌───────────────┴───────────────┐
              │                               │
        Quick Service Booking          Browse NIRAB Branches
              │                               │
       Customer Location               Select Branch
              │                               │
       Auto Best Branch                Direct Booking
              └───────────────┬───────────────┘
                              │
                           SERVICE
                              │
             ┌────────────────┴────────────────┐
             │                                 │
        Fixed Price                       Quote Required
             │                                 │
          Booking                       Quote Request
             │                                 │
             │                           Branch Review
             │                                 │
             │                            Customer Accept
             └────────────────┬────────────────┘
                              │
                         Dispatch Engine
                              │
              ┌───────────────┴───────────────┐
              │                               │
       Single Technician                  Team Job
              │                               │
      Qualified Available              Team Leader
         Technician                     + Members
              │                               │
              └───────────────┬───────────────┘
                              │
                          Execution
                              │
                        Work Finished
                              │
                   Customer Verification
                              │
                          Completed
                              │
                    Payment / Odoo
```

## الخلاصة

Demandium يوفر أساساً جيداً جداً ولا نحتاج إلى إعادة بناء المشروع.

لكن يجب تحويل فلسفته من:

```text
Marketplace
→ Independent Providers
→ Commission / Subscription
```

إلى:

```text
NIRAB
→ Company Branches
→ Employees / Teams
→ Central Revenue
```

مع الحفاظ على ظهور الفروع للعميل وإمكانية التواصل معها والحجز منها مباشرة، وفي نفس الوقت توفير تجربة أسرع يستطيع فيها العميل طلب الخدمة فقط ويقوم النظام تلقائياً باختيار الفرع الأنسب.

**العميل يتعامل مع شركة NIRAB ومع فروعها الرسمية، بينما الإدارة المركزية تحتفظ بالرؤية والسيطرة الكاملة على الشركة بأكملها.**