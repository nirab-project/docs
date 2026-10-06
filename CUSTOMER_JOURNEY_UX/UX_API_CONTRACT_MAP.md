# UX — خريطة عقود رحلة العميل

التاريخ: 2026-10-07. تتبع ساكن للمصدر النهائي على main، وليس نتائج استدعاءات API. بادئة الجداول `/api/v1`، ومغلف الاستجابة المعتاد `response_code/message/content`. العميل والويب من المشروع نفسه.

## الخدمة والدخول وطلب العرض

| الخطوة | العقد/الرمز | المدخل ومصدره | الاستجابة والمستهلك | الحماية/التوافق |
|---|---|---|---|---|
| فتح خدمة | GET customer/service/detail/{slug} | slug فقط من الرابط | Service.pricing_type, active_fields, variations, FAQ, HTML, gallery_image_urls | ServiceActionHelper يقرر quote/fixed؛ CreatePostScreen يعيد تحميل الخدمة بعد الدخول |
| العودة من الدخول | RouteHelper.getSignInRoute + redirect parser | مسار محلي يتضمن service وaction=quote، أو checkout | نفس المسار بعد نجاح الدخول بما فيه social login | URI encoding، رفض scheme/authority ومسارات auth؛ يقبل الشكل المحلي وJSON القديم؛ Guest Checkout محفوظ |
| إنشاء عرض | POST customer/quote-requests؛ POST customer/post القديم يرفض بـ410 في وضع الفروع | service_id، description، service_address_id أو service_address، requested_window_start/end اختياريان، field_values، additional_instructions، attachments | Post submitted + branch_id؛ CreatePostController ثم قائمة الطلبات | خدمة quote نشطة غير محذوفة، عنوان مملوك وإحداثيات ومنطقة Spatial، حقول الخدمة والملفات، اختيار فرع intakeOnly بلا حجز سعة |
| قائمة الطلبات | GET customer/quote-requests?page&limit | حساب مصادق عليه | data/current_page/last_page/total؛ service, active_quotation, inspections, booking, quote_status | ملكية customer_user_id؛ تصحيح bool is_booked في العميل؛ لا بيانات Controller قديمة لازمة للرابط |
| تفاصيل الطلب | GET customer/quote-requests/{id} | post_id في رابط quote-request | quotations[].customer_actions.can_accept/can_reject, payable_amount, currency، المعاينات، booking_id | قراءة مملوكة؛ حالة العرض بعد انتهاء الصلاحية؛ خدمة الطلب لا تختفي بسبب انتقال منطقة التصفح الحالية؛ لا قبول مستنتج من UI |
| ملخص قبول العرض | GET customer/post/details/{id}?post_bid_id={quoteId} | معرفا الطلب والعرض | post_details، quotation_can_checkout، quotation_offered_amount، quotation_inspection_deduction، quotation_payable_amount | Flutter يعرض هذه المبالغ دون حساب ضريبة/خصم مستقل ودون مبلغ الرابط؛ المبلغ هو إجمالي عقد العرض الحالي؛ غياب العقد يعطّل الدفع بأمان |
| رفض العرض | POST customer/quote-requests/quotations/{id}/reject | معرف العرض | DEFAULT_UPDATE_200 | قفل العرض والطلب، ملكية، غير محجوز وready وغير منتهٍ؛ الرفض المكرر لا يكرر الأثر |
| تحويل العرض | POST customer/quote-requests/quotations/{id}/accept، ومسارات post القديمة المتوافقة | service_schedule صريح، payment_method، quotation_payable_amount كقيمة متوقعة فقط؛ is_partial/بيانات offline وفق المسار الموجود | Booking.id والربط post.booking_id/quote.booking_id | العرض والطلب مقفولان، إعادة الطلب تعيد الحجز؛ الخدمة والسعة والفرع والموعد والسعر يعاد فحصها؛ التوقيت الاختياري الأولي لا يستخدم كبديل |

