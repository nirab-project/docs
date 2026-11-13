# S09 — مدير الفرع والفني والواجهات التشغيلية
## 1. أسطح التغيير
### Provider Flutter
`lib/feature/booking_details/{model,controller,repo,view,widget}`، `lib/feature/booking_requests/`، `lib/feature/dashboard/`، `lib/feature/reporting/`، `lib/feature/custom_post/`، `lib/feature/serviceman/`، `lib/feature/service_details/`، `lib/util/app_constants.dart` و`lib/helper/route_helper.dart`.
لا تستخدم business_subscription الخاص بمزودي Demandium كاشتراك للعميل؛ أخفِ مسارات بيع صلاحية الفرع/عمولة/سحب عمولة في branch-only mode، مع المحافظة على صلاحيات auth والفرع.

### Serviceman Flutter
`lib/feature/booking_details/{model,controller,repository,view,widget}`، `lib/feature/booking_request/`، `lib/feature/dashboard/`، `lib/feature/quality/`، `lib/common/models/service_details_model.dart`، `lib/utils/app_constants.dart`، notifications/route_helper.
**لاحظ اختلاف `utils` في الفني عن `util` في العميل والفرع؛ لا تفترض مسارات موحدة.**

### Laravel provider/admin operational pages
استعرض `Modules/BookingModule/Resources/views/` و`Modules/ProviderManagement/Resources/views/` وroutes قبل تعديلها. الواجهات web يجب أن تستخدم application services نفسها للتعيين/الجدولة/المال؛ لا bypass SQL في controller لمجرد أنها Blade.

## 2. مدير الفرع
dashboard من V2: الحجوزات حسب موعد التنفيذ وحالتها، cash outstanding/remitted، quotes pending، tasks pickup/delivery، المهام المتأخرة، وسعة الفريق. order قد يحتوي مجموعات فرعه فقط (مدينة واحدة في checkout)؛ لا تسرب عناصر مدن سلة أخرى. كل مبلغ مع تعريفه، لا revenue = مجموع order + bookings.

Booking detail يعرض order reference، customer contact المخول، العنوان/نقطتي النقل، service snapshot، components، repeated instances answers، preferences، media المصرح بها، due-now/due-later، الفريق والمدة وschedule revision. الإسناد وإعادة الإسناد يستخدم eligibility/locks، لا dropdown بكل فنيي كل الفروع. قبول الفرع لا يغير paid.

Quote desk يسمح review/send version/inspection/additional-info حسب permission. يقدم amendment لزيادة عمل أو وزن؛ العميل يقبله، لا مدير الفرع يقبل بالنيابة دون audit وصلاحية استثنائية مصممة. Calendar drag/reschedule إن موجود لا يكتب الموعد مباشرة. Cancel يمر policy ويعرض أثر المال/visit credit قبل التنفيذ.

غسيل الملابس له قوائم awaiting pickup/processing/delivery؛ تعديل وزن وعدد أكياس يرفق evidence؛ أي excess يحتاج موافقة. تحويل task من فني لآخر يحافظ على chain-of-custody logs، لا يغير صاحب order.

## 3. الفني
My tasks مرتبة بالموعد: type/arrival-window/estimated-work، البيانات الضرورية فقط، offline indicator. صفحة تفاصيل تحتوي checklist وقائمة مكونات وصور قبل/بعد وnotes وتعليمات تفضيلات. الأزرار حسب capabilities وحالة الخادم. التحصيل النقدي يعرض المبلغ المتبقي الحقيقي، يدخله/يؤكده حسب السياسة، وتسجل الحركة بمفتاح فريد. لا تحرير status_paid في payload عام.

Onsite: قبول مهمة → في الطريق → الوصول → بدء → checklist+evidence → إكمال. inspection: تسجيل نتائج ومرفقات لا سعر نهائي مفروض من الفني. pickup: تعداد أكياس/إثبات استلام. delivery: إثبات تسليم وOTP إن policy؛ إكماله يستهلك الزيارة مرة واحدة بعد اكتمال المعالجة. لا تسقط مهام فريق عند إكمال قائد واحد خطأ.

هاتف بلا شبكة: queued request له idempotency/expected_version؛ يظهر «لم يزامن» حتى يوافق الخادم. عند conflict يعرض الحالية ويطلب إجراء مناسب لا يعيد الحالة بالقوة. انتهاء صلاحية token يعيد login دون ضياع evidence غير المرسلة، ويعزل ملفات المستخدم السابق.

## 4. API وصلاحيات مشتركة
CustomerOrderDTO يختلف عن BranchOrderDTO وTechnicianTaskDTO. الفني لا يحصل على مفاتيح الدفع أو ledger أو بيانات عملاء غير مهامه. BranchManagerScope عامل مساعد لا بديل عن policy على كل entity، بما فيها invoices/evidence exports. التعيين لفني inactive/فرع آخر ممنوع حتى لو أرسل admin UI id صحيحاً.

## 5. اختبار التطبيقات
اكتب tests models/repositories/state/widget وfixtures JSON مشتركة مع OpenAPI. في PH12: flutter analyze/test للتطبيقات الثلاثة، build web للعميل، Android debug لكل تطبيق بأدوات SDK المتاحة. Provider/Serviceman لا يتضمنان منصة web باللقطة؛ ليس مطلوباً اختراعها لتجاوز اختبار Android، لكن لوحة مدير الفرع Laravel تختبر في المتصفح. iOS لا ينجح بمجرد مراجعة Dart؛ app موجود iOS للفني فقط بهذه اللقطة، وأي Apple build يحتاج بيئة مرخصة مناسبة وموافقة منفصلة للنشر.

manual on-device: auth/OTP، فتح deeplink، الخلفية/العودة، رفع صور، task transition، cash duplicate retry، branch isolation، RTL. عدم إمكانية native push live محلياً يسجل `external_pending` مع fake event acceptance واختبار المالك لاحقاً؛ لا يزعم وصول رسالة حقيقية.
