# نيراب كلين — التقرير الموحد للأعمال المنفذة وجاهزية التشغيل

**المشروع:** NIRAB / نيراب كلين  
**تاريخ التقرير:** 7 أكتوبر 2026  
**موجّه إلى:** العميل، إدارة المشروع، وفريق التشغيل  
**مرجعية التقرير:** التقارير الأربعة المرفقة عن التنفيذ، الإغلاق، وحجم المصادر  
**الغرض:** تقديم مرجع واحد للتعديلات والإضافات المنفذة، وتغطية المنصات، وحجم العمل الموثق، وما يلزم لاعتماد الإطلاق.

> **الحالة التنفيذية:** اكتمل نطاق التطوير والإصلاحات البرمجية المحدد في تقارير الإغلاق. وتوثّق مرفقات الإغلاق اكتمال تجهيز النشر وتطبيق تحديثات قاعدة البيانات وتفعيل الوحدات الأساسية. انتقل المشروع إلى **مرحلة الاختبار اليدوي والقبول التشغيلي**؛ ولا يمثل هذا التقرير اعتماداً للإطلاق العام أو إثباتاً لنجاح جميع التكاملات الخارجية.[^closure][^counts]

هذا التقرير تجميع وتحرير للمرفقات، وليس مراجعة جديدة للشيفرة أو اختباراً جديداً للبيئة. ترد نتائج التنفيذ والفحص على أساس ما وثقته تلك المرفقات، مع الفصل بين إنجاز البرمجة واعتماد التشغيل.

---

## 1. الملخص التنفيذي

جرى تطوير النظام القائم وتخصيصه ليتوافق مع نموذج **نيراب كشركة واحدة متعددة الفروع**، بدلاً من منصة تجمع مزودين مستقلين وتتقاضى منهم عمولات أو اشتراكات. شمل ذلك الإدارة المركزية، وتشغيل الفروع والفرق والفنيين، ورحلة العميل من اختيار الخدمة إلى الحجز والتنفيذ والتحصيل والفاتورة.[^progress]

امتد العمل إلى **أربعة مشاريع مصدرية** تخدم لوحة الإدارة والنظام المركزي، وتطبيق العميل وموقعه الإلكتروني، وتطبيق مدير الفرع، وتطبيق الفني. جرى الاستفادة من محركات الحجز والمواعيد والدفع والمحفظة والباقات والمحاسبة القائمة وتطويرها، ولم يكن العمل إعادة بناء المنصة من الصفر أو إعادة كتابة جميع شاشاتها.[^progress][^counts]

تجمع هذه الوثيقة أعمال تخصيص نموذج التشغيل، وإغلاق الأمن والواجهات ودورة الطلب، وإصلاحات الدفع والترقيم، ثم تحسينات رحلة العميل والباقات وصفحات الخدمات وإصلاحات الإغلاق المرتبطة بها. **المتطلب المباشر الآن هو إثبات سلامة التشغيل عملياً، لا إعادة تنفيذ النطاق البرمجي المغلق.**[^progress][^closure]

## 2. الأقسام التي شملها التنفيذ

| القسم | أبرز ما شمله العمل |
|---|---|
| **النظام المركزي ولوحة الإدارة** | نموذج الشركة والفروع، الصلاحيات، الخدمات والتسعير، الحجوزات وعروض الأسعار، إدارة التشغيل والقوى العاملة، التحصيل والمحاسبة، الحماية، قاعدة البيانات وتجهيز التشغيل. |
| **تطبيق العميل وموقع العميل** | مداخل الخدمات والتسجيل والعودة للمسار، الحجز المباشر وطلب عرض السعر، المواعيد والدفع، الباقات، تفاصيل الخدمة ومعرض الصور، المتابعة والإشعارات وحالات الخدمة والدفع والفاتورة. |
| **تطبيق مدير الفرع** | الفرق والمهارات والدوام والإجازات، التغطية وإتاحة الخدمات، الإسناد وحالات الطلب والصلاحيات، إزالة قيود الاشتراكات القديمة، والتوافق مع زيارات الباقات وحمايتها. |
| **تطبيق الفني** | الموقع وحالات التنفيذ والعضوية في الفرق وصلاحيات الإجراءات، إثبات الخدمة بالصور والموقع ورمز العميل، وتوافق عرض زيارات الباقات ومنع تعديلها بصورة غير مسموحة. |

**ملاحظة نطاق:** تطبيق العميل وموقعه يشتركان في مشروع مصدري واحد؛ لذلك لا يُحسبان مشروعين أو تُضاعف أرقامهـما. اختلاف حجم التعديل بين الأقسام يعكس اختلاف أدوارها، وليس إهمال أحدها.[^scope][^counts]

## 3. مسارات العمل التي يجمعها التقرير

| مسار العمل | ما توثّقه المرفقات |
|---|---|
| **تخصيص المنصة والتشغيل** | ست مراحل تطوير أساسية، تلتها مراحل الإغلاق 7 و8 و9 للأمن والواجهات ودورة الطلب. |
| **تصحيحات الإغلاق N01–N03** | حماية بدء الدفع، معالجة المبالغ المحصلة عند تعثر إنشاء الحجز، وآلية ترقيم الحجوزات. |
| **تحسين رحلة العميل UX-01 إلى UX-06** | مداخل الطلب والدخول والمواعيد، عروض الأسعار والباقات، صفحات الخدمات والحجوزات والمتابعة والتوافق مع الفرع والفني. |
| **RELEASE-HOTFIX-01 وRELEASE-HOTFIX-02** | استكمال حماية المسارات القديمة وملكية الطلبات والعروض، والتحقق من الوحدات وإعداد بدء التشغيل. |

هذه مسارات موثقة بنطاقات مختلفة، وليست مجموعات ملفات مستقلة يمكن جمع أعدادها دون إزالة التكرار. وتُعرض أرقام كل نطاق منفصلة في قسم الإحصاءات.[^progress][^scope][^counts]

---

## 4. التعديلات والإضافات الوظيفية

### 4.1 نموذج الشركة والفروع والإيرادات

تمت مواءمة المزودين ليصبحوا **فروعاً رسمية لنيراب**، مع تعطيل العمولات واشتراكات الفروع وسحب أرباحها وفق نموذج المزودين المستقلين القديم. أصبحت إيرادات الطلبات منسوبة إلى الشركة ومصنفة حسب الفرع، دون معاملتها كمستحقات لشركات مستقلة أو احتساب الإيراد مرتين.[^progress]

أصبح نطاق الإدارة مركزياً لجميع الفروع، مع حصر مدير الفرع في فرعه، وتوزيع الصلاحيات على الفنيين وقادة الفرق والموظفين المخولين، وتسجيل العمليات الحساسة. وشمل التنظيف إزالة قيود الاشتراك القديمة من المسارات المعنية بتشغيل الفرع.[^progress][^scope]

### 4.2 الوصول إلى الفرع والتغطية الجغرافية

دُعم اختيار الفرع المناسب حسب موقع تنفيذ الخدمة، إلى جانب الحجز المباشر من فرع محدد. جرى الحفاظ على إظهار معلومات الفرع الرسمية وإمكانية التواصل معه، مع حماية بيانات الاتصال الخاصة دون إخفاء بيانات الفرع العامة.[^progress]

