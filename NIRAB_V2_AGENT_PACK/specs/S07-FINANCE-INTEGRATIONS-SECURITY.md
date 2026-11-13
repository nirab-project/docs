# S07 — المال والتكاملات والأمان
## 1. مصدر حقيقة واحد
GatewayPaymentService/GatewayManager/RefundService/PaymentPrincipal والـPaymentSession تبقى موصلات تحت adapters V2. وسع مراجعها إلى order/items/bookings V2، ولا تستنسخ بوابة دفع أخرى. PaymentRequest/PaymentRefund هما سجل gateway الرسمي، وledger V2 سجل أثره الداخلي، وpayment_allocations تخصيص لا عملية دفع ثانية.

المحفظة الجديدة تقرأ ledger/accounts لا balances قديمة متروكة في users/accounts. أي endpoints قديمة للتعبئة/شراء العضوية إما توجّه إلى application service V2 أو تحجب تجارياً عند cutover؛ لا تكتب رصيدين. OTP/auth/config وحدها لا تحتاج تحويل عقدها بلا سبب.

## 2. قيود مالية
الخصم لا يتجاوز مستحق البند؛ refund cumulative≤verified captured−prior refunds؛ allocation sums exact؛ عمليات wallet debit تحت lock وعدم سالب. كل ledger transaction balanced، immutable، idempotency key؛ reversals لا delete. snapshots قبل/بعد في audit مع masked sensitive fields.

الدفع بعد الخدمة: booking confirmed/unpaid؛ تسجيل النقد من الفني creates cash_collection بعد authorization، لا مجرد `is_paid=1`. قبض العميل يختلف عن تسليم الفني للصندوق؛ branch remittance مستقل ومطابقة الإدارة وتاريخ التدقيق. ليس marketplace settlement ولا عمولة.

التحويل البنكي: رفع receipt private لا ينشئ paid. مخول finance يعتمد أو يرفض مع السبب ومطابقة المبلغ/العملة؛ لا يكفي رفض/قبول ملف نفسه لتوليد قبض ثانٍ. approval تحت lock/idempotency، ويحاول commit الإتاحة إذا انتهى hold؛ إن لم تتوفر لا يصنع حجزاً متعارضاً.

Partial wallet + gateway: wallet hold عند confirmation، final debit بعد success؛ الفشل/expiry يحرره. refund يعكس مصادر الدفع بحسب allocation والسياسة المعتمدة، وليس تحويل كل المبلغ للمحفظة دون قبول. لا wallet grant على redirect.

باقة زيارات: الشراء يزيد customer deferred entitlement liability داخلياً؛ completion يحرر الجزء المستحق تشغيلياً للتقرير. لا يجمع تقرير الإيراد قيمة شراء pack مع قيمة كل زيارة مرة أخرى. اعتماد طريقة القيد/الضريبة والإقرار المحاسبي النهائي في Odoo للمالك/محاسبه؛ هنا متطلبات consistency تقنية وليست إرشاداً ضريبياً.

## 3. المدفوعات المتأخرة والتصحيح
Webhook verify signature/provider retrieval والمبلغ والعملة والmerchant عند الموصل. dedupe external event ID + request hash؛ اختلاف المحتوى لنفس id يرفع تنبيه. يُسجل event قبل retries ولا يعاد external charge للتأكد. نجاح مالي بعد انتهاء holds → reacquire آمن أو resolution_required؛ refund عبر queue أو تدخل مالي مخول إذا المزود لا يدعم آلياً، مع عدم وسم refunded حتى يؤكد المزود.

timeouts تعني unknown/pending verification لا failed مؤكد. no retry charge بلا idempotency من المزود؛ استخدم reference lookup. لا إرسال mobile callback payload كمصدر للحقيقة. تحفظ expired old references في cutoff fence عند reset حتى لا تتصل ببيانات جديدة.