قواعد معاينة العرض من QuoteInspection الحالية: مجانية أو مدفوعة، scheduled_at وstatus؛ خصم المعاينة يأتي من paid_at/amount وغير المخصوم على الخادم. تطبيق العميل يعرضها ولا ينشئ سياسة رسوم أو موعد معاينة تلقائياً. provider_note وterms من العرض الحالي، لا محتوى ثابت لخدمة.

بعد `RELEASE-HOTFIX-01` يرد `PUT customer/post/update-info` أيضًا بـ410 في وضع الفروع. الرسالة تطلب تحديث التطبيق واستخدام شاشة طلب عرض السعر، دون إنشاء أو تعديل سجل. قراءة `post/details` وقبول ورفض العرض محفوظة. في الوضع الآخر تبقى الكتابة مشروطة بنوع customer وملكية الطلب والعنوان؛ التعديل يقفل الطلب والعنوان ويرفض المحجوز أو ذا عرض أو سياق رحلة الفروع بـ409. التفاصيل في [تقرير الإصلاح](RELEASE_HOTFIX_01_REPORT.md).

## الإتاحة والحجز المباشر والدفع

| الخطوة | العقد | مدخل/استجابة أساسية | القرار النهائي |
|---|---|---|---|
| المواعيد | GET customer/booking/availability/slots | zone_id, sub_category_id, service_ids[], date/starts_at, provider_id, latitude/longitude, quotation_id اختياري، branch_selection؛ يعود slots[].starts_at/provider_id وtimezone أو requested_slot_available | AvailabilityService والفرع؛ سعر/سعة الواجهة لا تكفي. سياق العرض والباقة مستقل عن سلة أخرى |
| معاينة مباشر | POST customer/booking/checkout-price | السلة والخدمة والخيارات والوحدات والعنوان والموعد والدفع/المحفظة | BranchCheckoutService يخرج checkout_snapshot_id والمبالغ المعتمدة؛ رفض استجابة قديمة عبر contextRevision/priceRevision |
| تأكيد مباشر | POST customer/booking/request/send | checkout_snapshot_id، payment_method، service_address_id/JSON، service_schedule، service_type، booking_type/dates، zone/provider/branch_selection، بيانات الضيف عند السياسة الحالية | الخادم يقفل النسخة ويتحقق ملكيتها ونتيجتها ويعيد نفس الحجز عند التكرار. تغيّر السياق يبطل الموعد والملخص |
| نقل غير محسوم | CheckoutRepo + CheckOutController | يحتفظ في الذاكرة بنفس payload والنسخة والمبلغ الموافق عليه؛ UI يوضح أن إعادة المحاولة تخص التأكيد السابق | لا يُنشئ snapshot جديداً أثناء إعادة تلك المحاولة. التخزين المباشر غير مستمر بعد إغلاق التطبيق؛ العودة إلى الحجوزات تبقى طريق الاستيضاح |
| دفع رقمي | POST customer/payment-sessions؛ GET customer/payments/{paymentId} | العقد الحالي، request_key محفوظ حسب digest، quote snapshot أو checkout snapshot | launch_url من نفس أصل الخادم، ثم finalization_status خادمي؛ الرجوع من الصفحة لا يثبت التحصيل. PaymentSessionHelper/PaymentScreen الحاليان محفوظان |
| نقد/محفظة/Offline | عقود confirm/accept الحالية | طريقة متاحة من Config؛ خصم المحفظة والتحقق في الخادم؛ offline ينتظر الاعتماد | لا علم Flutter لإثبات paid. النقد/المحفظة للعرض يفتحان الحجز المعاد؛ الخطأ غير المحسوم يطلب مراجعة الطلب |
| شحن المحفظة | RouteHelper.getMyWalletScreen والمسارات الحالية | انتقال من باقاتي والعودة | إعادة قراءة الباقات بعد الرجوع؛ لا أسرار أو رموز دفع في رابط العودة ولا شراء باقة بالبطاقة مستحدث |

