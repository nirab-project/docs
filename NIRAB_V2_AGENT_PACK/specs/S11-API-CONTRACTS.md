# S11 — العقود البرمجية وأكواد الخطأ والأحداث
## 1. القاعدة
الواجهات التالية **مطلوب إنشاؤها** وليست endpoints مثبتة من النظام الحالي. الأصل `/api/v2/commerce`. حافظ على auth/config/cities القائمة بadapter، ووثق map القديم→الجديد. OpenAPI في `contracts/commerce-v2.openapi.yaml` معيار payload/state، ويمكن للوكيل إكمال تفاصيل fields غير التجارية بعد source-delta بشرط تحديث الأمثلة والاختبارات معاً، وليس إرجاع maps غير موثقة.

Security: Passport bearer للعميل/الفرع/الفني، session+CSRF للBlade، guest token موقّع محدود الصلاحية للdraft/cart فقط أو مسار guest verified المعتمد. final principal resolves server-side؛ لا customer_id/branch_id في body يغير صاحب العملية. page/cursor limits موثقة. `request_id` في كل استجابة، ولا stacktrace production.

Request mutations: `Idempotency-Key` لكل financial/scheduling/quote submission، `If-Match` revision للتعديلات. لا epoch timestamp في key يتغير بكل retry. `X-Commerce-Contract: 2`. ISO8601 UTC timestamps + timezone، local_date صريح ولا parses ambiguous dates. API returns minor integers وformatted amounts UI generated من currency metadata. `null total` للغير مسعر.

Envelope:
```json
{"data":{},"meta":{"request_id":"uuid","contract_version":2}}
```
Errors:
```json
{"error":{"code":"PRICE_CHANGED","message":"تغير السعر؛ راجع التفاصيل","fields":{},"retryable":false},"meta":{"request_id":"uuid","contract_version":2}}
```
Fields ترجع path مثل `answers.rooms[1].window_size`، لا أسرار أو سعر يعتمد على client fields. 201 فقط إنشاء كيان فعلي، 202 pending، 200 success/replay، 401 auth، 403 scope، 404 غير متاح لهذه الهوية، 409 conflict، 422 invalid، 429 rate، 503 capability/config unsafe.

## 2. أهم عقود العمليات
### Evaluate
input: service_version_id, offer_version_id?, city_id, address_id?, quantity, selections, answers, schedule?؛ output normalized answers/price_state/breakdown/requirements/configuration_hash/missing fields. لا يرسل العميل amount أو tax rate أو أي resource eligibility معتمد.

### Checkout preview
input source(cart/buy_now/accepted_quote/entitlement), cart_item_ids أو configurations، city_id، scheduling groups/occurrences، payment_method، coupon_codes، wallet_requested_minor؛ output preview_id/hash/expires_at، immutable item snapshots، group plans، allocations، eligible methods، total/pay_now/pay_later، excluded items/error reasons. الإتاحة المتوقعة ليست hold.

### Confirm
input preview_id/expected_hash/terms_version/payment_method؛ key mandatory. output order_id/attempt_id/state/bookings/payment_action?؛ duplicate returns same. ليست charge paid لأن `state=pending_payment`. provider payment action redacted short URL/session only.

### Quote
submit تعريف الخدمة/متغير/إجابات/مرفقات/عنوانين إن احتاج/موعد مفضل. output request_id/state. offer version يقدم breakdown/requirements/policies/expiry. accept specific version does not reserve؛ returns checkout_context صالح لمسار approved quote. لا يُرسل سعر العميل ضمن quote accept.

### Entitlement
id، total/reserved/consumed/available، expires_at، scoped eligible offer/version/units. redemption preview ينشئ plan ثم confirm يحجز تحت lock؛ لا decrement من UI. idempotency للreserve/release/consume/refund مستقل ويوثق مصدر الحدث.

### Operations
task transition يحمل action + expected_version + evidence_ids + checklist_answers؛ allowed_actions في response بحسب الدور. assignment يحمل technician/team ids المخولة لكن resolver يراجع الانتماء والمهارات والcapacity. cash collection مبلغ محدد لا تجاوز outstanding مع idempotency؛ ledger reference returned.

