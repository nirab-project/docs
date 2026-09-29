# تقرير مزودي SMS و OTP المناسبين للسعودية لمشروع NIRAB

**التاريخ:** 30 سبتمبر 2026  
**المشروع:** NIRAB  
**الغرض:** اختيار مزود SMS/OTP مناسب للسوق السعودي، مع التركيز على انخفاض التكلفة، وجود تجربة مجانية أولية، سهولة تكامل API، وإمكانية تسجيل Sender ID باسم `NIRAB`.

---

## 1. الخلاصة التنفيذية

أفضل الخيارات المقترحة لمشروع NIRAB هي:

1. **Taqnyat (تقنيات)** — الخيار الأفضل للإنتاج داخل السعودية بسبب السعر المحلي المنخفض وسهولة الربط.
2. **Msegat (مسجات)** — بديل سعودي قوي ومناسب كـ Fallback Provider.
3. **Infobip** — ممتاز لاختبار دورة OTP كاملة قبل الإطلاق، ويقدم تجربة مجانية جيدة.
4. **Unifonic** — مزود قوي ومناسب للشركات الأكبر ويدعم OTP بشكل مخصص.
5. **Twilio** — سهل جدًا تقنيًا لكنه مرتفع التكلفة داخل السعودية.
6. **Plivo** — يدعم السعودية، لكن تكلفته أعلى من المزودين المحليين.

### التوصية العملية

- **للاختبار الآن:** `Taqnyat + Infobip`
- **للإنتاج:** `Taqnyat`
- **كمزود احتياطي:** `Msegat`
- **عدم الاعتماد على Twilio أو Plivo للإنتاج المحلي** إلا عند الحاجة لمسارات دولية أو كحل احتياطي خاص.

---

## 2. مقارنة سريعة

| المزود | تجربة مجانية | يدعم السعودية | OTP / API | تكلفة الإنتاج | الاستخدام المقترح |
|---|---:|---:|---:|---:|---|
| Taqnyat | نعم — حوالي 7 رسائل تجريبية | نعم | نعم | منخفضة جدًا | المزود الأساسي |
| Msegat | نعم — حوالي 10 رسائل | نعم | نعم | منخفضة | مزود احتياطي |
| Infobip | نعم — تجربة لمدة 60 يومًا | نعم | نعم — 2FA/OTP | متوسطة | أفضل للاختبار |
| Unifonic | نعم | نعم | نعم — Authenticate | متوسطة/مرتفعة | شركات أكبر |
| Twilio | نعم | نعم | نعم — Verify | مرتفعة جدًا | اختبار أو احتياطي دولي |
| Plivo | نعم / Credits | نعم | نعم | مرتفعة نسبيًا | ليس الخيار الأول |

---

# 3. Taqnyat — تقنيات

## لماذا هو الأفضل لـ NIRAB؟

Taqnyat مزود سعودي مناسب جدًا للرسائل المحلية، ويدعم API لإرسال SMS ويمكن دمجه مباشرة مع Laravel.

الميزة الأهم هي انخفاض تكلفة الرسائل مقارنة بمزودي SMS العالميين.

## التجربة الأولية

يوفر الحساب الجديد رسائل تجريبية محدودة، ويمكن الاختبار باستخدام اسم مرسل تجريبي مثل:

```text
Taqnyat.sa
```

يمكن استخدام هذه المرحلة لاختبار:

```text
Laravel Backend
      ↓
Taqnyat API
      ↓
SMS
      ↓
رقم جوال سعودي
```

## الأسعار التقريبية المنشورة

| عدد الرسائل | السعر التقريبي | التكلفة لكل رسالة |
|---:|---:|---:|
| 5,000 | 529 ريال | 0.106 ريال |
| 10,000 | 920 ريال | 0.092 ريال |
| 25,000 | 2,185 ريال | 0.087 ريال |
| 50,000 | 4,025 ريال | 0.081 ريال |
| 100,000 | 6,325 ريال | 0.063 ريال |

> الأسعار والعروض قد تتغير، لذلك يجب التحقق منها وقت التعاقد.

## Sender ID باسم NIRAB

عند الانتقال إلى الإنتاج، يفضّل تسجيل:

```text
NIRAB
```

كاسم المرسل الرسمي.

قد يحتاج ذلك إلى:

- سجل تجاري.
- عقد الخدمة.
- تفويض Sender ID.
- تصديق من الغرفة التجارية.
- إثبات ملكية اسم `NIRAB` عند الحاجة.
- دفع رسوم سنوية لتسجيل اسم المرسل.

## خطوات التسجيل

