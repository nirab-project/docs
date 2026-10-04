# NIRAB — ملخص تنفيذ المرحلة 5

تاريخ التنفيذ: 2026-10-04.

تم تنفيذ نطاق المرحلة في السورس: بوابات الدفع السعودية، الاسترداد، المحفظة، Outbox وOdoo، استقبال نتائج الفوترة/ZATCA، وواجهات العميل والفرع والإدارة. لم يتم تشغيل النظام أو إجراء معاملات خارجية، ولا يمثل نجاح البناء تحققًا من إعدادات حسابات Tap أو نسخة Odoo الفعلية.

## التغييرات المنفذة

- أضيف عقد PaymentGatewayInterface ومحوّلات Tap/Tabby/Tamara داخل PaymentModule. وفق مواصفة المدفوعات التفصيلية للمشروع، تستخدم الثلاثة Tap Charges API مع المصادر src_all وsrc_tabby.installement وsrc_tamara. خيارات Mada/Apple Pay/Cards تأتي من إعداد حساب Tap وصفحة الدفع المستضافة.
- فُصلت حالات الدفع initiated/pending/authorized/paid/failed/canceled/partially_refunded/refunded. التحقق من المرجع والمبلغ والعملة والمالك يتم من مورد البوابة على الخادم؛ redirect العميل وحده لا يؤكد الدفع.
- أضيفت webhooks موقعة، سجل أحداث منزوع البيانات الحساسة، ومفاتيح ثابتة تمنع إعادة تطبيق الحدث. إتمام الحجز وتسجيل أحداث المحاسبة يقعان داخل transaction محلية واحدة.
- الاسترداد قرار محفوظ لا يمكن تغيير مصدره: أصل الدفع عبر Job غير متزامن، أو المحفظة عبر حركة واحدة بمفتاح فريد. أصل الدفع لا يضيف رصيد محفظة. يُراعى إجمالي المبالغ المحجوزة للاسترداد، والمبلغ الإلكتروني المخصص لكل حجز/زيارة، والدفع المختلط wallet + card.
- عولج الدفع المشترك لعدة حجوزات والزيارات المتكررة بتخصيص مبلغ الدفع على كل موضوع وفرع. لا تُصدر فاتورة أخرى من الحجز الأب الذي يجمع الزيارات.
- أضيفت حقول source/amount/reference/idempotency/accounting status لحركات المحفظة، بما فيها top-up/refund/cashback/promotion/booking payment/subscription payment. لم تضف خدمة سحب نقدي للعميل.
- أضيف Module مستقل OdooIntegration، مع OdooTransportInterface ومحوّلي JSON2 وlegacy JSON-RPC. التسلسل الخاص بكل API منفصل عن mapper المحاسبي.
- الأحداث تحفظ snapshots مشفرة في Outbox قبل dispatch، دون HTTP إلى Odoo من مسار الحجز. الأوامر المجدولة ترسل Jobs إلى queue دائمة، مع backoff وثماني محاولات، وlease قابل للاسترداد بعد توقف العامل، وrequires_attention ثم Retry يدوي.
- أضيف mapping للعملاء والمنتجات/الاختلافات والحجوزات والفواتير والمدفوعات ومراكز تكلفة الفروع. لا تنشأ عمولات أو مستحقات Provider؛ الإيراد يعود إلى NIRAB والفرع يستخدم analytics.
- النقد والتحويل مؤهلان للإرسال فقط بعد اعتماد مدير الفرع والمحاسب. عهدة التحصيل لا ترسل قبل ذلك؛ التسليم النقدي يحتاج التحقق المحاسبي. التحصيل المعتمد للحجوزات الضيف يحتفظ بهوية الضيف دون اشتراط حساب مستخدم.
- الفواتير مستقلة عن اكتمال الخدمة وحالة الدفع: تدعم خدمة مكتملة وفاتورة مستحقة، وفواتير/credit notes من Odoo. تغيير مبلغ فاتورة سبق إصدارها يتطلب credit note صريحًا.
- يستقبل NIRAB رقم الفاتورة وUUID وحالة الدفع ونتيجة ZATCA وQR من Odoo. لا توجد API مباشرة إلى ZATCA. يخزن PDF في مساحة خاصة ويرسله عبر رابط موقع لمدة عشر دقائق.
- أضيف InvoiceAvailable وإشعار محلي دائم مرة واحدة عند توفر الفاتورة وPDF، وAPI للإشعارات غير المقروءة. WhatsApp يظل ضمن المرحلة 6.
- واجهة العميل/الويب تعرض قائمة وتفاصيل الفاتورة وQR وتنزيل PDF من تفاصيل الحجز والزيارة. واجهة الفرع تعرض فواتير الفرع فقط مع حالات الإصدار والدفع. لوحة الإدارة تعرض Outbox ومحاولاته وأخطاءه المنقحة، وRetry وmapping والاسترداد وإعدادات البوابات.
- المفاتيح المشتركة لبوابات Tap تحفظ مشفرة في addon_settings أو تقرأ من ENV. لا يعرض العميل أسرارًا، وتبقى إعدادات test/live مستقلة؛ ترك حقل السر فارغًا يحافظ على السر المحفوظ.
- حافظت روابط الدفع والاستجابة القديمة على عقد token الموجود، مع إضافة payment_id وحالة تحقق على الخادم. لم تُضف حزم جديدة.