شمل العمل إتاحة الخدمات بحسب الفرع والمنطقة والحي، ومعالجة تطبيق الإتاحة على مستوى الحي، وتثبيت الفرع والمنطقة قبل اعتماد السعر. كما شمل تطبيق الفرع إدارة مناطق التغطية وإتاحة الخدمات ضمن نطاقه.[^progress][^scope]

### 4.3 كتالوج الخدمات والتسعير والبيانات المطلوبة

تم تطوير كتالوج خدمات مركزي، مع تحديد إتاحة كل خدمة حسب الفرع والمنطقة، وقواعد للتسعير حسب الفرع والحي وأوقات الذروة. وأُضيفت واجهات لإدارة قواعد الأسعار والحقول والبيانات المطلوبة لكل خدمة.[^progress]

جرى منع استمرار الاعتماد على أسعار أو مواعيد قديمة عندما يغيّر العميل العنوان أو الكمية أو الخدمة. وتظل **سياسة احتساب مدة الخدمة عند زيادة الكمية** من القرارات المطلوب اعتمادها تشغيلياً؛ فلا تعني هذه الحماية أن تلك السياسة حُسمت في المرفقات.[^closure][^progress]

### 4.4 رحلة العميل والدخول والحجز المباشر

وُحّدت مداخل طلب الخدمات: الخدمة ذات السعر المحدد تتجه إلى **الحجز المباشر**، والخدمة التي تتطلب تسعيراً تتجه إلى **طلب عرض سعر**. وحُفظ اختيار العميل أثناء تسجيل الدخول كي لا يفقد الخدمة أو الخطوة التي وصل إليها.[^closure]

شملت التحسينات اختيار المواعيد والتأكيد، ووضوح مسار الطلب، والتعامل مع المحاولات غير المكتملة وإعادة المحاولة بما يمنع إنشاء حجز أو دفع مكرر. جرى ربط هذه التحسينات بتطبيق العميل وموقعه ضمن المصدر المشترك.[^closure][^counts]

### 4.5 دورة عروض الأسعار والمعاينة

فُصل طلب عرض السعر عن السلة، مع دعم تفاصيل الطلب والصور والمعاينة، ثم إصدار العرض ومتابعة صلاحيته وقبوله وتحويله إلى حجز فعلي. كما شمل المسار معالجة خصم رسوم المعاينة **وفق التنفيذ الموثق**.[^progress][^closure]

أصبح السعر النهائي ومبلغ الدفع معتمدين من النظام المركزي، لا من قيمة يرسلها التطبيق. وأُغلقت المسارات القديمة الأضعف، وقُيّدت قائمة عروض العميل بطلباته هو، وعُطّل مسار إصدار العرض القديم من الفرع في وضع الفروع لصالح المسار الحديث.[^closure]

### 4.6 المواعيد والإسناد والتوفر

تم تحسين اختيار الموعد وربطه بالإتاحة الفعلية للفرع والفريق، وعرض المواعيد المتاحة، وعدم إبقاء موعد قديم معتمداً بعد تغيير بيانات تؤثر في التوفر.[^closure]

شمل التطوير اختيار الفني أو الفريق بحسب الأهلية والتوفر والمسافة وعبء العمل، مع دعم الإسناد التلقائي وفق الإعدادات. ودُعمت واجهات الفرع المعنية بالإتاحة والإسناد وحالات الطلب.[^progress][^scope]

### 4.7 الفنيون والفرق وإدارة القوى العاملة

دُعم تنفيذ الخدمة بواسطة فني واحد أو فريق بقيادة مسؤول، مع إدارة الأعضاء والمهارات والدوام والإجازات. وتم تحسين اختيار الفنيين بالأسماء بدلاً من المعرفات التقنية، وضبط صلاحيات القائد والأعضاء.[^progress]

امتدت الوظائف إلى تطبيق مدير الفرع لإدارة الفرق والتشغيل، وإلى تطبيق الفني للعضوية في الفرق والإجراءات المسموحة له، مع استمرار الإسناد والتنفيذ والحالات التشغيلية ضمن النظام القائم.[^scope][^closure]

### 4.8 باقات الزيارات والرصيد

قُدمت باقات الزيارات للعميل بصورة مستقلة وواضحة، مع دعم عدد الزيارات والرصيد المتبقي والصلاحية وسجل الاستخدام، وإمكانية حجز زيارة مباشرة من الباقة.[^closure]

أُضيفت ضوابط تمنع خصم الزيارة مرتين عند إعادة المحاولة، وتعيد الزيارة إلى الرصيد **مرة واحدة فقط عند الإلغاء المؤهل**، وتمنع تحويل الزيارة المغطاة بالباقة إلى حجز نقدي أو التلاعب بتغطيتها. وشملت الحماية عرض تفاصيل التغطية وضبط إتاحة تعديل الحجز في تطبيقي الفرع والفني وترجمة المعلومات الجديدة.[^closure][^counts]

### 4.9 متابعة الحجز والتتبع والإشعارات

تم تحسين صفحات تفاصيل الطلب والحجز، والفصل الواضح بين **حالة تنفيذ الخدمة، وحالة الدفع، وحالة الفاتورة**؛ فلا يُعامل اكتمال التنفيذ وحده كإثبات للسداد أو جاهزية الفاتورة.[^progress][^closure]

شمل العرض حالة الفريق والفني، وموقع الفني أثناء الطريق، ووقت الوصول التقديري، وآخر تحديث للموقع، مع حماية بيانات التتبع. كما حُسنت الإشعارات والروابط التي تفتح الطلب أو الحجز الصحيح. هذه تحسينات ضمن إمكانات التتبع الحالية، وليست بناء مزود جديد للمسارات المرورية.[^progress][^closure]

### 4.10 إثبات تنفيذ الخدمة

شمل المسار تسجيل بداية العمل ونهايته والموقع، والصور قبل التنفيذ وبعده، وقائمة المهام، ورمز تأكيد العميل. وتم الحفاظ على هذه الأدلة في تطبيق الفني، وربط الإجراءات بالصلاحيات وحالات التنفيذ.[^progress][^scope]

يوفّر ذلك أساساً لتوثيق ما تم ميدانياً مع بقاء الخدمة والدفع والفاتورة حالات مستقلة، ويحتاج المسار الكامل إلى تجربة تشغيلية فعلية ضمن القبول.[^progress]

### 4.11 التحصيل والمحفظة وعهدة الفني

تم تطوير إثبات التحصيل النقدي والتحويل البنكي، واعتماد مدير الفرع ثم المحاسب، ومتابعة عهدة الفني. كما جرى الاستفادة من محفظة العميل والدفع الجزئي، مع ضوابط تمنع تكرار الخصم.[^progress]

شمل الإغلاق أيضاً حماية جلسة الدفع والتحقق من الطلب والمبلغ ومعالجة المحاولات المتعثرة. أما جاهزية قنوات الدفع الخارجية للعملاء فتتطلب إعداد الحسابات ونجاح الاختبار الفعلي، ولا تستنتج من وجود الشيفرة أو تفعيل الوحدة وحدهما.[^progress][^scope]