1. إنشاء حساب في بوابة Taqnyat.
2. تفعيل البريد الإلكتروني.
3. تفعيل رقم الهاتف.
4. إدخال بيانات المنشأة.
5. الدخول إلى لوحة التحكم.
6. استخدام الرصيد/الرسائل التجريبية.
7. اختبار SMS API.
8. التوجه إلى إدارة Sender ID.
9. تقديم طلب `NIRAB`.
10. رفع مستندات الشركة والتفويض.
11. دفع رسوم التسجيل المطلوبة.
12. بعد الاعتماد، استخدام `NIRAB` في رسائل OTP.

## API

Base URL المستخدم في وثائق Taqnyat:

```text
https://api.taqnyat.sa/
```

### المصادر

- https://portal.taqnyat.sa/
- https://portal.taqnyat.sa/technical_explanations/en/technical_explanations/subscribe_and_login/
- https://taqnyat.sa/ar/offers/packages/
- https://dev.taqnyat.sa/

---

# 4. Msegat — مسجات

Msegat من المزودين السعوديين المعروفين ويدعم:

- Verification SMS.
- OTP.
- Notifications.
- REST API.
- Delivery Reports.
- الشبكات السعودية.

## التجربة المجانية

يوفر للمستخدم الجديد عددًا محدودًا من الرسائل المجانية للاختبار، يصل حسب صفحة الأسئلة الشائعة إلى نحو:

```text
10 SMS
```

## الأسعار التقريبية المنشورة

| عدد الرسائل | السعر | التكلفة التقريبية لكل رسالة |
|---:|---:|---:|
| 5,000 | 699 ريال | 0.140 ريال |
| 10,000 | 1,049 ريال | 0.105 ريال |
| 35,000 | 3,299 ريال | 0.094 ريال |
| 100,000 | 6,999 ريال | 0.070 ريال |
| 200,000 | 13,500 ريال | 0.0675 ريال |

## Premium OTP

لدى Msegat خيارات مخصصة أو محسنة لرسائل التحقق OTP يمكن أن تعطي أولوية أعلى في التسليم.

قد يتم احتساب هذه الرسائل بسعر أو نقاط أعلى من SMS العادي.

## متطلبات التسجيل

للشركات داخل السعودية قد يطلب:

- سجل تجاري سعودي صالح.
- بيانات المنشأة.
- تفويض Sender ID.
- تصديق الغرفة التجارية.
- دفع رسوم اسم المرسل.

## خطوات التسجيل

1. إنشاء حساب Msegat.
2. إدخال بيانات الشركة.
3. رفع السجل التجاري.
4. انتظار مراجعة الحساب.
5. استخدام الرسائل المجانية للاختبار.
6. الدخول إلى Sender Names.
7. طلب اسم `NIRAB`.
8. تنزيل نموذج التفويض.
9. توقيعه وختمه.
10. تصديقه من الغرفة التجارية.
11. رفع التفويض.
12. دفع رسوم Sender ID.
13. إنشاء API Key.
14. إضافة بيانات API إلى NIRAB Backend.

### المصادر

- https://www.msegat.com/
- https://www.msegat.com/faqs/
- https://landing.msegat.com/en/sms/
- https://landing.msegat.com/en/faqs/

---

# 5. Infobip

Infobip خيار ممتاز في مرحلة التطوير والاختبار لأنه يقدم نظام 2FA / OTP متكامل، وليس فقط إرسال SMS عادي.

## التجربة المجانية

يقدم:

```text
Free Trial
```

بمدة قد تصل إلى:

```text
60 يومًا
```

ولا تتطلب التجربة الأولية بطاقة ائتمانية في العادة.

يمكن استخدام حساب Trial لاختبار إرسال SMS إلى أرقام موثقة داخل الحساب.

## دعم OTP

يوجد لديهم منتج مخصص لـ:

```text
2FA with SMS
```

وهذا يسمح بتنفيذ:

```text
Send OTP
Verify OTP
Wrong OTP
Expired OTP
Resend OTP
```

## التدفق المقترح

```text
NIRAB
   ↓
Infobip 2FA API
   ↓
إرسال OTP
   ↓
العميل يدخل الكود
   ↓
NIRAB Backend
   ↓
Infobip Verify
   ↓
Valid / Invalid
```

## خطوات التسجيل

1. إنشاء Free Trial.
2. تفعيل البريد.
3. إنشاء كلمة المرور.
4. إدخال بيانات المنشأة.
5. توثيق رقم الهاتف.
6. الدخول إلى Developer Portal.
7. اختيار SMS.
8. اختيار 2FA / Authentication.
9. إنشاء API Key.
10. إرسال OTP إلى رقم الاختبار.
11. اختبار Verify.
12. بعد الانتقال للإنتاج، تسجيل Sender ID سعودي.

### المصادر

