# CITY-10 | عقود API المقترحة — City Mode V1

> هذه صياغة Target Contract لتطبيقها بعد تدقيق Routes الحالية. **لا تدّعِ وجودها الآن**. ابدأ بالتحقق من `response_formatter` وطريقة المصادقة/guest IDs والأسماء المسجلة، ثم استخدم الصيغة المعتادة للمشروع مع الحفاظ على الدلالة والحقول أدناه. إن كان هناك Endpoint مماثل فاستخدم extend additive بدل ازدواجيته.

## مبادئ عامة

- Base: `/api/v1`؛ كافة العمليات الحساسة تمنع التلاعب بـ `provider_id` و`zone_id` المرسلين من العميل.
- Customer public GET قد يقبل guest؛ حفظ العنوان/Cart/Checkout يتحقق من Principal/Guest principal الموجود (لا يكفي `guest_id` وحده).
- استجابة `content` وما حولها تتبع `response_formatter` الفعلي؛ الأمثلة التالية تمثل **content body** لشرح الدلالة.
- الأوقات باستخدام ISO-8601 offset صحيح وتوقيت `Asia/Riyadh`.
- أخطاء validation تستخدم `422`؛ تضارب علاقة/سلة/سعة `409`؛ نقص صلاحية `403`، فشل المصدر المؤقت `503`؛ لا `200 success` مع حالة وهمية.
- backend مسؤول عن التحقق النهائي عند كل حجز، ولا تعتمد على نتائج Calendar/Catalog cache.

## 1. قائمة المدن المتاحة

`GET /api/v1/customer/cities`

- تُظهر فقط مدن `active`, Geo Verified, assigned branch unique and Published/Intake, and >=1 service configured for booking/quote.
- Response، مثال للهيكل وليس حالة فعلية:

```json
{
  "cities": [
    {
      "id": "uuid-riyadh",
      "code": "SA-RUH-CITY",
      "name": "الرياض",
      "name_en": "Riyadh",
      "country_code": "SA",
      "status": "available"
    }
  ],
  "revision": 42
}
```

- لا ترسل قائمة المدن المتوقفة أو فرعها الداخلي/بيانات الفنيين.
- أسماء الأكواد التجريبية أعلاه placeholders، لا تستخدمها كرموز رسمية دون مصدر مرجعي.

## 2. حل الإحداثيات إلى المدينة

`POST /api/v1/customer/location/resolve`

```json
{
  "latitude": 24.7135517,
  "longitude": 46.6752957,
  "expected_city_id": "city-uuid-optional"
}
```

نجاح مثال بنيوي:

```json
{
  "status": "verified",
  "supported": true,
  "resolved_city": {"id": "city-uuid", "name": "الرياض"},
  "can_save_address": true,
  "can_start_booking": true,
  "verification_source": "approved_city_boundary",
  "boundary_revision": "v3"
}
```

في mismatch يعاد `LOCATION_CITY_MISMATCH` مع المدينة المحلولة للاختيار، وليس حفظًا تلقائيًا. نقطة خارج النطاق `CITY_UNSUPPORTED`; مصدر geo متعطل `LOCATION_VERIFICATION_FAILED`.

**حارس مهم:** `resolve` معاينة؛ endpoint حفظ العنوان يعيد التحقق Server-side من coordinates والمدينة والحالة، دون الثقة في استجابة client محفوظة.

## 3. إنشاء وتحديث العنوان — extend existing

ابقَ على المسار الحالي لحفظ العناوين إن أمكن. الحقول المنطقية: `latitude`, `longitude`, `address`, `address_label`, `contact_*`، city selected اختياريًا للتوجيه؛ city الحقيقي مشتق من النقطة.

- 201: `address_id`, `resolved_city_id`, `verification_status=verified`.
- 422: `CITY_UNSUPPORTED`, `CITY_PAUSED`, `LOCATION_CITY_MISMATCH`, `ADDRESS_INVALID`.
- 503: `LOCATION_VERIFICATION_FAILED`، لا ينشئ سجل `verified`.
- PUT changing location is equivalent to new verification؛ لا bypass لطريق Update أو Provider/customer checkout free address.

## 4. كتالوج مدينة واحدة

`GET /api/v1/customer/cities/{city_id}/services?limit=20&offset=0`

```json
{
  "city_id": "city-uuid",
  "services": [
    {
      "id": "service-uuid",
      "name": "تنظيف المكيفات",
      "pricing_type": "fixed",
      "availability_status": "ready_for_scheduling",
      "starting_price": 100,
      "currency": "SAR"
    }
  ],
  "meta": {"total": 1, "limit": 20, "offset": 0}
}
```

`starting_price` توضيحي لا يمثل تسعيرًا حقيقيًا أو إجماليًا شاملًا. خدمة quote تحصل على `pricing_type=quote` وCTA منفصل. لا تظهر خدمة stop/admin false كقابلة للحجز.

## 5. Cart Add / Update / Validate — extend existing

- لا تنشئ Cart جديدة إذا كان الحالي كافيًا؛ اربطه بعنوان verified.
- مبادئ الطلب: `service_id`, `variant_key`, `quantity`, `field_answers`, `address_id` أو authoritative checkout address context.
- لا يقبل `provider_id` من العميل كفرع مختار في City Mode؛ خادم API يستنتجه من `resolved_city_id`، وإذا أرسله عميل قديم يتحقق من مطابقته دون إعادة توجيه صامتة.
- خطأ نقص فريق: 422 `SERVICE_NOT_READY`, reason=`TEAM_MISSING`.
- تضارب عنوان/سلة مدن: 409 `CART_CITY_CONFLICT`.
- يجب أن يعيد `GET cart` حالة الأهلية وأسباب العناصر الحالية؛ لا يحذف عناصر.

