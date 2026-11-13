# خطة الاختبارات الآلية — تكتب مبكراً، تنفذ في PH-12 فقط
## مصفوفة إلزامية
| Suite | البيئة/المدخل | Assertions حرجة | الدليل المطلوب |
|---|---|---|---|
| Contract | OpenAPI + JSON Schema + DTO fixtures | كل ref/path parameter صحيح؛ serializers مطابقة؛ لا null→0 | schema/contract test log |
| Catalog | draft/publish/version/copy/archive | لا نشر ناقص، لا تعديل published، scope/identity من code لا اسم | PHPUnit |
| Forms | 200 fields وحدود repeater/conditions | كشف cycle، hidden values ignored/rejected، instance IDs ثابتة، bounds | unit/property-based cases |
| Money | PG01..PG20 + عشوائي bounded | نتائج بالهللة، مجموع allocation مطابق، لا overflow/float drift | golden results + seed random |
| Pricing security | forged total/offer/field/quantity | الخادم يعيد الحساب ويرفض cross-service option | HTTP tests |
| City GIS | MySQL Spatial SRID4326 | inside/outside/edge/overlap/status/revision؛ مصدر معتمد؛ unique assignment | SQL+HTTP tests |
| Isolation | اتصال READ COMMITTED وآخر غير آمن | قبول الأولى ورفض commit الثانية | real MySQL integration |
| Slot race | اتصالان مستقلان وحاجز بدء متزامن | last slot واحد؛ roles كلها تستخدم نفس القفل | concurrency logs |
| Team schedule | عمل بفنيين ومهارات متعددة | عدم احتجاز فنيين متعارضين، duration/buffer صحيحان | DB assertions |
| Checkout | cart/buy_now/quote/entitlement intents | subset/one-city/all-or-none/price hash/TTL | HTTP+DB |
| Idempotency | same/different hash وطلب جارٍ | نتيجة واحدة و409 mismatch و202 in-progress | race + retry log |
| Wallet | 20 محاولات متوازية على رصيد محدود | لا سالب ولا double debit؛ ledger balanced | concurrent integration |
| Gateways | local fake صحيح/مخالف/متأخر | signature/amount/currency verified؛ redirect غير كافٍ | webhook logs redacted |
| Late payment | hold expired / capacity taken | reacquire أو resolution/refund؛ لا overbook | integration assertions |
| Refund | partial/repeated/timeout/over-limit | cumulative bound، state pending حتى ack | finance ledger assertions |
| Bank/cash | approve/reject/remit repeat | receipt ليست paid؛ received≠remitted | HTTP+ledger |
| Entitlements | reserve/consume/release/expire | units invariant؛ visit واحد للغسلة الكاملة؛ idempotent | DB constraints+race |
| Recurrence | weekly/custom/count/until | finite، no duplicates، عدم نقل موعد تلقائياً، timezone | schedule fixtures |
| Renewals | cycle duplicate/fail/cancel | no grant قبل paid؛ دورة واحدة؛ consent محفوظ | fake clock + worker |
| Quotes | drafts/inspection/version accept | النقر لا ينشئ official deal؛ accepted version ثابتة | API tests |
| Fulfillment | onsite/team/pickup/process/delivery | required evidence، aggregate completion، amendment consent | transition tests |
| AuthZ | customer/guest/branch/technician/finance | IDOR/exports/files/actions خارج scope مرفوضة | security API tests |
| Media | fake MIME/size/XSS/SVG/foreign owner | رفض وإخفاء private objects؛ cleanup آمن | storage integration |
| Outbox | rollback/retry/duplicate/out-of-order | أثر منطقي مرة واحدة؛ لا إرسال قبل commit | real queue local |
| Odoo | fake transport + mapper | references/allocation/account scopes، no duplicate invoice | fake journal + mapping assertions |
| Reports | one-to-many joins/cancel/refund/pack | لا double count ولا إيراد وهمي من رصيد غير مستخدم | reconciliation fixtures |
| Reset | synthetic preserved+legacy DB | dry-run/unknown stop/closure/checkpoint/verified backup | digest reports |
| Seeders | fresh + second run + edited draft | idempotence، no published overwrite، production local-profile deny | SQL diff |
| Runtime isolation | browser + backend + native environment | لا production URL أو external outgoing أو secret logs | network assertions |
| Browser E2E | admin + provider web + Flutter customer | تسع رحلات، mixed cart، RTL/EN، responsive، errors/reload | screenshots/traces |
| Flutter | models/controllers/widgets/integration | nullable prices، nested forms، payment resume، deeplinks | tests الثلاثة |
| Build | Customer web + 3 Android debug apps | binaries من code النهائي؛ lock/toolchain مثبتة | build logs/artifacts |

## تنفيذ التزامن
لا تُحاكَ منافسة السعة بunit tests تسلسلية. افتح اتصالي MySQL منفصلين وtransactions مستقلتين مع barrier قبل commit؛ assert max capacity بعد اكتمالهما. اختبر أيضاً تعارض checkout/reschedule وcheckout/entitlement redemption وrefund/cancel. سجّل كل attempt/outcome، وتحقق من wallet/ledger/reservations بعد rollback/deadlock retry.

## السلامة والاسترجاع
اختبار failure injection بعد حفظ order وقبل gateway وبعد external success وقبل finalize وبعد ledger وقبل notification. تأكد أن request retry/outbox retry يصل إلى حالة متسقة دون تكرار. لا تترك rejection throw بعد قبض مثبت بلا payment exception record.

## الأداء المحلي
استعمل dataset صناعي محدود ثابت (مثلاً 100 عرض و500 حجز و20 فنياً) وسجل p50/p95 وعدد SQL queries وذاكرة العمليات. حد الأحمال معقول محلياً؛ لا تعد بنتيجة SLA إنتاج. افحص N+1 على calendar/order detail وbounded scans/query indexes وpagination. اجعل correctness حدوداً قاطعة؛ أي هدف زمن يعتمد على الجهاز يوثق لا يزوّر.

## مخرجات تقارير الاختبارات
results.json يحوي suite/case/status/duration/commit/environment/evidence/issue. junit أو مكافئ للعد الآلي؛ logs منقحة؛ SQL assertions لا تعرض بيانات شخصية. أدوات غير متاحة تسجل BLOCKED مع السبب والخطوات، لا SKIP يوصف نجاحاً. لا تحديث expected golden لتلائم bug؛ أي تغيير نموذج مالي يعاد اعتماده في spec والاختبار معاً.

## بوابة الإغلاق
جميع P0/P1 القابلة للاختبار محلياً ناجحة، أو LOCAL_BLOCKED مع قائمة واضحة. أي Native/External pending يظهر كبند قبول مستقل للمالك. إعادة تشغيل كل suites المتأثرة بعد آخر إصلاح ثم smoke شامل. تقرير يجمع «كتبت الاختبارات» مع «نفذت الاختبارات» دون تمييز غير مقبول.