### 4.12 المحاسبة والفواتير والتكاملات الخارجية

جُهز الربط مع **Odoo** لنقل البيانات المالية والفواتير والتحصيلات المعتمدة، مع متابعة التعثر وإعادة المحاولة. ويظل الاتصال الفعلي والفوترة السعودية عبر Odoo بحاجة إلى إعداد واختبار وقبول مستقل؛ ولا يثبت تجهيز الربط أن الفوترة الإلكترونية مفعلة أو معتمدة بالفعل.[^progress]

وبحسب التقرير التنفيذي، فإن التجهيز الخاص بـ **تابي وتمارا يعتمد على مسار Tap المتاح في المصدر**. إتاحته للعملاء مشروطة بتفعيل الخدمة في حساب التاجر ونجاح اختبارها؛ ولا تعرض المرفقات ذلك كاختبار ناجح لتكاملين مستقلين مباشرين.[^progress]

### 4.13 الجودة والضمان والتقارير والتواصل

تضمنت المراحل السابقة تجهيز أدوات الشكاوى والضمان والتقييمات، وباقات الزيارات والحجوزات المتكررة، وتقارير الفروع، وأساس تكامل الرسائل وواتساب. **الموصوف هنا تجهيز وظيفي وبرمجي**؛ أما تفعيل الخدمات الخارجية واعتماد سياسات هذه الوظائف وتجربتها فيدخل ضمن التشغيل التجريبي.[^progress]

وتظل سياسة دفع الحجوزات المتكررة ضمن القرارات الإدارية المطلوب اعتمادها، ولا يُفهم من وجود الحجوزات المتكررة أن جميع خيارات دفعها قد حُسمت.[^progress]

### 4.14 الواجهات والمحتوى واللغات والهوية

أُعيد تنظيم عرض الخدمات في الصفحة الرئيسية، وتحسين صفحة تفاصيل الخدمة ووضوح الأزرار ومسار الطلب، وإضافة **معرض صور للخدمة يمكن إدارته من لوحة التحكم**، مع الإبقاء على الوصف والأسئلة الشائعة والمراجعات.[^closure]

شمل العمل واجهات العميل والفرع والإدارة للمواعيد والتتبع والتشغيل، وتحديث العربية والإنجليزية، ومعالجة مشكلات تحميل الصور واختلاف نسخ المصدر. ونُفذت التصحيحات على النسخة النهائية مع المحافظة على الهوية المعتمدة، دون استبدال هوية نيراب أو نسخ هوية أو نصوص تطبيق آخر. وتبقى المطابقة البصرية النهائية جزءاً من القبول اليدوي.[^progress][^closure]

---

## 5. إغلاقات الأمان والاستقرار ومعالجة الاستثناءات

### 5.1 حماية بدء الدفع ومنع التكرار

أصبح بدء الدفع مرتبطاً بهوية موثوقة وجلسة آمنة، مع التحقق من ملكية الطلب والمبلغ. أُضيفت ضوابط لمنع تكرار المحاولات والخصم عند انقطاع الاتصال، إلى جانب حماية المحفظة وحجز زيارة الباقة من تكرار الأثر المالي أو خصم الزيارة.[^progress][^closure]

### 5.2 معالجة مبلغ محصل دون اكتمال الحجز

أُضيفت شاشة للإدارة لمعالجة الحالة التي يُحصّل فيها المبلغ ثم يتعثر إنشاء الحجز. يستطيع المسؤول المخول إعادة إتمام الحجز **دون تحصيل جديد**، أو طلب استرداد المبلغ، مع حفظ المرجع وسجل القرار. وجود خيار طلب الاسترداد لا يُعد بذاته إثباتاً لنجاح استرداد فعلي لدى البوابة.[^progress]

### 5.3 ترقيم الحجوزات

استُبدلت آلية الترقيم القديمة بآلية مصممة لمنع تكرار أرقام الحجوزات عند إنشاء طلبات متزامنة. وتوثق مرفقات الإغلاق تطبيق تحديث قاعدة البيانات الخاص بها. أما فحص البيانات السابقة والتحقق تحت تزامن فعلي فلا يُنسب إلى هذا التجميع كاختبار جديد.[^progress][^closure]

### 5.4 ملكية البيانات وإغلاق المسارات القديمة

أُضيف التحقق من ملكية العميل للطلبات والعناوين والعروض، وأُغلقت عمليات الكتابة القديمة في وضع الفروع. واكتمل إغلاق آخر مسارين موثقين: تقييد قائمة عروض العميل بطلباته فقط، وتعطيل إصدار العرض عبر المسار القديم للفرع في وضع الفروع.[^closure]

### 5.5 سلامة بدء التشغيل

أُضيف فحص يمنع بدء الإصدار إذا كانت الوحدات الأساسية ناقصة أو معطلة، مع المحافظة على إعدادات التشغيل الفعلية بدلاً من استخدام قالب إعدادات غير صالح للإنتاج. وشملت الإصلاحات ملف بدء التشغيل، وفاحص حالة الوحدات، وضبط نهايات الأسطر عبر `.gitattributes`.[^closure][^counts]

## 6. قاعدة البيانات وتجهيز النشر والثوابت المحفوظة

تؤكد مرفقات الإغلاق تطبيق جميع تحديثات قاعدة البيانات المطلوبة وتسجيل المهاجرات النهائية بحالة **`Ran`**، ومنها آلية أرقام الحجوزات الآمنة، وجدول منع تكرار حجز زيارة من الباقة، ودعم معرض صور الخدمات.[^closure][^counts]

كما توثق تفعيل الوحدات الرئيسية، بما يشمل الحجوزات والخدمات والدفع وعروض الأسعار والعملاء والفنيين وOdoo وغيرها. **تفعيل الوحدة داخل النظام لا يثبت نجاح اتصالها بخدمة خارجية.**[^closure][^counts]

حُفظت الأسرار ومفتاح التطبيق `APP_KEY` والهوية البصرية ومحرك قاعدة البيانات؛ وبقي المشروع على **MySQL 8 + Spatial** دون تغيير المحرك المعتمد.[^closure]

### توضيح اختلاف حالة قاعدة البيانات بين المرفقات

التقرير التنفيذي العام يذكر أن تطبيق المهاجرات لم يكن ضمن مهمة الإغلاق التي يلخصها، ولذلك يدرجها ضمن المتبقي آنذاك. في المقابل، يقدّم ملخص الإدارة وتقرير حجم تنفيذ UX تحديث حالة صريحاً يؤكد تطبيقها. **اعتمد هذا التقرير تحديث الحالة الموثق في مرفقي الإغلاق لإظهار قاعدة البيانات وتجهيز النشر كمكتملين، ولم يُبقِهما كعمل غير منفذ.** هذا التحديث لا يثبت تلقائياً اكتمال بناء الجوال أو اختبار الدفع وOdoo؛ فلكل منها دليل قبول مستقل.[^progress][^closure][^counts]

---

## 7. مستوى التحقق والحالة الحالية

### 7.1 الفحوص الموثقة