- https://www.infobip.com/signup
- https://www.infobip.com/docs/essentials/getting-started/create-an-account
- https://www.infobip.com/docs/essentials/getting-started/free-trial
- https://www.infobip.com/docs/sms/get-started

---

# 6. Unifonic

Unifonic مزود قوي في المنطقة ويدعم السوق السعودي.

لديه منتج مخصص:

```text
Unifonic Authenticate
```

ويدعم:

- SMS OTP.
- WhatsApp OTP.
- Voice OTP.
- إنشاء رمز التحقق.
- التحقق من الرمز.
- تحديد مدة الصلاحية.
- تحديد عدد المحاولات.

## API

التدفق الأساسي:

```text
/start
```

لإرسال OTP.

ثم:

```text
/check
```

للتحقق من الرمز.

## Trial

توجد إمكانية Trial لاختبار رسائل SMS داخل السعودية.

أثناء الاختبار قد يكون الإرسال محدودًا بالأرقام المرتبطة بالحساب ورسائل تجريبية محددة.

## متى نستخدمه؟

مناسب عندما يصبح NIRAB أكبر ويحتاج إلى:

- Multi-channel Authentication.
- WhatsApp OTP.
- Voice OTP.
- Enterprise Support.

### المصادر

- https://www.unifonic.com/ar/products/authenticate
- https://docs.unifonic.com/articles/api-documentation/verifications
- https://docs.unifonic.com/articles/products-documentation/sms-service-trials/a/sms-service-trials-process
- https://www.unifonic.com/ar/pricing

---

# 7. Twilio

Twilio ممتاز من الناحية التقنية وسهل جدًا للمطورين، لكنه مكلف داخل السعودية.

## السعودية مدعومة

Twilio يدعم SMS في السعودية رسميًا.

## التكلفة

السعر المنشور للرسائل إلى السعودية قد يصل تقريبًا إلى:

```text
$0.1949 / SMS
```

أي ما يعادل تقريبًا:

```text
0.73 ريال لكل SMS
```

بحسب سعر الصرف.

وعند استخدام:

```text
Twilio Verify
```

قد توجد رسوم إضافية لكل عملية Verification.

## المشكلة

مقارنة بالمزودين المحليين:

```text
Taqnyat ≈ 0.06 – 0.10 ريال
Twilio ≈ 0.73 ريال أو أكثر
```

لذلك الفرق كبير جدًا عند زيادة المستخدمين.

## Sender ID

Twilio يوضح أن السعودية تتطلب تسجيل Sender ID مسبقًا.

### المصادر

- https://www.twilio.com/en-us/sms/pricing/sa
- https://www.twilio.com/en-us/verify/pricing
- https://www.twilio.com/en-us/guidelines/sa/sms

---

# 8. Plivo

Plivo يدعم السعودية أيضًا، لكنه ليس الأرخص.

## الأسعار التقريبية

بحسب شبكة الاتصال، السعر قد يكون في حدود:

```text
$0.19 – $0.27
```

لكل SMS.

وهذا يجعله أغلى بكثير من Taqnyat وMsegat للاستخدام المحلي المكثف.

## الاستخدام المقترح

يمكن الاحتفاظ به كخيار دولي أو احتياطي، وليس المزود الأساسي داخل السعودية.

### المصادر

- https://www.plivo.com/sms/coverage/sa/
- https://www.plivo.com/sms/pricing/sa/

---

# 9. متطلبات السعودية المهمة

عند تشغيل رسائل OTP بشكل تجاري داخل السعودية، يجب الانتباه إلى متطلبات تسجيل اسم المرسل.

اسم المرسل المقترح:

```text
NIRAB
```

يجب أن يتم تسجيله واعتماده لدى المزود والجهات ذات العلاقة قبل الاستخدام التجاري الكامل.

قد تُرفض أو تُحجب الرسائل التي تستخدم Sender ID غير معتمد أو غير مسجل.

لذلك يجب التفريق بين:

```text
Development / Trial
```

و:

```text
Production
```

---

# 10. التصميم البرمجي المقترح في NIRAB

لا يفضّل ربط المشروع مباشرة بمزود واحد بشكل صلب.

الأفضل إنشاء طبقة عامة:

```text
OtpProviderInterface
        │
        ├── TaqnyatOtpProvider
        ├── MsegatOtpProvider
        ├── InfobipOtpProvider
        └── UnifonicOtpProvider
```

## لوحة التحكم

يضاف قسم مثل:

```text
OTP Settings

Provider:
[ Taqnyat ▼ ]

API Key:
*************

Sender ID:
NIRAB

OTP Length:
6

OTP Expiry:
3 minutes

Resend Cooldown:
60 seconds

Max Attempts:
5
```

## النتيجة

يمكن تغيير المزود من لوحة الإدارة:

```text
Taqnyat
```

إلى:

```text
Msegat
```

بدون تعديل تطبيق العميل أو Provider App أو Serviceman App.

---

# 11. مكان حفظ مفاتيح SMS

يجب ألا توضع API Keys داخل:

- Customer Android App.
- Provider Android App.
- Serviceman Android App.
- JavaScript Frontend.

بل يجب أن تبقى فقط داخل:

```text
Laravel Backend
```

أو في:

```text
.env
```

أو نظام Secrets Manager.

مثال:

```env
OTP_PROVIDER=taqnyat

TAQNYAT_API_KEY=
TAQNYAT_SENDER=NIRAB

MSEGAT_API_KEY=
MSEGAT_SENDER=NIRAB

INFOBIP_API_KEY=
INFOBIP_BASE_URL=
```

---

# 12. إعدادات الأمان المقترحة للـ OTP

ينبغي تطبيق:

```text
OTP Length:           6 digits
OTP Validity:         2–5 minutes
Resend Cooldown:      30–60 seconds
Max Attempts:         5
```

بالإضافة إلى:

- Rate Limit لكل رقم هاتف.
- Rate Limit لكل IP.
- منع إرسال OTP متكرر بسرعة.
- تخزين OTP بشكل Hashed.
- انتهاء صلاحية تلقائي.
- سجل كامل لمحاولات الإرسال.
- سجل Delivery Status.
- منع Enumeration لأرقام المستخدمين.
- عدم إظهار ما إذا كان الرقم مسجلاً أو غير مسجل بطريقة تكشف قاعدة المستخدمين.

---

# 13. الخطة المقترحة للتنفيذ

## المرحلة الأولى — الاختبار المجاني

إنشاء حسابين:

```text
Taqnyat
Infobip
```

ثم اختبار:

```text
Send OTP
Verify OTP
Wrong OTP
Expired OTP
Resend OTP
Rate Limit
Delivery Failure
```

---

## المرحلة الثانية — Production

اعتماد:

```text
Primary Provider:
Taqnyat
```

ثم:

```text
Fallback Provider:
Msegat
```

---

## المرحلة الثالثة — Failover

يمكن برمجة النظام بحيث:

```text
Send OTP
   ↓
Taqnyat
   ↓
Failed?
   ↓ Yes
Msegat
```

ويجب وضع قواعد تمنع الإرسال المكرر للمستخدم إذا كان المزود الأول قد أرسل الرسالة بالفعل لكن Delivery Report تأخر.

---

# 14. القرار المقترح لمشروع NIRAB

## المزود الأساسي

```text
Taqnyat
```

الأسباب:

- سعودي.
- تكلفة منخفضة.
- مناسب للسوق المحلي.
- API سهل.
- Sender ID سعودي.
- مناسب للـ OTP والإشعارات.

## المزود الاحتياطي

```text
Msegat
```

الأسباب:

- سعودي.
- موثوق.
- API واضح.
- مناسب كمسار Backup.

## مزود التطوير والاختبار

```text
Infobip
```

الأسباب:

- Free Trial جيد.
- 2FA API متكامل.
- إرسال + Verify.
- مناسب لاختبار النظام قبل شراء الباقات.

---

# 15. البنية النهائية المقترحة

```text
Customer App
Provider App
Serviceman App
Customer Web
       │
       ▼
NIRAB Laravel Backend
       │
       ▼
OTP Service Layer
       │
       ├── Taqnyat      ← Primary
       │
       ├── Msegat       ← Fallback
       │
       └── Infobip      ← Testing / Optional
       │
       ▼
Saudi Telecom Networks
       │
       ▼
Customer Phone
```

---

# 16. الإجراء التالي

1. إنشاء حساب Taqnyat تجريبي.
2. إنشاء حساب Infobip تجريبي.
3. استخراج API Keys.
4. اختبار إرسال SMS يدويًا.
5. إنشاء `OtpProviderInterface` داخل Laravel.
6. إضافة Taqnyat Adapter.
7. إضافة Infobip Adapter.
8. إضافة Msegat Adapter لاحقًا.
9. ربط OTP Login / Register.
10. إضافة إعدادات OTP إلى لوحة الإدارة.
11. اختبار التطبيقات الثلاثة والويب.
12. تسجيل Sender ID باسم `NIRAB`.
13. الانتقال إلى Production.

---

## النتيجة النهائية

الخيار الأكثر ملاءمة اقتصاديًا وتقنيًا لمشروع NIRAB داخل السعودية هو:

```text
Primary:   Taqnyat
Fallback:  Msegat
Testing:   Infobip
```

مع بناء نظام OTP مستقل عن المزود حتى يمكن تغيير شركة SMS مستقبلًا من لوحة الإدارة دون إعادة برمجة المشروع أو التطبيقات.
