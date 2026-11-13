# S01 — المعمارية ونموذج البيانات والحالات
## 1. توزيع المسؤولية
نفذ modular monolith داخل Laravel. المسار المقترح الجديد `Modules/CommerceModule` وفيه Catalog, Forms, Pricing, Cart, Checkout, Orders, Scheduling, Entitlements, Quotations, Fulfillment, Finance. سجل الوحدة في manifest وservice provider والroutes وفق أسلوب المستودع. لا تنشئ خدمة منفصلة أو قاعدة منفصلة.

التبعيات الموجودة تبقى مالكة: الهوية في UserManagement/Auth؛ المدينة في `App\Services\City`؛ الفروع والفنيون والورديات في وحداتهم؛ موصلات الدفع في PaymentModule؛ OdooIntegration؛ الاتصالات والتخزين. توفر adapters للواجهات الجديدة، وتغيّر قراءاتها من legacy bookings إلى bookings V2. **ممنوع dual write للمال أو الحجوزات.**

البنية التجارية الجديدة تستخدم prefix `commerce_` لتجنب تعديل جدول إعداد مشترك بطريق الخطأ. بقاء جداول legacy مؤقتاً حتى cutover لا يعني دعم مسار تجاري قديم. عندما يفعل V2: جميع الكتابات التجارية القديمة محظورة؛ auth/config/cities تبقى متاحة؛ الـwebhooks القديمة تمر إلى مسار عزل لا تُهمل عشوائياً.

## 2. قواعد عامة للبيانات
- UUID للكيانات العامة، FK بنفس نوع عمود الأصل. مفاتيح المستخدمين والفروع UUID؛ العنوان الحالي BIGINT: لا تفرض UUID على `user_addresses.id`.
- كل مال جديد integer minor units (`*_minor`)، SAR هللات، العملة صريحة؛ لا float في محرك المال. المقادير مثل m² decimal string حتى 3 منازل. نسب bps أعداد صحيحة. لا ترسل أسرار config إلى public DTO.
- timestamps UTC في التخزين، مع timezone المدينة والوقت المحلي في snapshot. استخدم `SaudiTime` في adapters بعد توحيد دلالته واختباره؛ لا تخزن UTC ثم تفسره كوقت سعودي مرة أخرى.
- `version` optimistic concurrency لكل draft/schedule/order mutable؛ `created_at/updated_at` لكل جدول، `created_by` عند التغيير الإداري. لا soft-delete للحركات المالية: reversal فقط. لا حذف إصدار منشور مستعمل.
- snapshot يحفظ أسماء البنود وترجماتها والأسئلة وإجاباتها ووحداتها والأسعار والسياسات والمتطلبات التشغيلية والعنوان وقت الشراء. لا يعتمد تقرير قديم على اسم خدمة mutable.
- الأعمدة التي تستخدم filters/indexes/FKs لا تدفن في JSON. JSON مسموح لتعريف النماذج المحدود والمحتوى والsnapshots، مع validation وschema_version.

## 3. الجداول المطلوبة
الأسماء تصميم جديد، لا وصف للقاعدة الحالية. يجوز دمج جدولين داخلياً بقرار موثق فقط إن بقيت قيودهما واختباراتهما؛ لا تنشئ جدولاً ثانياً لنفس المدفوعات.