بحسب المرفقات، نجحت فحوص الشيفرة وتحليل التطبيقات الثلاثة، وبناء موقع العميل، و**ثمانية اختبارات دفع معزولة**. كما توثقت مراجعة ملفات PHP وDart والترجمات وJSON، وعدم ظهور أخطاء أو تحذيرات تعيق الإطلاق في الملفات المعدلة، ووجود إصلاحات الدفع والترقيم وربطها بالمسارات الفعلية.[^progress][^closure]

هذه نتائج منقولة عن التقارير، وليست اختبارات أُعيد تنفيذها عند إعداد هذا الملف. والفحص الساكن أو الاختبار المعزول لا يحل محل تجربة رحلة فعلية متكاملة.[^progress]

### 7.2 مصفوفة الحالة

| البند | الحالة الموثقة | دلالة الحالة وحدودها |
|---|---|---|
| تخصيص المنصة والإصلاحات البرمجية محل الإغلاق | **مكتمل ضمن النطاق المراجع** | ليس إعادة بناء لكل وظائف القالب، ولا شهادة قبول تشغيلي شامل. |
| رحلة العميل وعروض الأسعار والباقات والخدمات والمتابعة | **مكتمل برمجياً** | يلزم قبول الرحلات عملياً على البيئة المقصودة. |
| توافق تطبيق الفرع والفني مع رحلة العميل والباقات | **مكتمل ضمن النطاق** | لا يعني اكتمال بناء نسخ المتاجر أو قبولها. |
| إغلاق المسارات القديمة وحماية الملكية والدفع | **موثق في الإغلاق** | يظل اختبار السيناريوهات الفعلية جزءاً من القبول. |
| تجهيز النشر وتحديثات قاعدة البيانات وتفعيل الوحدات | **مكتمل بحسب تحديث الإغلاق** | لا يساوي فتح الخدمة للجمهور أو اعتماد الاتصال الخارجي. |
| تحليل التطبيقات الثلاثة وبناء موقع العميل | **نجاح موثق في تقرير الإغلاق** | لا يُعمّم على بناء Android وiOS. |
| اختبارات الدفع المعزولة | **8 اختبارات ناجحة موثقة** | ليست معاملات فعلية ناجحة لدى جميع بوابات الدفع. |
| بناء نسخ Android وiOS | **لا تثبت المرفقات اكتماله** | التقرير التنفيذي يذكر عدم استكماله في المهمة التي يلخصها. |
| الدفع الخارجي وOdoo والرسائل | **التجهيز موثق؛ القبول الفعلي غير مثبت** | يتطلب إعداد الحسابات والخدمات واختبار الاتصال والنتائج. |
| استيعاب الحمل المستهدف | **غير مثبت باختبار ضغط فعلي** | لا تدعم المرفقات ضمان عدد مستخدمين محدد. |
| الاختبار اليدوي التشغيلي | **المرحلة التالية** | يشمل الرحلات الأساسية والتكاملات واللغات والأدوار. |
| الإطلاق العام | **بعد نجاح القبول واعتماد الإدارة** | لا يُعلن اكتماله في هذا التقرير. |

مصادر مصفوفة الحالة: التقرير التنفيذي العام، وملخص الإغلاق، وتقريرا النطاق والحجم.[^progress][^closure][^scope][^counts]

---

## 8. حجم العمل والأرقام الموثقة

### 8.1 نطاق تحسين رحلة العميل والإصلاحين النهائيين

| المشروع | ملفات مراحل UX الست | ملفات النطاق بعد الإصلاحين |
|---|---:|---:|
| النظام المركزي ولوحة الإدارة | 22 | 27 |
| تطبيق العميل وموقعه | 40 | 40 |
| تطبيق مدير الفرع | 4 | 4 |
| تطبيق الفني | 4 | 4 |
| **الإجمالي** | **70** | **75** |

يمثل الرقم **75 ملفاً مختلفاً** شملها التعديل أو الإضافة في هذا النطاق المحدد، مع حساب الملف مرة واحدة وإن تكرر تعديله، ودون احتساب تقارير التسليم وسجلات الفحص. وهو **ليس إجمالي الملفات المعدلة منذ بداية المشروع**.[^counts]

أضاف الإصلاحان خمسة مسارات إلى نطاق السبعين: ملفا قائمة عروض العميل وإصدار عرض الفرع، وملف بدء التشغيل، وفاحص حالة الوحدات، وملف `.gitattributes`. ولا يعني ذلك إنشاء المسارات الخمسة جميعها من الصفر. أما `PostController` وترجمتا `journey` فكانت محسوبة مسبقاً ولم تُكرر.[^counts]

### 8.2 أرقام مراحل الإغلاق 7 و8 و9

| المرحلة | الرقم الوارد في تقريرها |
|---|---|
| المرحلة 7 | 136 ملفاً معدلاً، إضافة إلى ملف `SystemRouteController` الجديد؛ المجموع 137. |
| المرحلة 8 | 27 ملفاً معدلاً و15 ملفاً جديداً؛ المجموع 42. |
| المرحلة 9 | 70 ملفاً معدلاً و5 ملفات جديدة؛ المجموع 75. |

**لا تُجمع هذه الأعداد باعتبارها ملفات مختلفة** لأن الملف نفسه قد يكون عُدل في أكثر من مرحلة. كما أن رقم 75 في المرحلة 9 يخص نطاقها، وليس هو إحصاء 75 ملفاً الخاص بمراحل UX والإصلاحين.[^scope][^counts]

من القوائم المسماة أمكن توثيق **181 مساراً مختلفاً على الأقل**: 79 في الباكند والإدارة، و32 في العميل والويب، و64 في الفرع، و6 في الفني. هذه قائمة جزئية من المرحلتين 7 و8 والملفات الخمسة الجديدة المسماة في المرحلة 9؛ لا تشمل كل ملفات المرحلة 9 المعدلة، ولا كامل المراحل 1–6، ولا الدمج أو كامل التصحيحات اللاحقة N01–N03.[^scope]

**لا يجوز جمع 181 + 75 لإعلان إجمالي نهائي فريد**؛ فهما إحصاءان لنطاقين مختلفين، ولم تقدم المرفقات اتحاداً منزوع التكرار بينهما. أُبقيت المسارات الـ181 كاملة في الملحق الفني للرجوع إليها دون إطالة القراءة الإدارية.[^scope][^counts]

### 8.3 عدد الأسطر: محتوى الملفات وليس حجم الإضافات

| القياس الموثق | العدد |
|---|---:|
| المحتوى الكامل لملفات مراحل UX الست، وعددها 70 | 31,851 سطراً |
| المحتوى الكامل للملفات المتاحة من النطاق الموسع، وعددها 74 | 32,451 سطراً |
| الوصف التقريبي لحجم محتوى ملفات هذا النطاق | نحو 32.5 ألف سطر |

تتضمن هذه الأعداد الكود السابق والتعليقات والأسطر الفارغة والترجمات؛ **ليست عدد الأسطر الجديدة أو المعدلة**. الفرق بين 75 ملفاً في نطاق التنفيذ و74 ملفاً في عد الأسطر سببه أن `.gitattributes` موثق ضمن العمل، لكن نصه لم يكن ضمن ملفات الإحصاء المتاحة، فلم يُفترض له عدد أسطر. هذه ملاحظة قياس وليست إعادة فتح لتجهيز النشر.[^counts]

