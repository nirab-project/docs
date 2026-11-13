# CITY-13 | خطة المراحل والتبعيات ومعايير الإغلاق

## مبادئ التنفيذ

- كل مرحلة تبنى على السابقة، مع commits/PRs صغيرة ومتجانسة أو Branch عمل واضح؛ لا تخلط تعديل بوابة الدفع مع تنسيق الواجهة.
- لا يختار الوكيل بين 10 حلول متناقضة؛ يتبع القرارات في `01-ARCHITECTURE-DECISIONS.md`, ويكتب ADR قصير عند الحاجة بسبب المصدر الفعلي.
- لا تختصر Gate Audit/Geo/Financial ولا تُدخل حالات نجاح وهمي لإنهاء المهمة بسرعة.
- الـ`city_id` هو معرف المنتج الجديد، والـ `zone_id` آلية توافق داخلية تضمن صحة التسعير/الطلبات، و`branch_id` اشتقاق حصري من المدينة.

## جدول التنفيذ

| رقم | المرحلة | المستند الرئيسي | Dependency | Artifact إغلاق |
|---|---|---|---|---|
| CITY-01 | Source audit + dependency map | 02 | لا شيء | `CITY-01-SOURCE-AUDIT.md` |
| CITY-02 | Schema + constraints + additive migrations | 03 | CITY-01 | `CITY-02-DATA-REPORT.md` |
| CITY-03 | Geo boundaries + CityResolver + address guards | 04 | CITY-02 + approved geo source | `CITY-03-GEO-REPORT.md` |
| CITY-04 | Admin cities & city-branch publish workflow | 05 | CITY-02/03 | `CITY-04-ADMIN-REPORT.md` |
| CITY-05 | Serviceability/Catalog/Cart/Quote | 06 | CITY-03/04 | `CITY-05-CATALOG-CART-REPORT.md` |
| CITY-06 | Single branch checkout + Calendar/Repeat + financial compatibility | 07 | CITY-05 | `CITY-06-BOOKING-REPORT.md` |
| CITY-07 | Customer Flutter Web/Android | 08 | CITY-05/06 APIs | `CITY-07-CUSTOMER-FLUTTER-REPORT.md` |
| CITY-08 | Provider/Serviceman compatibility | 09 | CITY-04/06 | `CITY-08-OPERATIONS-REPORT.md` |
| CITY-09 | QA static, feature flags, release plan and rollback | 10/11/12/14 | ALL | `CITY-09-RELEASE-READINESS-REPORT.md` |

## CITY-01 — Source Audit

- تحقّق من source hashes/branches/dirty state ومن مسارات حفظ العنوان والكتالوج والسلة وCheckout والتكرار وQuote/Payment.
- احصر اعتماد كل جزء على `zone_id` وprovider/area؛ أثبت مدى كفاية Mapping Zone/City.
- احسم وجود City schema سابق ومصدر الحدود، واشرح blockers.
- **Gate:** لا تبدأ migrations بحدس أو من تقارير قديمة فقط.

## CITY-02 — Data Model

- أنشئ Schema additive، migrations، relationships/indexes/unique constraints، اختبارات DTO، DRY-RUN audit.
- اكتب Data migration/backfill helper `--dry-run` default؛ لا تشغله على الإنتاج.
- **Gate:** لا Referential Data Loss ولا destructive migration.

## CITY-03 — Geolocation

- تحقق معتمد من حدود المدينة + CityResolver + CityBranchResolver؛ add/update address guards لكل المسارات.
- رفض fake city/zone/provider؛ حالات خطأ منفصلة؛ runtime geo source gate.
- **Gate:** لا عنوان غير مدعوم يمكن حفظه. إذا geo dataset غير معتمد تسليم **code-ready blocked-for-launch** فقط.

## CITY-04 — Admin Workflow

- قائمة المدن وCreate Draft/Assign/Reassign Impact Preview/Readiness/Publish/Pause؛ RBAC/Audit.
- إزالة branch coverage manual من واجهة المسار الجديد مع legacy compatibility.
- **Gate:** قواعد 1:1 مضمونة بقيد DB وAPI، لا نشر مدينة غير جاهزة.

## CITY-05 — Catalog & Cart

- واجهة خدمة موحّدة تعتمد engines الموجودة، تربط Catalog بـcity branch، تشترط عنوان verified قبل add/update.
- منع cart city mismatch، التمييز بين temporarily busy vs not configured، الحفاظ على Quote.
- **Gate:** لا عرض/إضافة قابلة للحجز لخدمة مستحيلة تشغيليًا بسبب نقص إعداد أساسي.

## CITY-06 — Calendar/Checkout

- فصل `single-city planner` عن مسار legacy وتهيئة slots يومية/دفعات + Repeat Suggestions صحيحة.
- الحفاظ على Snapshot/Lock/Payment/Wallet/Odoo/Refund/Legacy Multi-branch.
- **Gate:** لا double booking، لا نقل فرع صامت، لا خصومات خاطئة، لا false slots.

## CITY-07 — Flutter Customer

- اختيار مدينة من API، resolve location/address، Catalog، Cart، Calendar inline، repeat UX، ترجمة AR/EN وحالات UI.
- cache invalidation على city/address/items/visits + Web/Android parity.
- **Gate:** لا user path يستطيع حفظ عنوان مرفوض في Backend، لا stale slots.

## CITY-08 — Provider/Serviceman

- عرض المدينة read-only، إخفاء coverage الرسم في city mode، جاهزية الفرق/المهارات/الورديات، الإسناد داخل الفرع.
- حماية الحجوزات القديمة ومساراتها.
- **Gate:** صلاحيات/OTP/الحجوزات القديمة لم تتراجع.

## CITY-09 — Source QA & Owner Handoff

- كامل `11-QA-ACCEPTANCE.md` على مستوى source checks الممكنة، مراجعة مالية/أمان/جغرافيا، docs، runbooks، flags، rollback.
- لا تدعِ Runtime PASS إن لم يُنفذ. حضّر مصفوفة P0/P1 Test Cases لتجربتها لاحقًا.
- **Gate:** تقرير فجوات واضح وحالة code completion منفصلة عن production acceptance.

## قالب تقرير كل مرحلة

```markdown
# CITY-XX Implementation Report
- Source repositories/branches/HEADs:
- Current behavior before change (file:line evidence):
- Files added/modified:
- Routes/DB fields/API changes:
- Backward compatibility and security:
- Test commands actually run and results:
- Unrun tests (why):
- Risks/blockers/gates:
- Manual acceptance actions for owner:
- Ready for next phase? yes/no, reason:
```

## شروط خروج شامل

1. جميع P0 للمصدر مُعالجة مع Traceability إلى Git Changes.
2. No destructive schema، no unauthorized production execution، reliable geo data source gate.
3. API contract versioning صحيح، AR/EN keys، Payment compatibility.
4. كل مراحل User/Provider/Serviceman ذات أثر تم فحصها المصدر وتوثيقها.
5. ملف مستقل للموافقة التشغيلية اللاحقة: `CITY-ACCEPTANCE-RESULTS.md`.
