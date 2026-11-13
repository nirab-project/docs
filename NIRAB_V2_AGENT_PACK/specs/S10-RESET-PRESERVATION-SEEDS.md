# S10 — حفظ التهيئة والتصفير والبيانات التأسيسية
## 1. معنى إذن الحذف
الإذن يسمح بإزالة البيانات التجارية التجريبية، وليس حذف قاعدة كاملة أو المساس بالتكاملات. لا نحتاج migration تحول كل booking قديم؛ نحتاج schema V2 نظيف وreset موثق يعمل في صيانة يدوية. أوامر reset لا تعمل عند deploy/restart/seed عادي. لا `migrate:fresh`/`db:wipe`/`database:refresh` على VPS، ولا تعطيل FK checks أو `TRUNCATE` عام.

## 2. خريطة الحماية
| المجموعة | السياسة الافتراضية |
|---|---|
| `.env`, APP_KEY, Passport/OAuth keys & clients, Firebase credentials, keystores, storage config, Dokploy env/volumes, queue/mail/SMS/gateway/Odoo keys | PRESERVE؛ لا يظهر secret في export/report؛ إنشاء مفتاح محلي جديد لا يغير الإنتاج |
| business_settings, addon_settings, provider settings, login/OTP config, notification templates, branding/landing/SEO | PRESERVE بعد جرد أسماء الجداول الحقيقي؛ بعض جداول settings تخزن أسراراً مشفرة |
| cities, city_boundary_versions, city_geographic_sources, city_branch_assignments, zones وpricing-zone links | PRESERVE بأكملها بما فيها raw_geojson/checksum/review metadata؛ لا seeds لإحداثيات إنتاج |
| providers, بيانات الاتصال/البنك/العنوان/ساعات العمل/capacity/approval، حسابات owners | PRESERVE؛ financial totals في جدول مختلط تصفر أعمدتها المحددة فقط |
| users داخليون، roles/scopes/permissions، servicemen/teams/team_members/shifts/leaves | PRESERVE مع closure لكل FK/reference في بيانات محمية |
| serviceman_skills وروابط خدمات الفروع | EXPORT/REMAP لا حذف مؤهلات دون بديل؛ مرجع الخدمات القديم يمسح فقط بعد mapping approved |
| services/variations/fields/pricing/old catalog/bundles/campaign sample targets | RESET_ALLOWED بعد أن تصبح المسارات التجارية V2؛ تعطيل بنر target مفقود لا حذف إعداد الهوية |
| carts/bookings/repeats/details/assignments/quotes/visit balances/customers test/reviews/test messages/operational reports | RESET_ALLOWED حسب allowlist وصفوف دون protected refs |
| payment requests/refunds/wallet/transactions/cash/odoo invoices/outbox | CLASSIFY: simulated يسوّى/يمسح؛ حقيقي أو مجهول أو pending خارجي quarantine ولا يحذف تلقائياً |
| Odoo mappings | SPLIT: journal/account/company/branch refs محمية؛ invoice/transaction refs تعامل مع external reconciliation لا حذف عشوائي |
| jobs/failed_jobs/outbox/notifications | QUIESCE/CLASSIFY؛ legacy operational jobs يعزل، لا retry عبر V2؛ auth/config jobs لا تحذف اعتباطياً |
| shared media/storage | لا مسح bucket؛ object allowlist التجاري بعد فحص كل مرجع؛ لا حذف شعار/حدود/ملفات identity |
| reset_runs/cutoff_fences/integration reconciliation audit | PRESERVE دائماً بعد إنشائها |

الخريطة ليست SQL جاهزاً. agent يكتشف جميع الجداول من migrations/models ثم يعيد inventory من INFORMATION_SCHEMA محلياً في PH12، والمالك يعيدها فعلياً على VPS. كل جدول/عمود جديد غير مصنف = STOP قبل apply.

## 3. الاحتفاظ بالحسابات الداخلية
ابدأ keep set بـsuper-admin/admin/موظفي الشركة/owners/servicemen و كل referenced user في cities/geographic approvals/provider settings/audits المحمية. احفظ closure ثم reset بقية customers المحددين. user_type وحده لا يكفي إن الحساب customer لكنه cited في protected row. لا تعيد إنشاء admin بكلمة مرور ثابتة أو تسمح حذف account المالك مع جلساته ثم تفقد الدخول.

لا تطبع hashes كلمات مرور أو مفاتيح، وتبقى export مملوكة للمالك خارج web root. مقارنة المحميات بالdigest المحسوب داخلياً لقيم canonical مرتبة؛ لا raw secrets في تقرير. جداول mixed الصفوف/الأعمدة لها masks/allowlist؛ لا aggregate rows count بديلاً عن التحقق من القيم.