## 6. تقويم الأيام

`POST /api/v1/customer/booking/availability/calendar`

```json
{
  "address_id": "address-uuid",
  "city_id": "city-uuid",
  "items": [
    {"service_id": "service-uuid", "variant_key": "option", "quantity": 1, "field_answers": []}
  ],
  "from": "2026-11-15",
  "to": "2026-11-21",
  "booking_type": "regular"
}
```

```json
{
  "timezone": "Asia/Riyadh",
  "days": [
    {"date": "2026-11-15", "status": "available", "slots_count": 5},
    {"date": "2026-11-16", "status": "unavailable", "reason_code": "NO_AVAILABLE_SLOTS"},
    {"date": "2026-11-17", "status": "not_checked"}
  ],
  "evaluated_at": "2026-11-13T13:00:00+03:00"
}
```

هذه قيم توضيحية للبنية لا لإتاحة حقيقية. `status=unavailable` فقط عند تحقق فعلي؛ عند timeout اكتب `error` مع retry. حد النطاق المبدئي 7 أيام مع pagination وأحجام Cart/Visits محدودة.

## 7. Slots موجودة — حافظ على العقد

`GET /api/v1/customer/booking/availability/slots`

- يبقى Date واحدة (ما لم تُضف نسخة جديدة) ويعود `slots[].starts_at`, `timezone`, `unavailability_message_key` عبر parser الحالي، مع branch ثابت من City.
- أي `provider_id` returned للتوافق فقط، لا يفتح اختيار فرع أو يثبته client ضمن Auto Mode القديم.
- لا تُظهر أسماء الفنيين أو هواتفهم في Customer response.

## 8. Repeat Suggestions — إذا لم يكفِ العقد الحالي

`POST /api/v1/customer/booking/availability/repeat-suggestions`

```json
{
  "address_id": "address-uuid",
  "city_id": "city-uuid",
  "items": [{"service_id": "service-uuid", "quantity": 1, "variant_key": "option"}],
  "days": ["2026-11-15", "2026-11-17"],
  "mode": "same_time"
}
```

Response: `timezone`, `days_count`, `common_options[]` each with actual visit schedule validation result, `daily_options[]`, `unavailable_days[]`, `reason_code`. كل option مر من planner لجميع الأيام، وليس intersect سطحي لساعات اليوم الواحد. حد أيام صغير ومحدود، ولا شيء يوحي بضمان الحجز.

## 9. Checkout Price/Place — extend existing

- أبقِ Routes الحالية وحقول `checkout_snapshot_id`, `hash`, `dates`, `booking_type`, `booking_id` القديمة.
- City Mode يثبت الفرع الحصري ويُعيد Snapshot يتضمن `resolved_city_id`, `resolved_branch_id`, `price_basis`, `price_status`, `total`, `expires_at` عند الحاجة.
- `409 SLOT_UNAVAILABLE`, `CHECKOUT_CONTEXT_CHANGED`, `checkout_price_changed` عند تضارب الخطة/الموعد/السعر، وتظل idempotency محفوظة.
- `bookings[]` القائمة للحجز الواحد/القديم، لا تغيّر Array إلى Object بصورة كاسرة.

## 10. Admin — Routes مقترحة

`GET /admin/cities`، `POST /admin/cities`، `PUT /admin/cities/{id}`، `POST /admin/cities/{id}/assignment`، `GET /admin/cities/{id}/readiness`، `POST /admin/cities/{id}/publish`, `/pause`, `/impact-preview`.

- استعمل نظام Web session/CSRF/Permissions الحالي، ولا تدع اسم endpoint يلتف على `availability.manage`/`branches.publish`.
- `409 CITY_BRANCH_CONFLICT` يثبت وجود assignment غير مسموح؛ لا يستبدل ربطًا قائمًا بصمت.
- Apply دائمًا يؤكد State/Readiness داخل transaction وإلا فشل، وينشئ Audit.

## أكواد أسباب مشتركة وترجمة

| Code | Client wording (AR) | Admin details |
|---|---|---|
| `CITY_UNSUPPORTED` | لا نقدم الخدمة في هذا الموقع حاليًا | لا مدينة منشورة تحتوي النقطة |
| `CITY_PAUSED` | خدمات هذه المدينة متوقفة مؤقتًا | حالة city/branch intake |
| `LOCATION_CITY_MISMATCH` | موقعك في مدينة أخرى | Expected vs resolved IDs |
| `LOCATION_VERIFICATION_FAILED` | تعذر التحقق من الموقع، حاول مجددًا | Geo failure/timeout |
| `CITY_GEOMETRY_AMBIGUOUS` | نحتاج إعادة تحديد موقعك | Multiple boundary matches |
| `CITY_BRANCH_CONFLICT` | لا يمكن إتمام العملية حاليًا | Unique city/branch conflict |
| `SERVICE_NOT_READY` | الخدمة غير متاحة للحجز حاليًا | Missing team, skill, auth, price, hours |
| `CART_CITY_CONFLICT` | السلة تخص مدينة أخرى | mismatch Cart context |
| `NO_AVAILABLE_SLOTS` | لا توجد مواعيد في هذا اليوم | Capacity, shift, leave etc. |
| `SLOT_UNAVAILABLE` | هذا الموعد لم يعد متاحًا | Concurrent reservation |
| `CHECKOUT_CONTEXT_CHANGED` | تغيرت تفاصيل الطلب، راجعها | city/branch/price/plan revision |

**أي كود جديد يجب إضافته بنفس المعنى في API وFlutter AR/EN والتشخيص الإداري، مع Endpoint contract tests دون إجبار العميل على تخمين الحالة من نص الرسالة.**