## 4. التكاملات
**Odoo:** حافظ على config/company/journals/accounts/tax mapping/branch analytic mappings/credentials. mapper يستقبل événements V2 typed مع minor units ويحوّل عند boundary. مفتاح external identity ثابت `nirab:v2:<generation>:<event_id>`؛ جدول mapping القديم الخاص بالإعداد لا يحذف مع invoice history. post بعد commit في outbox، retries backoff وdead-letter وmanual replay مخول. لا اختبارات إلى Odoo الحقيقي في PH12.

**Notifications:** events order confirmed، payment failed/verified، booking assigned/rescheduled/cancelled/reminder، quote sent/accepted/expired، pickup/delivery، pack low/expired/renewal failed. جمهور الحدث role+owner+branch محدد، وأحداث push/email/in-app لا تكرر بسبب webhook duplicate. deeplink يفتح order/booking/quote الصحيح في التطبيقات. delivery receipt ليس إثبات تنفيذ عمل. SMS/FCM fake محلياً؛ preserve credentials الإنتاج دون تصديرها.

**Storage:** configure disk abstraction مع local/S3-test؛ لا R2 production bucket. حساب owner/private object check قبل presigned download. reset ينظف objects حسب allowlist مثبتة ومرجع ملكية، لا recursive bucket delete.

**Maps/Identity:** حافظ Firebase IDs/package identifiers/signing configs/Google settings. local uses stub location/address and fake notifications، لا نقل مفاتيح إلى frontend جديدة. البريد SMTP وإعداد OTP provider/email تبقى كما هي؛ لا تحوّل phone OTP تلقائياً. auth existing حقيقي محلياً مع test mailbox/OTP stub مقيد بـlocal guard وليس bypass إنتاج.

**Marketing:** أحداث view/configuration/cart/checkout/purchase منفصلة؛ purchase after verified/confirmed policy—not click. consent respected؛ اختبارات تمنع إرسال tracking pixels خارجيًا.

## 5. صلاحيات
| الفاعل | مسموح | ممنوع |
|---|---|---|
| customer/verified guest | بياناته وسلته وعروضه ودفعه ومواعيده | اختراع user/branch/price/status من body |
| branch manager | موارد وحجوزات وعروض وتقارير فرعه، تعديل المواعيد وفق policy | مفاتيح تكامل/عملاء فروع أخرى/نشر كتالوج مركزي |
| technician | مهام مسندة، checklists/evidence/cash عند تخويل | تعديل الأسعار/اعتماد refund/قبول عرض نيابة عن عميل |
| central catalog admin | drafts/publish/city enable حسب policy | تنفيذ reset من UI |
| finance admin | bank approve/refunds/cash reconciliation/reports | تعديل المبالغ المدفوعة بعدياً دون قيود |
| owner/operator CLI | inventory/cutover/reset المصرح بخطته | reset بلا manifest/dry-run/حماية بيئة |

AuthZ explicit policies ولا تعتمد global scope وحده. روابط PDF/CSV/private evidence تفحص owner/scope في كل request. لا exposing authToken/headers/APP_KEY في logs أو audits. الصلاحيات الجديدة تُزرع idempotently دون إضعاف الحالية.

## 6. تقارير واختبارات الأمان
تقارير مركزية وإيراد فرع وخدمة وفترة وحجز/طلب، مع pending/collected/refunded/recognized/unearned. attribution حسب booking.branch وsnapshot لا provider في body. مرشحات وتصدير وطباعة ثنائية اللغة؛ queries لا تتضاعف totals بسبب join one-to-many. regenerate cache بعد reset، لا استيراد totals قديمة.

اختبارات IDOR، cross-branch export، forged payment، webhook replay، old callback after reset، hidden field tamper، order price injection، negative quantities، concurrency، SSRF بالمرفقات الخارجية، XSS بcontent، تجاوز file size/MIME، OTP/rate limit، disabled staff، cleanup protected data. لا يزعم الوكيل ضمان الأمان الكلي؛ يسلم نتائج الاختبارات المحددة.