## 4. أدوات CLI التي ينفذها الوكيل
| الأمر المقترح | السلوك المطلوب |
|---|---|
| `nirab:commerce-inventory --output=...` | read-only schema/FKs/mixed refs/queues/capabilities والconfig fingerprints منزوعة الأسرار |
| `nirab:commerce-reset --plan --manifest=... --output=...` | dry-run افتراضي؛ يحسب PK allowlists/counts وprotected closure وخطة حذف topological/بصمات البيئة |
| `nirab:commerce-reset --apply --plan-file=... --approve-hash=...` | action منفصل صريح؛ maintenance+owner gate+backup verified+matching fingerprint+pending external clear؛ لا bypass عام |
| `nirab:commerce-reset --resume=<run_id>` | يتبع journal/checkpoints بعد توقف، لا يعيد حذف جداول غير معتمدة؛ checks نفسها |
| `nirab:commerce-preservation-verify --before=... --after=...` | تطابق المحميات، orphan check، خروج nonzero على أي فرق غير مصرح |
| `nirab:commerce-seed --profile=reference|local --mapping=...` | reference drafts فقط؛ local synthetic actors/prices/cities/workforce، محظور إنتاجياً |
| `nirab:commerce-verify --mode=local|preflight|postcutover` | readiness للأكواد/schema/routes/الموارد/المال بلا اتصالات خارجية تلقائية |

اسم كل أمر مقترح؛ يسلم agent `--help` النهائي وrunbook مطابق بعد PH12. أداة apply تتطلب `APP_ENV`/DB identity/server UUID/dbname/generation مسجلة في plan، وعلامة manual owner mode مؤقتة في الإنتاج. ترفض connection خارجي في local. صلاحية plan قصيرة وتبطل عند تغير صفوف/نسخة/مخطط/حالة عملاء أو وصول معاملات جديدة. لا `--force` يسقط كل الحراس.

## 5. التنفيذ الآمن
quiesce writers/jobs/schedulers/FCM/outbox gateways؛ لا حذف مع app حي. baseline backup مع restore rehearsal منفصل، protected digest، external refs settlement، snapshot للمرفقات. MySQL DDL ليس transaction قابلة للتراجع مثل DML: فصل schema migrations الإضافية عن reset rows؛ cleanup DROP منفصل آخر خطوة يدوية بعد إثبات عدم الاعتماد. في reset استخدم transactions/chunks وjournal لكل وحدة واتساق FK؛ crash resumable. أية orphan أو protected diff توقف.

قد تتعارض city sources reviewed_by مع مستخدم تجريبي؛ default الاحتفاظ بالمرجع لا نزع FK. قد يبقى legacy table هيكلياً حفاظاً على config؛ لا يعد ذلك compatibility تجارية. protected tables أبداً ليست ضمن loops لحذف wildcard.

Old callbacks: quarantine/tombstones mappings لمنع orphan payment يعاد ربطه بorder جديد؛ لا إعادة استخدام external refs أو sequence محلي بطريق تصطدم مع Odoo. generation جديد لكل cutover. لا تمسح payment key mappings في الخارج بحجة تنظيف المحلي.

## 6. Seeders
- `CommercePermissionsSeeder`: إضافة permissions فقط لا reset roles.
- `CommerceReferenceCatalogSeeder`: 9 خدمات، content original، forms/offers/rules/policies مسودة، stable seed_key، update-only-owned-fields، لا overwrite تعديلات الأدمن المنشورة.
- `CommerceReferenceCapabilitiesSeeder`: registry مهارات جديدة مستقلة عن service names؛ remap مؤهلات الفنيين عبر mapping مراجعة، لا يمنح كل مهارة لكل فني.
- `CommerceLocalFixtureSeeder`: مدينتان صناعيّتان وحدود test-only ومصادر dummy، فرع لكل مدينة، حسابات test random/local credentials، فرق وورديات، محفظة fixture، بيانات سيناريوهات. يرفض APP_ENV غير local/testing وDB marker غير مثبت.
- `CommerceLocalScenarioSeeder`: orders/payment/events seeds للاختبار فقط، no external outbox dispatch.

الأرقام في `fixtures/catalog.seed-spec.json` توضيحية وغير معتمدة للبيع. reference production لا يحمل أسعار فعالّة إلا اعتماد المالك. لا نضع رقم هاتف/اسم شخص/رمز مصادقة من trace في seeds. لا نعتمد على DatabaseSeeder الحالي الفارغ لإعادة تكوين الشركة.