## باقات الزيارات

جميع المسارات أدناه تحت `customer/visit-packages` ومصادقة API الحالية. نوع المستخدم وملكية الباقة في الخدمة/Controller، لا اعتماد على علم الواجهة.

| المسار | المدخل | الاستجابة/الاستخدام |
|---|---|---|
| GET /plans | page | خطط active لخدمة fixed نشطة غير محذوفة: 4/8/12 زيارات، price/currency، cadence، validity_days، pricing_policy |
| GET / | page | باقات العميل ورصيدها وصلاحيتها وحالتها وpricing_snapshot والتجديد |
| POST /purchase | plan_id، idempotency_key UUID، auto_renew اختياري | شراء بالمحفظة عبر VisitPackageService.purchase نفسه؛ قفل العميل، مفتاح payment_reference، خصم واحد؛ مفتاح UI محفوظ لإعادة المحاولة |
| GET /{id}/booking-context **جديد** | package_id مملوك | package، eligible، service مختصرة، variations بلا أسعار، max_units_per_visit، minimum_interval_days، amount_due=0، payment_method=prepaid_visit |
| POST /{id}/booking-preview **جديد** | idempotency_key UUID، service_address_id integer، variant_key، quantity 1..100، service_schedule، provider_id UUID | ملخص الخادم: العنوان/الخدمة/الفرع/الوقت والمنطقة الزمنية، remaining_before/after، visits_required=1، amount_due=0، currency |
| POST /{id}/book **جديد** | نفس حقول المعاينة، لا مبلغ ولا paid | booking_id، readable_id، service_status، payment_method، coverage؛ throttle 20/min |
| POST /{id}/reserve **قديم محفوظ** | booking_id | تغطية الحجز المؤهل القديم بقواعده، مع منع quote والمتكرر؛ لا تقبل واجهته nativeBooking. الواجهة الجديدة لا تستخدم تسلسل طلبين |
| GET /{id}/ledger | page | أحداث purchase/reserve/release/consume، delta/balance والربط بالحجز؛ ملكية الباقة |
| PUT /{id}/renewal | auto_renew boolean | تحديث تفضيل التجديد الموجود؛ المجدول لم يُشغّل |
| admin/visit-packages/plans GET/POST/PUT | العقود الحالية + fixed service eligibility | صلاحية packages.manage محفوظة؛ لا إعادة اشتراكات مزودين |

### الذرية والأقفال

`book`: قفل العميل ← فحص مفتاح الطلب/hash ← خدمة وعنوان وخيار صالح ← قفل الفرع وفحص تغطية وإتاحة البداية والنهاية والعمالة ← إنشاء Booking صفر القيمة وdetail وamounts وتشغيل snapshot checklist الحالي ← reserve داخل نفس transaction ← usage/ledger/history/request row. الخطأ يرمي استثناءً ويعيد كل الكتابات، بما فيها أحداث DB، وتعيد transaction المحاولة عند deadlock ضمن المحاولات الثلاث الحالية.

`reserve`: قفل booking ثم package، تحقق ملكية/رصيد/صلاحية حتى الموعد/وحدات/فاصل زمني/حالة pending/عدم دفع آخر أو تكرار أو quote؛ نفس سجل usage لمرة واحدة. `settle`: booking ثم package ثم usage، يعيد الحالة المقفولة الحالية، ويعيد زيارة واحدة فقط عند canceled قبل بدء/إنهاء العمل؛ غير ذلك consumed. تكرار حدث النهاية لا يكرر الرصيد. إعادة نفس book بعد الإلغاء تعيد الحجز السابق ولا تنشئ حجزاً جديداً؛ يلزم طلب جديد لحجز آخر.

`customer_visit_booking_requests` يربط (customer_id,idempotency_key) الفريد بـhash وbooking_id الفريد. تغيير المحتوى مع المفتاح نفسه 409. العميل يحفظ payload معرفات/كمية/موعد فقط عبر reload ويعيده عند نتيجة غير محسومة، دون دفع. إذا فشل الخادم دون إنشاء حجز فالـrollback يمنع حجزاً بلا تغطية؛ لم تختبر سباقات الحمل فعلياً.