| الجدول بعد `commerce_` | الحقول الجوهرية والعلاقات | القيود والفهارس |
|---|---|---|
| services | id, code, status, current_version_id | code UNIQUE؛ published_version FK يضاف بعد إنشاء version |
| service_versions | id, service_id, version, schema_version, content_json, form_json, journey_json, policy_json, requirements_json, published_at/by | UNIQUE(service_id,version)؛ الإصدار المنشور immutable |
| offers | id, service_id, code, status, current_version_id | UNIQUE(service_id,code) |
| offer_versions | id, offer_id, version, kind, pricing_json, included_json, addon_json, entitlement_json, requirements_json, published_at | UNIQUE(offer_id,version)؛ تحقق مرجع إصدارات المكونات |
| city_offers | id, city_id, offer_id, enabled, price_override_json?, policy_revision | UNIQUE(city_id,offer_id)؛ branch مشتق من assignment لا اختيار حر |
| media | id, owner_id, visibility, purpose, storage_disk, object_key, mime, bytes, scan_state | UNIQUE(disk,key)؛ private customer uploads؛ لا أسرار URLs |
| assets | id, owner_id, type, attributes_json, version | index(owner_id,type)؛ car schemas؛ ليس مطلوباً حفظ الأصل لإنشاء الحجز |
| carts | id, owner_id? أو guest_id?, version, expires_at | قيد owner XOR guest؛ جلسة ضيف موثقة لا id فقط |
| cart_items | id, cart_id, kind, service_version_id?, offer_version_id?, quote_version_id?, city_id, address_id?, answers_json, quantity, selections_json, status, version | index(cart_id,city_id,status)؛ distinct configuration fingerprint |
| checkout_previews | id, principal_id, cart_version, selected_items_hash, price_hash, city_context_revision, snapshot_json, expires_at, consumed_order_id? | immutable snapshot؛ unique الاستهلاك؛ ليست حجز سعة |
| orders | id, number, customer_id, city_id, currency, lifecycle_state, settlement_state, totals_minor, version, confirmed_at | number UNIQUE؛ index(customer_id,created_at) |
| order_items | id, order_id, kind, service_version_id?, offer_version_id?, snapshot_json, subtotal_minor, discount_minor,tax_minor,total_minor | sum items + fees = order total؛ immutable بعد تأكيد |
| fulfillment_groups | id, order_id, address_snapshot, branch_id, requirements_snapshot, schedule_policy_snapshot, status | branch يطابق city assignment عند الالتزام؛ index(branch_id,status) |
| bookings | id, number, order_id, group_id, branch_id, city_id, schedule_start_utc, schedule_end_utc, timezone, state, kind, recurrence_id?, entitlement_id?, version | number UNIQUE؛ index(branch_id,state,start,end)؛ start<end |
| booking_items | id, booking_id, order_item_id, component_snapshot, allocated_minor, visit_index? | UNIQUE(booking_id,order_item_id,component_code)؛ يمنع duplicate charges |
| reservations | id, booking_id?, checkout_attempt_id?, branch_id, resource_type, resource_id, start/end, expires_at?, state, resource_units | ACTIVE/HOLD/RELEASED؛ index(resource,start,end,state)؛ يحمى بقفل مشترك |
| recurrence_plans | id, customer_id, timezone, rule_json, end_at, status, source_order_item_id | finite count/until؛ occurrence_key UNIQUE على booking |
| entitlements | id, customer_id, source_order_item_id, total_units, reserved_units, consumed_units, expires_at, status, snapshot_json | 0≤reserved+consumed≤total؛ source unique لكل grant |
| entitlement_entries | id, entitlement_id, booking_id?, type, delta_reserved, delta_consumed, key | key UNIQUE؛ append-only؛ استرداد واحد لكل زيارة |
| renewal_agreements | id, customer_id, kind, offer_version_id, consent_snapshot, payment_mandate_ref?, next_at, status | لا PAN/CVV؛ unique cycle grant بواسطة renewal_cycle_key |
| quote_requests | id, customer_id, city_id, branch_id, service_version_id, variant_code, answers_snapshot, preferred_schedule?, state | لا ينشأ إلا submit؛ index(branch_id,state) |
| quote_versions | id, request_id, revision, items_json, totals_minor, expires_at, state, customer_accepted_at? | UNIQUE(request_id,revision)؛ accepted immutable |
| tasks | id, booking_id, type, state, assigned_team_id?, start/end?, checklist_snapshot, version | أنواع onsite/inspection/pickup/process/delivery؛ index(booking,state) |
| task_assignments | id, task_id, technician_id, role, accepted_at, released_at? | UNIQUE active assignment منطقي؛ يحميه lock |
| task_evidence | id, task_id, media_id, phase, actor_id, timestamp, notes? | تفويض object-level؛ before/after/receipt |
| checkout_attempts | id, order_id, preview_id, principal_id, state, expires_at, generation | order/preview unique حسب idempotency؛ gateway ref في PaymentRequest |
| payment_allocations | id, order_id, payment_request_id? أو collection_id?, order_item_id, booking_id?, entitlement_id?, amount_minor, refunded_minor | لا تجاوز captured؛ مرجع واحد للمصدر؛ index(order_id) |
| cash_collections | id, order_id, booking_id, technician_id, branch_id, amount_minor, key, remittance_state | key UNIQUE؛ paid ≠ remitted |
| ledger_accounts | id, code, owner_type, owner_id?, branch_id?, currency, cached_balance_minor | UNIQUE(code,owner,branch,currency)؛ cached يعاد اشتقاقه |
| ledger_transactions | id, event_key, source_type,id, reversal_of?, posted_at | event_key UNIQUE؛ append-only |
| ledger_entries | id, transaction_id, account_id, debit_minor, credit_minor, order_id?, booking_id?, branch_id? | لكل قيد جانب واحد؛ total debit=credit |
| wallet_holds | id, customer_id, checkout_attempt_id, amount_minor, expires_at,state | قفل wallet account؛ السحب لا يتجاوز available |
| adjustments | id, booking_id, version, items_snapshot, total_delta_minor, state, accepted_at?, reason | لا تغيير سعر دون موافقة؛ reject/expire مدعومان |
| idempotency_records | id, actor_scope, operation, key, request_hash, status, response_ref, expires_at | UNIQUE(actor_scope,operation,key) |
| event_outbox | id, event_id, aggregate_type/id/version, event_type,payload_json,status,next_at,attempts | event_id UNIQUE؛ at-least-once + inbox dedupe |
| event_inbox | id, source, external_event_id, payload_hash, state, received_at | UNIQUE(source,external_event_id)؛ verify قبل apply |
| reset_runs | id, manifest_hash, generation, state, checkpoint_json, protected_digest, report_path | تبقى بعد reset؛ ليست في allowlist حذف |
| cutoff_fences | id, gateway, legacy_reference_hash, generation, reason, created_at | منع ربط callbacks قديمة بمعاملات جديدة؛ no reset |