### 8.4 حجم المصادر المرفوعة كاملة

| القسم | ملفات PHP/Blade أو Dart الأساسية | الأسطر الفعلية | الأسطر غير الفارغة | كل الملفات داخل الأرشيف |
|---|---:|---:|---:|---:|
| النظام المركزي ولوحة الإدارة | 1,563 | 310,424 | 282,079 | 1,716 |
| تطبيق العميل وموقعه | 615 | 106,498 | 97,444 | 679 |
| تطبيق مدير الفرع | 529 | 84,998 | 75,524 | 569 |
| تطبيق الفني | 196 | 27,080 | 24,157 | 237 |
| **الإجمالي** | **2,903** | **529,000** | **479,204** | **3,201** |

هذه أرقام **المصدر الحالي المرفوع، بما فيه القالب الأصلي والتطويرات**، وليست حجم الأعمال المكتوبة من الصفر أو قياساً لجودة التنفيذ أو نسبة إنجاز المتطلبات.[^scope][^counts]

في الباكند يتكون العد من 1,115 ملف PHP و448 قالب Blade، وفي التطبيقات من ملفات Dart داخل `lib/`، وقد تتضمن ملفات مولدة. الأسطر الفعلية معدودة باستخدام `splitlines()` وتشمل الفراغ والتعليقات؛ والأسطر غير الفارغة تستبعد الفراغ فقط، فلا تمثل كوداً منطقياً خالصاً.[^scope]

رقم 2,903 لا يشمل JavaScript وCSS وJSON وملفات الإعداد والقفل والصور والخطوط وملفات بناء المنصات. أما 3,201 فيشمل جميع الملفات داخل الأرشيفات الأربعة، وليس جرد الجهاز الكامل أو حزم النشر ومكتبات الطرف الثالث غير المرفوعة. وملفات Docker المرفوعة منفردة ليست مضافة إلى عد ملفات الأرشيفات.[^scope][^counts]

### 8.5 ما يلزم لإحصاء مساهمة التطوير بدقة

المرفقات لا تثبت العدد الإجمالي للأسطر المضافة والمحذوفة، ولا الإجمالي الفريد لجميع الملفات التي تغيرت منذ بداية المشروع. يتطلب ذلك مقارنة سجل Git بين مرجع قبل بدء جميع التعديلات والمرجع النهائي في كل مستودع، مع إزالة تكرار الملفات وفصل التنسيق والكود المولد. ولا تكفي معرفات النسخ قبل N01–N03 وحدها كخط أساس لكل التطوير.[^scope]

**الخلاصة العددية المعتمدة:** 75 ملفاً مختلفاً لنطاق UX والإصلاحين، و181 مساراً مختلفاً على الأقل ضمن قوائم إغلاق جزئية أخرى، و529 ألف سطر لحجم المصدر المرفوع الكامل. لكل رقم تعريف مستقل، ولا يُنسب حجم المصدر كله إلى التعديلات الجديدة.[^counts][^scope]

---

## 9. الأثر المتوقع على العمل

تدعم التعديلات وضوح رحلة العميل وتقليل فقدان اختياراته بين التسجيل والحجز، وتوفر مساراً واضحاً للخدمات المسعرة مباشرة والخدمات التي تحتاج عرض سعر. كما تضيف عرضاً وإدارةً أوضح لباقات الزيارات والخدمات ومحتواها وصورها.[^closure]

على مستوى التشغيل، تعزز إدارة الفروع والفرق والفنيين والتغطية والإسناد وتوثيق التنفيذ، وتفصل حالة الخدمة عن التحصيل والفاتورة. وعلى مستوى الحماية، تضيف ضوابط لمنع الوصول غير المسموح، وتكرار الحجوزات والمدفوعات وخصم الزيارات، وتقلل مخاطر بدء إصدار بإعدادات أو وحدات غير صالحة.[^progress][^closure]

**هذه آثار مستهدفة وظيفياً وليست نسب تحسن مقاسة بعد الإطلاق**؛ فالمرفقات لا تقدم مؤشرات فعلية عن التحويل أو الإيراد أو انخفاض الأخطاء بعد التشغيل.

## 10. حدود النطاق وما لا يشمله إعلان الإنجاز

لم يشمل هذا الإغلاق بناء دورة مستقلة لخدمات الاستلام والإرجاع متعددة المراحل، مثل استلام الملابس ثم إعادتها، أو بناء مزود تتبع مروري جديد. ولم يشمل استبدال هوية نيراب أو نسخ تصميم تطبيق آخر حرفياً، أو التحويل إلى محرك قاعدة بيانات مختلف.[^closure]

كذلك لا يعني شمول الأقسام الأربعة تنفيذ كل وظيفة داخل كل تطبيق أو إعادة كتابة كل شاشة؛ تتوزع الوظائف حسب الدور. ولا يشمل ادعاء الإكمال تكاملات جديدة خارج الخطة، أو قبول الدفع وOdoo والرسائل فعلياً دون دليل، أو إثبات قدرة النظام على حمل مستخدمين لم يُختبر.[^scope][^counts][^progress]

## 11. القبول التشغيلي والخطوة التالية

### 11.1 نطاق القبول المطلوب

الخطوة التالية هي اختبار يدوي منظم على البيئة المنشورة، يغطي الرحلة كاملة بمشاركة الأدوار المعنية. يجمع الجدول التالي نطاق الاختبارات والمسؤوليات المذكورة في المرفقات؛ وهو **خطة قبول، وليس سجلاً لاختبارات ناجحة**.[^closure][^progress]

| محور القبول | ما ينبغي التحقق منه | الجهة المعنية |
|---|---|---|
| **الدخول وتجربة العميل** | التسجيل والدخول، حفظ اختيار الخدمة، الحجز المباشر، العربية والإنجليزية، العرض البصري والإشعارات وفتح الحجز الصحيح. | الفريق الفني والتشغيل. |
| **الخدمات والتغطية والمواعيد** | بيانات الفروع والمناطق والأسعار، إتاحة الخدمة والموعد، وتحديث الاختيارات عند تغيير بيانات الطلب. | التشغيل، بمساندة الفريق الفني. |
| **عروض الأسعار والباقات** | طلب العرض وإصداره وقبوله وتحويله إلى حجز؛ حجز زيارة من الباقة والإلغاء المؤهل واسترجاع الزيارة دون تكرار. | التشغيل ومديرو الفروع والفريق الفني. |
| **الدفع والتحصيل والاسترداد** | المعاملة الفعلية، المحفظة والدفع الجزئي، الكاش والتحويل والاعتمادات، معالجة التعثر والاسترداد، وعدم تكرار التحصيل. | المالية والفريق الفني والتشغيل. |
| **الإسناد والتنفيذ والمتابعة** | إسناد فني أو فريق، الصلاحيات، حالات التنفيذ والتتبع، وأدلة بدء الخدمة وإنهائها وتأكيد العميل. | مديرو الفروع والفنيون والتشغيل. |
| **الفاتورة والتكاملات** | إعداد الدفع وOdoo والرسائل، ثم التحقق من الاتصال والنتيجة الفعلية والفاتورة والتحصيلات المعتمدة. | المالية والتشغيل والفريق الفني. |
| **إصدارات الجوال والتجهيز التشغيلي** | إثبات جاهزية نسخ الجوال المطلوبة، وتوثيق النسخ الاحتياطي والخدمات الآلية ومطابقة الإصدار المستخدم للقبول. | فريق التطوير والنشر. |
| **قرار الإطلاق** | اعتماد نتائج التجربة والسياسات، ثم الموافقة على فتح الخدمة للجمهور. | الإدارة. |