## Migrations الجديدة

لم تُشغّل أي migration.

1. Admin Panel/Modules/PaymentModule/Database/Migrations/2026_10_04_110000_create_gateway_workflows.php
   - حقول حالة/مرجع/تحقق/إتمام الدفع، وgateway_events وpayment_refunds وحقول المحفظة المحاسبية.
   - مفاتيح idempotency فريدة، وفهارس البحث/المحاولات/الحجز، وعلاقات FK حيث توجد هوية فعلية. customer_id في refunds يقبل هوية الضيف؛ الاسترداد إلى wallet يتطلب حساب عميل.
2. Admin Panel/Modules/OdooIntegration/Database/Migrations/2026_10_04_110100_create_odoo_outbox.php
   - odoo_outbox وodoo_mappings وodoo_invoices وodoo_invoice_notifications.
   - snapshots مشفرة، lease، فهارس، هوية بعيدة فريدة، وربط الفاتورة بالحجز والزيارة.

كلا الملفين يحتوي down() يعكس الجداول والحقول والفهارس.

## المسارات وعقود API

gateway مقيد بالقيم tap أو tabby أو tamara. جميع استجابات العميل والإدارة تستخدم response_formatter المعتاد، عدا إقرار webhook/callback الذي يرجع received.

| الطريقة | المسار | العقد/الوصول |
|---|---|---|
| GET | /payment/{gateway}/pay?payment_id={uuid} | يبدأ الطلب الموجود ثم ينقل إلى checkout host مسموح عبر HTTPS |
| GET | /payment/{gateway}/return?payment_id={uuid}&tap_id={reference} | يتحقق من مورد Tap ويعيد flag وpayment_id وtoken المتوافق |
| POST | /api/v1/payments/{gateway}/webhook | hashstring حسب adapter، ثم إعادة تحقق من المورد؛ idempotent |
| GET | /api/v1/customer/payments/{id} | عميل مصادق ومالك payer_id؛ id/state/verified_at/booking_id/booking_repeat_id |
| GET | /api/v1/customer/invoices | قائمة مصفحة scoped للعميل؛ filters booking_id وbooking_repeat_id |
| GET | /api/v1/customer/invoices/{id} | تفاصيل الفاتورة ورابط PDF مؤقت؛ يسجل الإشعار مقروءًا |
| GET | /api/v1/customer/invoice-notifications | إشعارات الفواتير غير المقروءة الخاصة بالعميل |
| GET | /api/v1/invoices/{id}/pdf | توقيع URL وصلاحية زمنية وربط بالعميل؛ PDF خاص دون cache |
| POST | /api/v1/integrations/odoo/invoices | HMAC SHA256 للـ timestamp.raw_body؛ نافذة خمس دقائق وidempotency |
| GET | /api/v1/provider/accounting/invoices | حساب فرع وصلاحية view_cash؛ فواتير فرعه فقط |
| GET | /provider/accounting/invoices | صفحة فواتير الفرع بصلاحياته |
| GET | /admin/finance-integration و/api/v1/admin/finance-integration | إدارة فقط؛ filter status/entity_type/entity_id؛ payload مخفي |
| POST | /admin/finance-integration/outbox/{id}/retry | إعادة failed/requires_attention؛ يوجد مقابل تحت /api/v1 |
| POST | /admin/finance-integration/refunds | قرار استرداد؛ يوجد مقابل تحت /api/v1 |
| POST | /admin/finance-integration/refunds/{id}/retry | إعادة استرداد أصل الدفع المتوقف؛ يوجد مقابل تحت /api/v1 |
| POST | /admin/finance-integration/mappings | branch أو service/variation مع remote_id وtax_ids؛ يوجد مقابل تحت /api/v1 |
| POST | /admin/finance-integration/gateways | إعداد Tap/Tabby/Tamara للـmode المختار؛ يوجد مقابل تحت /api/v1 |