## 3. أكواد الخطأ الإلزامية
| الكود | المعنى والمعالجة |
|---|---|
| CONTRACT_UPGRADE_REQUIRED | نسخة التطبيق قديمة؛ احصل config وأظهر التحديث دون كتابة |
| CITY_UNSUPPORTED / CITY_PAUSED | لا حجز؛ اختيار عنوان/انتظار التفعيل |
| GEO_SOURCE_NOT_APPROVED / CITY_CONFIGURATION_UNSAFE | إعداد محمي غير جاهز؛ لا bypass |
| CITY_TRANSACTION_ISOLATION_UNSAFE | MySQL isolation مرفوض؛ توقف الالتزام |
| CITY_BRANCH_NOT_READY / SERVICE_NOT_READY | سبب readiness ظاهر للعميل إجمالاً وللأدمن تفصيلاً |
| CHECKOUT_CITY_CONFLICT | العناصر المختارة من مدن مختلفة؛ أقسام منفصلة لا مسح |
| CONTEXT_CHANGED / SCHEMA_CHANGED | عنوان/مدينة/خدمة تغيرت؛ إعادة تقييم مع الاحتفاظ بما يمكن |
| FIELD_VALIDATION_FAILED / ATTACHMENT_REJECTED | تحديد الحقل أو الملف وإصلاحه |
| QUOTE_REQUIRED / QUOTE_EXPIRED | عرض سعر وليس total=0 |
| PRICE_CHANGED / PREVIEW_EXPIRED | راجع preview جديدة قبل الموافقة |
| SLOT_UNAVAILABLE / HOLD_EXPIRED | موعد آخر؛ لا خصم جديد بلا تأكيد |
| INCOMPATIBLE_GROUP | فصل مجموعة باختيار العميل |
| IDEMPOTENCY_MISMATCH / VERSION_CONFLICT | لا تكرار side effect؛ استرجاع الحالية |
| PAYMENT_PENDING / PAYMENT_UNVERIFIED | تحقق server؛ لا completed false |
| PAYMENT_RECONCILIATION_REQUIRED | مبلغ متحقق ولم يثبت التنفيذ؛ حالة معالجة/استرداد |
| PAYMENT_METHOD_INELIGIBLE / WALLET_INSUFFICIENT | اختيار وسيلة أخرى/تعديل subset |
| ENTITLEMENT_EXPIRED / ENTITLEMENT_INSUFFICIENT | لا وعد بزيارة؛ تجديد/شراء |
| REFUND_LIMIT_EXCEEDED / TRANSITION_NOT_ALLOWED | يمنع الفعل، audit ومحاولة مخولة فقط |
| RESET_GUARD_FAILED / PROTECTED_DATA_CHANGED | CLI owner؛ stop لا force |

## 4. الأحداث
اكتب event envelope: event_id UUID، event_type، schema_version=1، generation، aggregate_type/id/version، occurred_at UTC، actor_id?، branch_id?، correlation_id، payload typed. لا هاتف/عنوان في broker log افتراضي؛ notification worker يقرأه وفق authorization.

الأحداث المطلوبة: `catalog.published`, `order.confirmed`, `payment.verified`, `payment.resolution_required`, `booking.scheduled`, `booking.assigned`, `booking.rescheduled`, `booking.cancelled`, `booking.completed`, `task.changed`, `quote.sent`, `quote.accepted`, `quote.expired`, `entitlement.granted`, `entitlement.reserved`, `entitlement.consumed`, `entitlement.released`, `entitlement.expired`, `renewal.failed`, `cash.collected`, `cash.remitted`, `refund.requested`, `refund.completed`, `adjustment.accepted`.

Outbox transactionally written، dispatch after commit، consumers inbox dedupe. retries لا تعيد الإيراد/الرسالة/الفاتورة مرتين. aggregate_version يمنع event قديم يعيد حالة أحدث؛ لا وعد exactly-once للشبكة، المقصود أثر منطقي مرة واحدة.

## 5. الربط بالمصدر
Audit route usages من `app_constants.dart` في التطبيقات الثلاثة وBlade routes وPaymentSuccess hooks وOdoo observers وjobs والتقارير. أرفق ملف `LEGACY-COMMERCE-ROUTE-MAP.md` النهائي: keep auth/config، redirect/application adapter، block at cutover، remove. لا تترك API V1 يكتب legacy financial records بعد V2. gateway callbacks لا تُلغى على أساس رقم العقد فقط؛ تعالج وعزل مراجعها القديمة بأمان.