لا يعيد بند التجهيز التشغيلي طلب تطبيق المهاجرات المغلقة؛ بل يفصل اكتمالها الموثق عن بقية أدلة التشغيل التي لا تقدم المرفقات تأكيداً مستقلاً على اكتمالها.[^progress][^closure]

### 11.2 القرارات التي تحتاج اعتماداً

يلزم اعتماد **سياسة دفع الحجوزات المتكررة** و**طريقة احتساب مدة الخدمة عند زيادة الكمية**، إلى جانب سياسات الوظائف التشغيلية المعنية. كما تتطلب الخدمات الخارجية الحسابات والإعدادات والتفعيل اللازم قبل اعتماد نتائجها.[^progress]

### 11.3 التوصية النهائية

**اعتماد انتهاء النطاق البرمجي الموثق، والانتقال إلى قبول تشغيلي منظم، ثم اتخاذ قرار الإطلاق العام بعد نجاح الاختبارات واعتماد الإدارة.** لا تُعاد فتح الأعمال المغلقة لمجرد أن تقارير سابقة كانت تسجلها كمتبقية، ولا يُستنتج اكتمال اختبار غير موثق من اكتمال الشيفرة أو المهاجرات.[^progress][^closure][^counts]

---

## ملحق أ — سجل المسارات الموثقة في تقارير الإغلاق

القائمة التالية تحتفظ بالمسارات الـ181 المسماة في تقرير حجم المصادر، موزعة حسب المشروع. هي **قائمة جزئية منقولة عن التقارير**، وليست حصراً لكل ملفات المشروع المعدلة، ولا إثباتاً بأن كل ملف يطابق حرفياً تقريراً أقدم. لا تُضاف أعدادها إلى نطاق UX دون مقارنة وإزالة التكرارات.[^scope]

<details>
<summary>النظام المركزي ولوحة الإدارة — 79 مساراً</summary>

```text
Admin Panel/.env.example
Admin Panel/Modules/AdminModule/Http/Controllers/Web/Admin/Analytics/SearchController.php
Admin Panel/Modules/AdminModule/Resources/views/layouts/partials/_aside.blade.php
Admin Panel/Modules/AdminModule/Resources/views/quality/index.blade.php
Admin Panel/Modules/AdminModule/Routes/web.php
Admin Panel/Modules/Auth/Http/Controllers/Api/V1/LoginController.php
Admin Panel/Modules/Auth/Http/Controllers/Api/V1/RegisterController.php
Admin Panel/Modules/Auth/Http/Controllers/RegisterController.php
Admin Panel/Modules/Auth/Http/Controllers/Web/PasswordResetController.php
Admin Panel/Modules/Auth/Http/Controllers/Web/VerificationController.php
Admin Panel/Modules/BidModule/Http/Controllers/APi/V1/Provider/PostBidController.php
Admin Panel/Modules/BookingModule/Entities/Booking.php
Admin Panel/Modules/BookingModule/Entities/BookingRepeat.php
Admin Panel/Modules/BookingModule/Http/Controllers/Api/V1/Provider/BookingController.php
Admin Panel/Modules/BookingModule/Http/Controllers/Api/V1/Provider/DispatchController.php
Admin Panel/Modules/BookingModule/Http/Controllers/Web/Admin/BookingController.php
Admin Panel/Modules/BookingModule/Http/Controllers/Web/Provider/BookingController.php
Admin Panel/Modules/BookingModule/Http/Traits/BookingTrait.php
Admin Panel/Modules/BookingModule/Listeners/SendBookingRequestEmail.php
Admin Panel/Modules/BookingModule/Resources/views/provider/booking/list.blade.php
Admin Panel/Modules/BookingModule/Routes/api/v1/api.php
Admin Panel/Modules/BookingModule/Services/BookingWorkflowService.php
Admin Panel/Modules/BookingModule/Services/DispatchEngine.php
Admin Panel/Modules/BusinessSettingsModule/Http/Controllers/Web/Admin/ConfigurationController.php
Admin Panel/Modules/BusinessSettingsModule/Http/Controllers/Web/Admin/SubscriberController.php
Admin Panel/Modules/BusinessSettingsModule/Http/Controllers/Web/Admin/SubscriptionPackageController.php
Admin Panel/Modules/BusinessSettingsModule/Http/Controllers/Web/Provider/SubscriptionPackageController.php
Admin Panel/Modules/BusinessSettingsModule/Providers/BusinessSettingsModuleServiceProvider.php
Admin Panel/Modules/CustomerModule/Http/Controllers/Web/Admin/CustomerController.php
Admin Panel/Modules/PaymentModule/Lib/PaymentResponse.php
Admin Panel/Modules/PaymentModule/Lib/PaymentSuccess.php
Admin Panel/Modules/PaymentModule/Routes/api.php
Admin Panel/Modules/PaymentModule/Traits/SmsGateway.php
Admin Panel/Modules/ProviderManagement/Http/Controllers/Api/V1/Provider/AccountController.php
Admin Panel/Modules/ProviderManagement/Http/Controllers/Api/V1/Provider/ConfigController.php
Admin Panel/Modules/ProviderManagement/Http/Controllers/Api/V1/Provider/ProviderController.php
Admin Panel/Modules/ProviderManagement/Http/Controllers/Web/Admin/ProviderController.php
Admin Panel/Modules/ProviderManagement/Http/Controllers/Web/Admin/SubscriptionController.php
Admin Panel/Modules/SMSModule/Lib/SMS_gateway.php
Admin Panel/Modules/ServiceManagement/Http/Controllers/Api/V1/Admin/BranchServiceAvailabilityController.php
Admin Panel/Modules/ServiceManagement/Http/Controllers/Api/V1/Admin/PricingRuleController.php
Admin Panel/Modules/ServiceManagement/Http/Controllers/Api/V1/Admin/ServiceFieldController.php
Admin Panel/Modules/ServiceManagement/Http/Controllers/Web/Admin/OperationsController.php
Admin Panel/Modules/ServiceManagement/Http/Controllers/Web/Provider/ServiceController.php
Admin Panel/Modules/ServiceManagement/Resources/views/admin/operations-workforce.blade.php
Admin Panel/Modules/ServiceManagement/Resources/views/admin/operations.blade.php
Admin Panel/Modules/ServiceManagement/Routes/api/v1/api.php
Admin Panel/Modules/ServiceManagement/Routes/web.php
Admin Panel/Modules/ServicemanModule/Http/Controllers/Api/V1/Provider/ServicemanController.php
Admin Panel/Modules/ServicemanModule/Http/Controllers/Api/V1/Serviceman/ServicemanController.php
Admin Panel/Modules/ServicemanModule/Routes/api/v1/api.php
Admin Panel/Modules/ServicemanModule/Routes/web.php
Admin Panel/Modules/TransactionModule/Entities/Account.php
Admin Panel/Modules/UserManagement/Database/Seeders/UserTableSeederTableSeeder.php
Admin Panel/Modules/UserManagement/Entities/User.php
Admin Panel/Modules/UserManagement/Http/Controllers/Api/V1/OTPVerificationController.php
Admin Panel/Modules/UserManagement/Http/Controllers/Api/V1/PasswordResetController.php
Admin Panel/app/Console/Commands/DatabaseRefresh.php
Admin Panel/app/Console/Commands/FreeTrialEnd.php
Admin Panel/app/Console/Commands/SendRenewalReminderEmail.php
Admin Panel/app/Console/Commands/SubscriptionTimeEnd.php
Admin Panel/app/Http/Controllers/QualityAdministrationController.php
Admin Panel/app/Http/Controllers/SystemRouteController.php
Admin Panel/app/Http/Middleware/EnforceWorkforceManagement.php
Admin Panel/app/Http/Middleware/ProtectPersonalData.php
Admin Panel/app/Providers/AppServiceProvider.php
Admin Panel/app/Providers/AuthServiceProvider.php
Admin Panel/app/Services/OperationalAccess.php
Admin Panel/bootstrap/app.php
Admin Panel/public/assets/admin-module/js/nirab-operations.js
Admin Panel/public/assets/admin-module/js/nirab-workforce.js
Admin Panel/resources/lang/ar/lang.php
Admin Panel/resources/lang/ar/operations.php
Admin Panel/resources/lang/en/lang.php
Admin Panel/resources/lang/en/operations.php
Admin Panel/routes/api.php
Admin Panel/routes/install.php
Admin Panel/routes/update.php
Admin Panel/routes/web.php
```