طلب الاسترداد: kind=booking أو booking_repeat، booking_id=معرف الموضوع، amount، destination=wallet أو original، reason، idempotency_key، وpayment_request_id إلزامي عمليًا لأصل الدفع. يثبت القرار والمبلغ ولا يسمح بإعادة استعمال المفتاح لقرار مختلف. الحجز المتكرر يتطلب اختيار زيارة booking_repeat للاسترداد اليدوي؛ إلغاء الحجز الأب يقسم الاسترداد التلقائي على زياراته بمفاتيح مستقلة، مع احتساب الاستردادات التاريخية للأب ضمن حد كل زيارة.

callback الفاتورة يقبل remote_id وnumber وuuid وstatus وpayment_status وzatca_status وqr_data. التوقيع X-Nirab-Signature هو HMAC SHA256 بالقيمة X-Nirab-Timestamp + "." + raw request body والمفتاح ODOO_INVOICE_CALLBACK_SECRET. لا يقبل URL PDF عشوائيًا؛ يتولى transport جلب PDF من Odoo.

أضيفت Tap/Tabby/Tamara إلى config طرق الدفع للعميل والفرع فقط عند اكتمال إعداداتها وتفعيلها وعملة SAR. استجابة digitalPaymentBookingResponse الحالية أصبحت تقرأ النتيجة النهائية المحفوظة لهذه البوابات، وتضيف payment_state؛ لا تعيد إنشاء الحجز أو حساب الضيف عند إعادة الاستدعاء.

## الإعدادات المطلوبة عند النشر

تم تعديل .env.example فقط؛ لم يعدّل ملف .env الفعلي. البوابات وOdoo معطلة افتراضيًا في الإعداد الجديد.

- Tap: أسرار وmerchant id لكل test/live، ومصدر الدفع، وwebhook key الذي يطابق حساب Tap. Tabby/Tamara يتبعان mode ومفاتيح Tap، مع تفعيل كل source في الحساب. المفاتيح القديمة plaintext لا تستخدمها المحولات الجديدة؛ يعاد إدخالها من لوحة الإدارة مرة واحدة لتخزينها مشفرة، أو تزود عبر ENV. APP_KEY يجب أن يبقى ثابتًا لفك إعدادات/snapshots المشفرة.
- URLs: اضبط APP_URL على HTTPS العام، وNIRAB_PAYMENT_RETURN_HOSTS لواجهات العميل المسموحة، وNIRAB_GATEWAY_CHECKOUT_HOSTS عند الحاجة لنطاق checkout آخر معتمد.
- Queue/cache: اضبط NIRAB_FINANCE_QUEUE_CONNECTION=redis وNIRAB_FINANCE_QUEUE=nirab-finance، وCACHE_STORE=redis في الإنتاج. Redis retry_after=180 أكبر من timeout=120 للمزامنة و60 للاسترداد. command nirab:finance-outbox مسجل كل دقيقة مع withoutOverlapping؛ يحتاج scheduler وworker في بيئة النشر، ولم يتم تشغيلهما هنا.
- Odoo: حدد transport والنسخة الفعلية، وHTTPS base URL/database/user/API key/company/SAR currency، وjournals/accounts ومعرفات inbound/outbound payment.method.line لكل cash/bank journal. يجب أن ينتمي كل method line إلى journal المقابل.
- أضف/حدد حقل x_nirab_key فريدًا في res.partner وaccount.move وaccount.payment داخل Odoo لمنع التكرار عبر workers/retries. توفر uniqueness هناك شرط لتفعيل التكامل.
- سجل branch → account.analytic.account وservice أو service:variation → product.product وtax_ids من شاشة الإدارة. Odoo يجب أن يحسب مبلغ الفاتورة نفسه؛ الاختلاف يزيد عن 0.02 يوقف posting ويظهر للمراجعة.
- الحسابات: top-up يستخدم bank receipt → wallet clearing، وحركة wallet تستخدم clearing → wallet liability. دفع wallet للحجز يستخدم receivable؛ refund/credit note يسوي receivable. اضبط outstanding receipts في cash journal إلى custody clearing، حتى تتكامل حركة التحصيل والعهدة دون تكرار النقد.
- تطابق أسماء الحقول مع النسخة: payment memo قابل للتبديل إلى ref، وanalytic_distribution إلى analytic_account_id عند الحاجة؛ uuid/QR/ZATCA/PDF attachment قابلة للتكوين. يمكن تعطيل حقل metadata اختياري بقيمة فارغة.
- PDF يقرأ attachment من Odoo API؛ عند استخدام report fallback تضبط كلمة مرور backend منفصلة ODOO_REPORT_PASSWORD والمسار المسموح. المفاتيح وكلمة المرور لا تصل إلى Flutter.
- Odoo مسؤول عن Saudi localization وZATCA clearance/reporting. يلزم إعداد ذلك في بيئة العميل؛ لم يحصل اتصال للتحقق منه أثناء التنفيذ.