التعديل المباشر لخدمة/وحدات/موعد/عنوان/تغطية زيارة الباقة ممنوع؛ cancel المؤهل ثم book جديد من الرصيد سياسة هذه الجولة. BookingTrait يحمي مسارات إضافة/حذف/زيادة/خفض الخدمة تحت قفل الحجز؛ observer يمنع تغيير سياق التغطية/وسيلة الدفع أو إعادة فتح زيارة مستهلكة/معادة. can_modify_details=false في workflow ويخفي تطبيق الفرع والفني زر التعديل للزيارة المغطاة.

## المحتوى والمتابعة

| العقد | الامتداد/السلوك | التوافق |
|---|---|---|
| Service API | gallery_image_urls[] مرتبة من gallery_images JSON | العميل القديم يتجاهل الحقل؛ الجديد يعود إلى الغلاف/المصغرة ثم يخفي المعرض إن غابتا |
| الإدارة ServiceController store/update | gallery_present، gallery_keep indexes، gallery_order، gallery_uploads | CSRF والصلاحيات والنموذج الحالي؛ لا حذف مراجع معرض عند غياب gallery_present؛ نوع/حجم وعدد 12 وصور السجل فقط |
| BookingWorkflowService.details | visit_package{package_id,usage_status,covered_visits}، can_modify_details، invoice_status=not_applicable للباقة دون فاتورة | أسماء وحالات تشغيل فقط؛ لا سعر شراء الباقة أو رصيد المحفظة للفرع/الفني. الحقول القديمة محفوظة |
| customer/communications/notifications | post_id لquote_ready الجديد، booking_is_repeated، booking_id، event/title/time | السجلات القديمة دون post_id تفتح قائمة الطلبات. الحجز المتكرر يفتح مساره الحالي |
| customer/invoices و/{id} | العقود الحالية بحسب booking_id أو booking_repeat_id | عرض pending منفصل عن الدفع، لا فاتورة مصطنعة؛ تجاهل استجابة تخص حجزاً سابقاً |
| التتبع القائم | captured_at، stale، eta_minutes والفريق | ETA تقديري، بيانات قديمة أو غائبة معلنة؛ لا خلفية أو خدمة مرور جديدة |

## التوافق وترتيب التطبيق لاحقاً — ورقي فقط

1. مراجعة حالة مهاجرات المصدر الأساسي الفعلية في البيئة المخولة لاحقاً؛ لا يمكن تأكيد قاعدة البيانات من مراجعة المصدر.
2. تطبيق `2026_10_07_000001_create_visit_booking_requests.php` بعد وجود users/bookings/customer_visit_packages و`2026_10_07_000002_add_service_gallery_images.php` بعد services، **عند تفويض تشغيلي منفصل فقط**. SQL/MySQL 8، foreignUuid وJSON وdown() موجودة؛ لا تغيير Spatial.
3. Backend المتوافق أولاً ثم العميل والويب والفرع والفني. العميل الجديد يعرض خطأ/retry مع Backend لا يملك عقد الباقات الجديد، ولا يفعّل قبول عرض دون quotation_can_checkout. العملاء القدامى يتجاهلون حقول القراءة؛ الخادم الجديد يحمي التغطية حتى لو ظل زر قديم ظاهراً.
4. إدخال المحتوى من الإدارة وفق قائمة الإعداد؛ لا Seeder. تفعيل/تجربة jobs والدفع والإشعارات والمحاسبة ليس ضمن هذه الجولة.
5. عند rollback لاحق: إيقاف استعمال الميزات أولاً، حفظ سجلات الربط والتغطية، ثم تقييم رجوع التطبيقات/الخادم. down() يحذف بنية وبيانات الربط/المعرض؛ ليس أمراً موصى بتنفيذه آلياً.