</details>

<details>
<summary>تطبيق العميل وموقعه — 32 مساراً</summary>

```text
User app and web/lib/common/repo/data_sync_repo.dart
User app and web/lib/common/widgets/custom_image.dart
User app and web/lib/common/widgets/not_found_screen.dart
User app and web/lib/common/widgets/notification_helper.dart
User app and web/lib/common/widgets/service_center_dialog.dart
User app and web/lib/common/widgets/zoom_image.dart
User app and web/lib/feature/address/view/add_address_screen.dart
User app and web/lib/feature/auth/controller/auth_controller.dart
User app and web/lib/feature/auth/controller/facebook_login_controller.dart
User app and web/lib/feature/auth/repository/auth_repo.dart
User app and web/lib/feature/auth/widgets/file_upload_field_widget.dart
User app and web/lib/feature/booking/controller/invoice_controller.dart
User app and web/lib/feature/booking/view/booking_details_screen.dart
User app and web/lib/feature/booking/view/repeat_booking_details_screen.dart
User app and web/lib/feature/booking/widget/booking_item_card.dart
User app and web/lib/feature/booking/widget/booking_workflow_panel.dart
User app and web/lib/feature/booking/widget/repeat/repeat_booking_service_log_widget.dart
User app and web/lib/feature/checkout/controller/checkout_controller.dart
User app and web/lib/feature/checkout/view/payment_screen.dart
User app and web/lib/feature/checkout/widget/order_details_section/available_repeat_time_picker.dart
User app and web/lib/feature/checkout/widget/order_details_section/available_slot_picker.dart
User app and web/lib/feature/conversation/view/conversation_details_screen.dart
User app and web/lib/feature/language/controller/localization_controller.dart
User app and web/lib/feature/location/widget/pickmap_dialog_widget.dart
User app and web/lib/feature/profile/widget/update_additional_files_widget.dart
User app and web/lib/feature/provider/view/become_a_provider.dart
User app and web/lib/helper/analytics/analytics_helper.dart
User app and web/lib/helper/analytics/tiktok_analytics.dart
User app and web/lib/helper/country_code_helper.dart
User app and web/lib/helper/file_validation_helper.dart
User app and web/lib/helper/phone_verification_helper.dart
User app and web/lib/helper/route_helper.dart
```

</details>

<details>
<summary>تطبيق مدير الفرع — 64 مساراً</summary>

```text
Provider app/assets/language/ar.json
Provider app/assets/language/en.json
Provider app/lib/common/model/config_model.dart
Provider app/lib/feature/auth/controller/sign_up_controller.dart
Provider app/lib/feature/auth/repository/auth_repo.dart
Provider app/lib/feature/auth/view/sign_up_screen.dart
Provider app/lib/feature/auth/widgets/file_upload_field_widget.dart
Provider app/lib/feature/booking_details/controller/booking_details_controller.dart
Provider app/lib/feature/booking_details/controller/invoice_controller.dart
Provider app/lib/feature/booking_details/repo/booking_details_repo.dart
Provider app/lib/feature/booking_details/widget/assign_serviceman_screen.dart
Provider app/lib/feature/booking_details/widget/booking_workflow_panel.dart
Provider app/lib/feature/booking_details/widget/regular_booking/booking_details.dart
Provider app/lib/feature/booking_details/widget/repeat_booking/repeat_booking_details.dart
Provider app/lib/feature/booking_details/widget/repeat_booking/repeat_booking_service_log.dart
Provider app/lib/feature/booking_requests/controller/calendar_controller.dart
Provider app/lib/feature/booking_requests/widgets/booking_request_item.dart
Provider app/lib/feature/dashboard/view/dashboard_screen.dart
Provider app/lib/feature/dashboard/view/payment_screen.dart
Provider app/lib/feature/dashboard/widgets/business_summery_section.dart
Provider app/lib/feature/dashboard/widgets/earning_statistics_widget.dart
Provider app/lib/feature/dashboard/widgets/my_subscription_section.dart
Provider app/lib/feature/location/view/update_customer_address.dart
Provider app/lib/feature/menu/view/menu_screen.dart
Provider app/lib/feature/nav/bottom_nav_screen.dart
Provider app/lib/feature/payement_information/controller/payment_info_controller.dart
Provider app/lib/feature/payement_information/view/add_payment_info_screen.dart
Provider app/lib/feature/payement_information/view/payment_information_screen.dart
Provider app/lib/feature/profile/controller/user_controller.dart
Provider app/lib/feature/profile/view/account_information/view/account_information.dart
Provider app/lib/feature/profile/view/bank_information/controller/bank_info_controller.dart
Provider app/lib/feature/profile/view/view/profile_screen.dart
Provider app/lib/feature/profile/widgets/update_additional_files_widget.dart
Provider app/lib/feature/reporting/controller/booking_report_controller.dart
Provider app/lib/feature/reporting/controller/business_report_controller.dart
Provider app/lib/feature/reporting/controller/transaction_report_controller.dart
Provider app/lib/feature/reporting/view/business_report.dart
Provider app/lib/feature/reporting/view/report_navigation_view.dart
Provider app/lib/feature/reporting/view/transaction_report.dart
Provider app/lib/feature/reporting/widgets/booking_report/booking_report_bar_chart.dart
Provider app/lib/feature/review/controller/review_controller.dart
Provider app/lib/feature/serviceman/operations/operations_api.dart
Provider app/lib/feature/serviceman/operations/team_editor.dart
Provider app/lib/feature/serviceman/operations/workforce_editors.dart
Provider app/lib/feature/serviceman/view/branch_operations_screen.dart
Provider app/lib/feature/serviceman/view/branch_service_areas_screen.dart
Provider app/lib/feature/serviceman/view/branch_service_availability_screen.dart
Provider app/lib/feature/serviceman/view/serviceman_setup_screen.dart
Provider app/lib/feature/serviceman/view/team_management_screen.dart
Provider app/lib/feature/serviceman/view/technician_operations_screen.dart
Provider app/lib/feature/serviceman/widget/add_new_serviceman_acount_info.dart
Provider app/lib/feature/splash/controller/splash_controller.dart
Provider app/lib/feature/subscriptions/controller/business_subscription_controller.dart
Provider app/lib/feature/subscriptions/controller/subscription_invoice_controller.dart
Provider app/lib/feature/subscriptions/view/business/business_plan_screen.dart
Provider app/lib/feature/subscriptions/widget/business/subscription_transaction_listview.dart
Provider app/lib/feature/transaction/controller/transaction_controller.dart
Provider app/lib/feature/transaction/view/withdraw_list_screen.dart
Provider app/lib/helper/country_code_helper.dart
Provider app/lib/helper/file_validation_helper.dart
Provider app/lib/helper/notification_helper.dart
Provider app/lib/helper/route_helper.dart
Provider app/lib/helper/validation_helper.dart
Provider app/lib/main.dart
```