أسماء ENV الجديدة/المضافة في تكامل المرحلة:

- NIRAB_PAYMENT_MODE
- TAP_ENABLED
- TAP_PUBLIC_KEY
- TAP_TEST_SECRET_KEY
- TAP_LIVE_SECRET_KEY
- TAP_TEST_MERCHANT_ID
- TAP_LIVE_MERCHANT_ID
- TAP_TEST_WEBHOOK_SECRET
- TAP_LIVE_WEBHOOK_SECRET
- TAP_SOURCE_ID
- TABBY_ENABLED
- TAMARA_ENABLED
- NIRAB_FINANCE_QUEUE_CONNECTION
- NIRAB_FINANCE_QUEUE
- NIRAB_GATEWAY_CHECKOUT_HOSTS
- NIRAB_PAYMENT_RETURN_HOSTS
- ODOO_ENABLED
- ODOO_TRANSPORT
- ODOO_BASE_URL
- ODOO_DATABASE
- ODOO_USERNAME
- ODOO_API_KEY
- ODOO_COMPANY_ID
- ODOO_SAR_CURRENCY_ID
- ODOO_SALES_JOURNAL_ID
- ODOO_CASH_JOURNAL_ID
- ODOO_BANK_JOURNAL_ID
- ODOO_BANK_RECEIPT_ACCOUNT_ID
- ODOO_WALLET_JOURNAL_ID
- ODOO_PAYMENT_METHOD_LINE_ID
- ODOO_OUTBOUND_PAYMENT_METHOD_LINE_ID
- ODOO_CASH_PAYMENT_METHOD_LINE_ID
- ODOO_BANK_PAYMENT_METHOD_LINE_ID
- ODOO_CASH_OUTBOUND_PAYMENT_METHOD_LINE_ID
- ODOO_BANK_OUTBOUND_PAYMENT_METHOD_LINE_ID
- ODOO_WALLET_LIABILITY_ACCOUNT_ID
- ODOO_WALLET_CLEARING_ACCOUNT_ID
- ODOO_PROMOTION_ACCOUNT_ID
- ODOO_SUBSCRIPTION_REVENUE_ACCOUNT_ID
- ODOO_CUSTODY_ACCOUNT_ID
- ODOO_CASH_ACCOUNT_ID
- ODOO_CUSTODY_CLEARING_ACCOUNT_ID
- ODOO_IDENTITY_FIELD
- ODOO_PAYMENT_REFERENCE_FIELD
- ODOO_ANALYTIC_FIELD
- ODOO_INVOICE_UUID_FIELD
- ODOO_ZATCA_STATUS_FIELD
- ODOO_INVOICE_QR_FIELD
- ODOO_INVOICE_PDF_REPORT_PATH
- ODOO_INVOICE_PDF_ATTACHMENT_FIELD
- ODOO_REPORT_PASSWORD
- ODOO_INVOICE_CALLBACK_SECRET
- CACHE_STORE
- REDIS_QUEUE_CONNECTION
- REDIS_QUEUE_RETRY_AFTER
- REDIS_QUEUE_BLOCK_FOR

## نتيجة Build/Compile/Format

