# S02 — استوديو الخدمات والمحتوى والنماذج
## المحتوى الإجباري عند النشر
code ثابت، اسم عربي/إنجليزي، وصف مختصر وطويل، صورة رئيسية وأيقونة، معرض صور وفيديو، includes نقاط مرتبة، why_us عناصر icon+title، FAQ question+answer، important_notice title+body اختيارية، شروط الخدمة، سياسة إلغاء، accessibility alt. الحقول الاختيارية تختفي بلا فراغات. النص HTML ينظف بقائمة مسموحة ولا ينفذ script. SVG للأيقونات من مكتبة موثوقة/تعقيم، لا SVG عميل خام.

أيقونة/صورة/اسم/وصف مختصر لكل متغير وعرض. الوسائط عامة للمحتوى التسويقي وخاصة لمرفقات العميل. غياب صورة لا يكسر البطاقة. تغيير اللغة لا يغيّر code ولا price hash. نسخ الخدمة يحفظ draft جديدة دون نسخ IDs أو روابط بيع منشورة.

## أنماط المكونات
`text`, `textarea`, `integer`, `decimal`, `select`, `multi_select`, `cards`, `boolean`, `counter`, `measurement`, `date_preference`, `media`, `group`, `repeater`, `asset_selector`, `address_selector`.
لكل حقل: `key`, labels, help, required, default, min/max, unit, options, visible_when, required_when, scope, sort. وجود required_when لا يسمح بتجاوز server validation. لا يحدد الأدمن أسعاراً في labels؛ الخيارات تربط rules بأكواد.

`scope=per_order` لملاحظات عامة؛ `per_item` للخدمة؛ `per_component` لجزء من باقة؛ `per_instance` لغرفة واحدة في repeater. يخزن كل instance بمعرف ثابت داخل الإجابات لمنع تبديل الغرفة الأولى بالثانية عند الحذف.

## الأسئلة المشروطة والمتكررة
مثال: property_type=villa، rooms_count=3 → قائمة rooms تضم 3 instances، لكل منها area_band/window_size/paint_level/cement_level. إذا انخفض العداد يحذر من حذف الإجابات الزائدة ثم يخزن النسخة الجديدة؛ لا يحسب السعر لإجابات مخفية قديمة. الحقول المخفية لا تكون required ولا تؤثر في السعر أو المدة؛ الخادم يطبع normalized_answers ويزيل أو يرفض مفاتيح مجهولة. `false` يختلف عن missing.

الشروط لغة محدودة: `all`, `any`, `not`, `eq`, `in`, `gt`, `gte`, `lt`, `exists`. لا JavaScript/PHP/eval أو SQL من الأدمن. اعتمادات الشروط DAG؛ يرفض نشر دورة أو مرجع لحقل غير موجود. max_depth=4، max_fields=200، max_repeat_instances=50 لكل مجموعة، max_total_answers=1000؛ حدود معتمدة في الإعداد وقابلة للخفض، لا payload غير محدود.

المساحات intervals لا تتداخل: مثال [0,20)، [20,30)، [30,60)، [60,+∞). لا ننقل حدود متداخلة من المرجع. درجات الأسمنت/الدهان codes none/light/medium/heavy، تعريف تأثيرها في rule مستقل. quantity للأكياس/الأجهزة ليست days أو hours.

## رحلة الحجز
خطوات مسموحة: content → configuration/offer → address → estimate → schedule → review → confirm، مع إظهار quote/inspection/entitlement مسارات بديلة. الترتيب يمكن تغييره ضمن اعتماد البيانات: schedule نهائي لا يسبق إجابات تؤثر في المدة أو الموقع. عرض calendar قبلها **استكشافي** يحمل flag غير ملزم؛ أي تغيير مؤثر يلغي quote/slot القديمة ويستدعي إعادة تقييم.

نقل الأثاث: اختيار شقة/دور/فيلا ليس إرسال quote. زر صريح «أرسل طلب التسعير» بعد البيانات. غسيل الملابس: خطة وسعة وتكرار وتفضيلات ثم الاستلام؛ الباقة قابلة للشراء دون كل المواعيد. خدمات ثابتة تدعم cart وbuy_now مع نفس evaluator.

## النشر والاعتماد
Draft save لا يجعل الخدمة متاحة. `validate` يعيد errors/warnings بمسارات fields. `simulate` ينفذ السعر والمدة باستخدام بيانات صناعية داخل process بلا booking/payment/outbox ولا إشعار. Publish يفحص: ترجمات، schema، سياسة سعر، سياسات إلغاء، أسعار صحيحة أو quote mode، مكونات باقة حقيقية، ساعات/مهارات/متطلبات، توافق خيارات الجدولة، FK صحيحة وموارد media متاحة.

نشر التعريف لا يفعّل الخدمة في كل المدن. city_offer enable يحتاج branch ready ومهارات وورديات وقدرة موجبة. يمكن draft service أن تظهر للأدمن ولو لم توجد مدينة جاهزة؛ لا تجعل readiness دورة تمنع إنشاء أول خدمة/تفعيل أول مدينة. افصل تعريف الجغرافيا والفرع عن commercial readiness؛ `CityBranchResolver` لا يعتمد على legacy service catalog بعد cutover.

## الإدارة بلا برمجة لكل خدمة
يبني الأدمن خدمة تنظيف خزانات غير واردة في seeds باستخدام نفس components. اختبار قبول إلزامي يثبت ذلك. لا شروط مثل `if service.name == 'غسيل الملابس'`؛ يتحدد السلوك بواسطة capabilities/workflow/template codes. إدخال component جديد تماماً مستقبلاً يحتاج تطويراً مشروعاً، لا وعد بمحرر قادر على كل شيء.

## المرفقات
القيم الافتراضية للاختبار: صور jpg/png/webp حتى 10MB، فيديو mp4 حتى 50MB؛ العدد والحدود في policy. التحقق من MIME الفعلي والامتداد والحجم وownership؛ روابط قراءة private قصيرة، حذف orphan uploads مؤجل آمن. لا يحذف media تبع booking عند حذف draft/cart. لا معلومات وصول R2 في Flutter؛ uploads عبر server/presigned flow محكوم بـpurpose وsize ثم finalize.