**مدفوعات البوابات:** أبقِ `payment_requests`, `payment_sessions`, `payment_refunds` مصدر الحقيقة للبوابة وأضف ربط V2 صريحاً وminor-unit adapter. لا تنشئ `commerce_payments` أو refund gateway worker ثانياً. حدّث refund immutable policy لتقبل مرجع V2 دون إعادة ربط قرارات قديمة. حارس reset لا يعطل observers مالياً في التشغيل الطبيعي.

**الإعدادات:** لا تنقل مفاتيح التكامل إلى جداول commerce. استخدم config وaddon_settings وbusiness_settings الحالية؛ أضف فقط إعدادات V2 باسم namespace واضح مع `insert-if-absent` وسياسة اعتماد.

## 4. آلات الحالة
| الكيان | الحالات والانتقالات المسموحة |
|---|---|
| Service/Offer | draft → published → paused → archived؛ التعديل ينشئ version جديدة، والأرشيف لا يحذف snapshot |
| Order lifecycle | pending_payment → confirmed → in_progress → completed؛ pending_payment → expired/cancelled؛ confirmed/in_progress → partially_cancelled حسب البنود؛ paid-but-no-capacity → resolution_required |
| Settlement | unpaid, partially_paid, paid, refund_pending, partially_refunded, refunded؛ مستقلة عن تنفيذ الحجز |
| Booking | awaiting_payment → scheduled/expired/cancelled؛ scheduled → assigned → en_route → arrived → in_progress → completed؛ قبل البدء cancel/reschedule/no_show وفق policy؛ لا تعديل مباشر completed |
| QuoteRequest | submitted → reviewing → inspection_needed/quoted → accepted/rejected/expired/cancelled؛ القبول يسلم checkout ولا يثبت حجز سعة |
| QuoteVersion | draft → sent → accepted/rejected/expired/superseded؛ نسخة مقبولة لا تعدل |
| Task | pending → assigned → in_progress → done؛ failed/blocked/cancelled مع سبب؛ completion aggregate لا يتجاوز required tasks |
| Entitlement | pending_payment → active → exhausted/expired/cancelled؛ reserve/release/consume ledger لا حذف صامت |
| Renewal | active → paused/cancelled؛ فشل الدفع past_due؛ لا grant قبل payment verified |
| Reservation | hold → committed/released/expired؛ committed → released عند تعديل/إلغاء منضبط |

كل transition يستدعي policy guard وversion check وidempotency وسجل تدقيق، ولا يقبل `state` اعتباطياً من العميل. branch/team scope على query وعلى mutation. اجعل projections مثل dashboard قراءة من الحالات الجديدة، لا أرقاماً ثانية قابلة للتحديث يدوياً.

## 5. نهائية التصميم مقابل الحذف القديم
بعد cutover ليست هناك كتابة إلى legacy carts/services/bookings/repeats. تنظيف جداول legacy بأمر owner منفصل يراجع FK إلى المحميات؛ يمكن الاحتفاظ بجدول هيكلي مؤقت لا يُقرأ تجارياً إذا كان حذفه سيكسر config، مع تسجيل dependency وخطة إزالة كود، وليس الاحتفاظ بمعاملات قديمة. ممنوع جعل نجاة legacy سلوكاً مطلوباً من V2.
## 6. توضيحات تنفيذية للحقول المشتركة
`totals_minor` اختصار وصفي في الجدول وليس blob مالي: نفذ أعمدة BIGINT `subtotal_minor`, `discount_minor`, `fee_minor`, `tax_minor`, `total_minor`, `paid_minor`, `refunded_minor`؛ nonnegative checks حيث يلزم، والنواتج derived لا يكتبها controller مستقلاً. `currency` CHAR(3)، `quantity` integer أو decimal string حسب unit، وnormalized_answers JSON محدود.

أضف `commerce_capabilities(id,code UNIQUE,label_json)` و`commerce_technician_capabilities(id,serviceman_id,capability_id,approved_by,approved_at)` مع UNIQUE(serviceman_id,capability_id). هذا يفصل مهارة الفني عن الخدمة التسويقية المحذوفة. الترحيل من serviceman_skills لا يستنتج أهلية جديدة من الاسم؛ mapping مراجعة. احتفظ teams/شروط انضمام الفريق في الجداول الحالية.

وسائل حماية العلاقات: order_items FK RESTRICT؛ service versions لا cascade-delete إلى الطلبات؛ snapshots تحفظ display حتى archive. soft delete للassets لا يحذف snapshot الحجز. external adapter FK على جداول بوابات الدفع تحدّث عبر migrations additive لا تغيير أصل immutable refund. `generation` في order/attempt/events موروثة من cutoff namespace؛ أرقام orders/bookings prefixes مميزة ولا تعاد إلى تاريخ سابق.