| الفحص | النتيجة |
|---|---|
| PHP lint | نجح الفحص النهائي php -l للـ69 ملف PHP/Blade المتأثر بعد جميع التعديلات |
| PHP formatting/compile | تنسيق 40 ملفًا جديدًا باستخدام PhpParser الموجود، parsing ناجح للـ63 مصدر PHP، autoload ناجح لـ32 class/interface/trait جديدة، وcompile نحوي ناجح للقوالب الثلاثة الجديدة |
| Composer | composer validate --no-interaction نجح؛ تحذيرات constraints (*) موجودة لحزم madzipper وspatial وmercadopago وpaypal وfast-excel؛ لم تتغير Composer dependencies |
| Dart formatting | dart format للملفات السبعة نجح؛ الفحص النهائي --output=none --set-exit-if-changed أعاد 0 changed |
| JSON | parsing ناجح لملفات الترجمة الثمانية وmodule.json |
| Customer Web | flutter build web --no-pub --no-wasm-dry-run نجح خلال 207.4 ثانية؛ المخرج User app and web/build/web |
| Customer APK | لم ينفذ: Android SDK المتاح يحتوي cmdline-tools فقط، ولا توجد platforms/build-tools اللازمة |
| Provider APK | لم ينفذ: لا يوجد .dart_tool/package_config.json لهذا التطبيق، إضافة إلى نقص Android SDK |
| Admin assets | لا يوجد تعديل JS/CSS مجمع يتطلب asset build |

تنسيق Dart لتطبيق الفرع أعطى تحذيرًا بأن package:flutter_lints/flutter.yaml غير قابل للحل بسبب الاعتمادات غير المتاحة، لكنه نجح. لم تُثبت dependencies أو أدوات بديلة. لم يُشغّل server/worker/Redis/Docker/emulator، ولم تُنفذ migration أو seed أو PHPUnit/Pest/Flutter tests/E2E، ولم تحصل مكالمات إلى Tap/Odoo/ZATCA/SMS/WhatsApp.

## الملفات المتأثرة

قائمة السورس أدناه تشمل 86 ملفًا؛ هذا الملخص ملف توثيق إضافي. لم يتغير تطبيق الفني.


### Admin Panel (71 ملفًا)