</details>

<details>
<summary>تطبيق الفني — 6 مساراً</summary>

```text
Service man app/lib/feature/auth/repository/auth_repo.dart
Service man app/lib/feature/booking_details/controller/invoice_controller.dart
Service man app/lib/feature/booking_details/widget/booking_details_widget.dart
Service man app/lib/helper/file_validation_helper.dart
Service man app/lib/helper/notification_helper.dart
Service man app/lib/helper/validation_helper.dart
```

</details>

## ملحق ب — بصمات نسخ المصادر المذكورة في الإحصاء

<details>
<summary>عرض بصمات الأرشيفات الأربعة للمرجعية الفنية</summary>

البصمات التالية منقولة كما وردت في تقرير حجم المصادر؛ لم يُجر حساب جديد للبصمات أو تدقيق جديد للأرشيفات أثناء إعداد هذا التقرير.[^scope]

**`01-NIRAB-BACKEND-SOURCE.zip`**

```text
e3be5a7f53823c7b2483367de5b31393dfcc47df6c36d7d9289d10ccc1646de8
```

**`02-NIRAB-CUSTOMER-SOURCE.zip`**

```text
db5f8a66b969f1a2d9730611d3b87a453f0a44196f379e62d0fe2a2bc9f10d1d
```

**`03-NIRAB-PROVIDER-SOURCE.zip`**

```text
31d40f4ce2248d5e674d31919abc809fc7c016431a1883788554e10ee9b30267
```

**`04-NIRAB-SERVICEMAN-SOURCE.zip`**

```text
ef0b81a2250916995b439e80a02d03d9290578ff151636d1603f2967fee7aaa5
```

</details>

## ملحق ج — رسالة مختصرة للنشر في مجموعة المشروع

تُستبدل عبارة `[رابط التقرير على GitHub]` برابط هذا الملف بعد رفعه إلى المستودع.

```text
السلام عليكم جميعاً،

أرفق لكم التقرير الموحد للأعمال المنفذة في مشروع نيراب كلين، ويجمع التعديلات والإضافات على لوحة الإدارة والنظام المركزي، وتطبيق العميل وموقعه، وتطبيق مدير الفرع، وتطبيق الفني.

يشمل التقرير تخصيص النظام لشركة متعددة الفروع، وتحسين الحجز وعروض الأسعار والمواعيد، وباقات الزيارات، وإدارة الفرق والتنفيذ والتحصيل، وتجهيز الربط المحاسبي، وتطوير الواجهات، وتعزيز الأمان وحماية الدفع، مع توضيح حجم العمل وحدود الاختبارات المنجزة.

الحالة الحالية: اكتمل نطاق التطوير والإصلاحات الموثق، وتجهيز النشر وتحديثات قاعدة البيانات. والمرحلة التالية هي الاختبار اليدوي والقبول التشغيلي والتحقق من التكاملات قبل اعتماد الإطلاق العام.

التقرير الكامل على GitHub:
[رابط التقرير على GitHub]

نرجو الاطلاع على التفاصيل، وخاصة قسم القبول التشغيلي والمسؤوليات المتبقية، لتوحيد الصورة لدى جميع أعضاء المشروع.
```

---

## مراجع التقرير ومنهج التوثيق

استند إعداد هذا الملف حصراً إلى المرفقات الأربعة أدناه. المراجع التنفيذية وسجلات الفحص والأرشيفات التي تسميها تلك المرفقات هي مراجعها الأصلية؛ لا يعني ذكر نتائجها هنا أنها فُتحت أو فُحصت أو شُغّلت من جديد عند إعداد هذا التقرير. لم تُستخدم معلومات خارجية لإضافة أعمال غير مذكورة في المصادر.

[^progress]: **التقرير التنفيذي العام:** `NIRAB_EXECUTIVE_PROGRESS_SUMMARY_2026-10-07(1).md`، بتاريخ 7 أكتوبر 2026. مرجع نموذج الشركة والفروع، المجالات الوظيفية، إصلاحات الدفع والترقيم، الفحوص الموثقة، قيود التكاملات، ومسؤوليات القبول والقرارات الإدارية. يسجل حالة المهاجرات وبناء الجوال كما كانت في المهمة التي يلخصها.

[^closure]: **ملخص الإدارة والإغلاق:** `NIRAB_MANAGEMENT_EXECUTIVE_SUMMARY_2026-10-07(1).md`، بتاريخ 7 أكتوبر 2026. مرجع تفاصيل رحلة العميل والباقات والواجهات والتوافق، إغلاق المسارات القديمة، بوابة التشغيل، وتحديث حالة المهاجرات إلى `Ran` وتفعيل الوحدات، مع بقاء الاختبار اليدوي قبل الإطلاق العام.

[^counts]: **حجم تنفيذ UX وتغطية الأقسام:** `NIRAB_IMPLEMENTATION_COUNTS_2026-10-07(1).md`، بتاريخ 7 أكتوبر 2026. مرجع نطاق UX-01 إلى UX-06 وRELEASE-HOTFIX-01 وRELEASE-HOTFIX-02، وإحصاء 70 ثم 75 ملفاً، و31,851 و32,451 سطراً، وحدود احتساب `.gitattributes` وحجم الأرشيفات. يحيل إلى تقارير UX والإصلاحات وسجل حالة المهاجرات.

[^scope]: **حجم المصادر وحدود قياس التعديلات:** `NIRAB_SOURCE_SIZE_AND_SCOPE_2026-10-07(1).md`، بتاريخ 7 أكتوبر 2026. مرجع أعداد مراحل 7 و8 و9، والقائمة الجزئية ذات 181 مساراً، وحجم المصدر البالغ 2,903 ملفات أساسية و529,000 سطر، وطريقة القياس والبصمات وحدود مقارنة Git وتغطية الأقسام.