- `Admin Panel/.env.example`
- `Admin Panel/Modules/AdminModule/Resources/views/layouts/partials/_aside.blade.php`
- `Admin Panel/Modules/BookingModule/Entities/Booking.php`
- `Admin Panel/Modules/BookingModule/Entities/BookingRepeat.php`
- `Admin Panel/Modules/BookingModule/Entities/CashHandover.php`
- `Admin Panel/Modules/BookingModule/Entities/PaymentCollection.php`
- `Admin Panel/Modules/BookingModule/Entities/TechnicianCustodyEntry.php`
- `Admin Panel/Modules/BookingModule/Http/Controllers/Api/V1/Customer/BookingController.php`
- `Admin Panel/Modules/BusinessSettingsModule/Http/Controllers/Web/Admin/ConfigurationController.php`
- `Admin Panel/Modules/BusinessSettingsModule/Resources/views/admin/configurations/third-party/payment/payment-digital.blade.php`
- `Admin Panel/Modules/CustomerModule/Http/Controllers/Api/V1/Customer/ConfigController.php`
- `Admin Panel/Modules/OdooIntegration/Console/DrainFinanceOutbox.php`
- `Admin Panel/Modules/OdooIntegration/Contracts/OdooTransportInterface.php`
- `Admin Panel/Modules/OdooIntegration/Database/Migrations/2026_10_04_110100_create_odoo_outbox.php`
- `Admin Panel/Modules/OdooIntegration/Events/InvoiceAvailable.php`
- `Admin Panel/Modules/OdooIntegration/Http/Controllers/FinanceController.php`
- `Admin Panel/Modules/OdooIntegration/Http/Controllers/InvoiceController.php`
- `Admin Panel/Modules/OdooIntegration/Jobs/SyncOutbox.php`
- `Admin Panel/Modules/OdooIntegration/Mappers/AccountingMapper.php`
- `Admin Panel/Modules/OdooIntegration/Models/Invoice.php`
- `Admin Panel/Modules/OdooIntegration/Models/Mapping.php`
- `Admin Panel/Modules/OdooIntegration/Models/Outbox.php`
- `Admin Panel/Modules/OdooIntegration/Observers/AccountingObserver.php`
- `Admin Panel/Modules/OdooIntegration/Providers/OdooIntegrationServiceProvider.php`
- `Admin Panel/Modules/OdooIntegration/Resources/lang/ar/finance.php`
- `Admin Panel/Modules/OdooIntegration/Resources/lang/en/finance.php`
- `Admin Panel/Modules/OdooIntegration/Resources/views/branch-invoices.blade.php`
- `Admin Panel/Modules/OdooIntegration/Resources/views/gateways.blade.php`
- `Admin Panel/Modules/OdooIntegration/Resources/views/monitor.blade.php`
- `Admin Panel/Modules/OdooIntegration/Routes/api.php`
- `Admin Panel/Modules/OdooIntegration/Routes/web.php`
- `Admin Panel/Modules/OdooIntegration/Services/AccountingEvents.php`
- `Admin Panel/Modules/OdooIntegration/Services/InvoiceService.php`
- `Admin Panel/Modules/OdooIntegration/Services/MappingException.php`
- `Admin Panel/Modules/OdooIntegration/Services/OutboxService.php`
- `Admin Panel/Modules/OdooIntegration/Traits/AtomicAccountingSave.php`
- `Admin Panel/Modules/OdooIntegration/Transports/HttpTransport.php`
- `Admin Panel/Modules/OdooIntegration/Transports/Json2Transport.php`
- `Admin Panel/Modules/OdooIntegration/Transports/LegacyJsonRpcTransport.php`
- `Admin Panel/Modules/OdooIntegration/module.json`
- `Admin Panel/Modules/PaymentModule/Contracts/PaymentGatewayInterface.php`
- `Admin Panel/Modules/PaymentModule/DTOs/GatewayResult.php`
- `Admin Panel/Modules/PaymentModule/Database/Migrations/2026_10_04_110000_create_gateway_workflows.php`
- `Admin Panel/Modules/PaymentModule/Entities/PaymentRefund.php`
- `Admin Panel/Modules/PaymentModule/Entities/PaymentRequest.php`
- `Admin Panel/Modules/PaymentModule/Gateways/TabbyGateway.php`
- `Admin Panel/Modules/PaymentModule/Gateways/TamaraGateway.php`
- `Admin Panel/Modules/PaymentModule/Gateways/TapGateway.php`
- `Admin Panel/Modules/PaymentModule/Http/Controllers/Api/V1/Admin/PaymentConfigController.php`
- `Admin Panel/Modules/PaymentModule/Http/Controllers/GatewayController.php`
- `Admin Panel/Modules/PaymentModule/Http/Controllers/Web/Admin/PaymentConfigController.php`
- `Admin Panel/Modules/PaymentModule/Jobs/ProcessRefund.php`
- `Admin Panel/Modules/PaymentModule/Library/Constant.php`
- `Admin Panel/Modules/PaymentModule/Services/GatewayManager.php`
- `Admin Panel/Modules/PaymentModule/Services/GatewayPaymentService.php`
- `Admin Panel/Modules/PaymentModule/Services/GatewaySettings.php`
- `Admin Panel/Modules/PaymentModule/Services/RefundService.php`
- `Admin Panel/Modules/PaymentModule/Traits/Payment.php`
- `Admin Panel/Modules/ProviderManagement/Http/Controllers/Api/V1/Provider/ConfigController.php`
- `Admin Panel/Modules/ProviderManagement/Resources/views/layouts/partials/_aside.blade.php`
- `Admin Panel/Modules/TransactionModule/Entities/Transaction.php`
- `Admin Panel/Modules/TransactionModule/Lib/Transaction.php`
- `Admin Panel/Modules/UserManagement/Entities/User.php`
- `Admin Panel/app/Lib/Constant.php`
- `Admin Panel/app/Lib/Helpers.php`
- `Admin Panel/app/Services/NirabBookingFinancialService.php`
- `Admin Panel/bootstrap/providers.php`
- `Admin Panel/config/cache.php`
- `Admin Panel/config/nirab_odoo.php`
- `Admin Panel/config/nirab_payments.php`
- `Admin Panel/config/queue.php`

### Provider app (6 ملفًا)

- `Provider app/assets/language/ar.json`
- `Provider app/assets/language/bn.json`
- `Provider app/assets/language/en.json`
- `Provider app/assets/language/hi.json`
- `Provider app/lib/feature/reporting/view/branch_cash_operations_screen.dart`
- `Provider app/lib/helper/string_parser.dart`

### User app and web (9 ملفًا)

- `User app and web/assets/language/ar.json`
- `User app and web/assets/language/bn.json`
- `User app and web/assets/language/en.json`
- `User app and web/assets/language/hi.json`
- `User app and web/lib/feature/booking/widget/booking_invoices_panel.dart`
- `User app and web/lib/feature/booking/widget/payment_info_widget.dart`
- `User app and web/lib/feature/booking/widget/repeat/repeat_booking_details_widget.dart`
- `User app and web/lib/feature/checkout/view/payment_screen.dart`
- `User app and web/lib/helper/string_parser.dart`
