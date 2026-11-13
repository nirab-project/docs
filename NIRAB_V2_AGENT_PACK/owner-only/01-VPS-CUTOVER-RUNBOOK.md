# OWNER-ONLY — دليل التحويل اليدوي على VPS
**المستخدم وحده ينفذ هذا الدليل لاحقاً بالتدرج. الوكيل يكتب ويختبر أدواته محلياً ولا يدخل VPS.** أسماء الأوامر الجديدة مواصفات تنفيذ وليست تأكيداً بأنها موجودة الآن. لا تنفذ block كاملاً من دون مراجعة مخرجات الخطوة السابقة.

## A. متطلبات عدم البدء
وجود LOCAL_VALIDATED report ونتائج اختبارات المال/الإتاحة/التصفير، manifests نهائية، قائمة schema changes، code releases الأربعة، rollback artifacts. native/external pending موضحة مع قرار المالك قبل الإطلاق. لا خطط تتطلب شراء أو ربط خارجي جديد تلقائياً.

حدد نافذة صيانة يدوية، عطّل auto-deploy من pushes/branches كي لا يعمل cutover جزئياً. لا حذف قديم قبل أن تكون ملفات V2 واختباراتها جاهزة. النسخ الاحتياطية خارج web root وبصلاحيات محدودة؛ احتفظ بمفاتيح فك التشفير معها بطريقة آمنة منفصلة، لا ترسلها للدردشة.

## B. اكتشاف النظام — قراءة فقط
هذه أوامر حقيقية قراءة فقط، لا تعرض env أو secrets:
```sh
docker ps --format 'table {{.ID}}\t{{.Names}}\t{{.Image}}\t{{.Status}}'
# اختر معرف حاوية الباك إند الصحيح من الناتج؛ لا أول حاوية عشوائياً.
CID='<BACKEND_CONTAINER_ID>'
docker exec -w /var/www/html "$CID" php artisan --version
docker exec -w /var/www/html "$CID" php artisan migrate:status
```
تحقق من مسار التطبيق الفعلي عند اختلافه. دوّن صور backend/customer versions وworker/scheduler ومصادر volumes وDB identity بشكل غير حساس. لا تستخدم `cat .env` أو `docker inspect` كامل في دردشة عامة.

## C. العزل والنسخ
1. ادخل maintenance، أوقف قبول الكتابات والمهام والجدولة/outbox send/renewals يدوياً مع حفظ ما أوقفته، وليس حذف الـjobs.
2. افحص pending payments/refunds/bank receipts/holds/تعاملات خارجية؛ تتبع ما هو fake وما هو حقيقي. أي مجهول يمسكه quarantine، لا يسمح reset بحذفه.
3. نفذ النسخ الحالية بعد التحقق من أداة النسخ:
```sh
docker exec -w /var/www/html "$CID" php artisan help database:backup
docker exec -w /var/www/html "$CID" php artisan database:backup
```
4. تحقق أن dump يشمل كل root+module tables وroutines المطلوبة، واجمع نسخة الملفات الخاصة/الوسائط المحمية/volumes/config الضرورية. الأداة السابقة لا تثبت وحدها اكتمال نسخ كل volumes.
5. تحقق من إمكانية الاستعادة في قاعدة معزولة يحددها المالك، وليس فوق الإنتاج. سجّل `backup_reference`, checksum, restored-check report. لا تغيّر APP_KEY الحالي أو OAuth key pair أو Firebase configs.

## D. الجرد والخطة — بعد رفع الكود المعتمد دون تشغيل migrations آلياً
افتح `--help` للأوامر الجديدة وتأكد من مطابقة هذه الوثيقة لتقرير الوكيل. احفظ ناتج inventory وprotected digests إلى private state path مصرح. أمثلة أسماء لا تشغّلها قبل وجودها:
```sh
docker exec -w /var/www/html "$CID" php artisan help nirab:commerce-inventory
docker exec -w /var/www/html "$CID" php artisan help nirab:commerce-reset
```
المالك يمرر ملف manifest الذي نتج عن inventory الحقيقي؛ لا يعتمد example على أنه مكتمل. plan يحدد الجداول/صفوفها/المحمية/المجهولة/المراجع الخارجية والـDB fingerprint وخطة checkpoint. راجع counts وkeep user IDs والحدود والجغرافيا ومؤهلات الفنيين وOdoo mappings. unknown أو protected diff أو remote pending → توقف.

## E. مخطط V2 ثم التصفير
- راجع قائمة migrations pending فعلياً، لا تفترض أن migrations الموجودة في الأرشيف مطبقة على VPS. أي migration تمس protected config تحتاج مراجعة منفصلة.
- طبق migrations الإضافية المعتمدة يدوياً فقط، والكود write endpoints ما زالت مغلقة. MySQL DDL قد يعمل implicit commit؛ rollback migration ليس خطة استعادة.
- حدّث inventory/plan إذا غيّر schema fingerprint. تحقق backup/generation/code hash ما زالت صحيحة. اطلب reset apply المعتمد من المالك صراحة بقيمة hash نفسها وowner maintenance gate، وفي terminal تفاعلي.
- أداة reset لا تحذف protected users/settings/cities/providers. تسوي/تعزل أي real external refs. تكتب journal/checkpoints. عند فشل لا تعيد أوامر SQL يدوياً عشوائياً؛ اتبع resume أو restore plan.
- لا `FOREIGN_KEY_CHECKS=0` ولا wildcard truncate ولا bucket wipe ولا database:refresh ولا migrate:fresh. لا apply ملف plan قديم بعد ظهور معاملات جديدة.

## F. إنشاء البيانات الجديدة دون مساس بالإعدادات
شغّل `reference` seeds فقط، لا local demo profile. ستنشئ خدمات/عروض/سياسات كمسودات، والأرقام التجريبية ليست بيعاً حقيقياً. استعمل mapping للخدمات/المهارات مع الفروع الموجودة، ولا تنشئ مدناً بديلة أو حساب أدمن افتراضي ضعيف. اعتمد المحتوى والأسعار/الضريبة/الإلغاء/السعات/ساعات العمل والمهارات من الإدارة قبل التفعيل.

تحقق preservation before/after بكل القيم المحمية، وorphans/FKs وأرقام التقارير الصفرية للمعاملات المحذوفة. يعاد بناء caches/projections لا نقل totals القديمة. جميع joins التشغيل الجديدة تُقرأ من V2 ولا يوجد stale landing/banner link يكسر الصفحة.

## G. التحويل المتزامن
أبقِ الصيانة. فعّل flags V2 مع strict city guard وmin commerce contract. انشر Customer web والتطبيقات المتوافقة حسب مسار النشر الذي ينفذه المالك. امسح cache العميل/الخادم انتقائياً لا sessions أو إعدادات أسرار بلا سبب. legacy financial mutations محجوبة؛ auth/config تبقى؛ old callbacks في quarantine.

ابدأ workers/scheduler **المحدثة** فقط بعد التأكد من عدم تشغيل legacy jobs على سجلات محذوفة. استخدم generation/fences، وأعد الاتصالات الخارجية واحدة واحدة بعد التحقق. لا ترسل دفعة إشعارات تجريبية للهواتف القديمة عند الإقلاع.

## H. قبول المالك قبل فتح الجمهور
اختبارات smoke من `acceptance/01-MANUAL-ACCEPTANCE.md`: دخول الأدمن/الفرع/الفني، المدينة والعنوان، خدمة منشورة، سعر، سلة متعددة الخدمات/مواعيد، حجز تجريبي مع وسيلة آمنة، تعيين وتنفيذ، quote، pack، إلغاء/إعادة جدولة، report totals، حفظ config. بوابات حقيقية فقط عبر sandbox الرسمي المخول أو إجراء مالي صغير يوافق عليه المالك منفصلاً؛ لا يلزم شراء حقيقي لمجرد إثبات UI.

افتح استقبال العملاء بعد اعتماد النتائج والسياسات. احفظ code/db/schema generation ووقت cutover وعدم وجود P0/P1. إصدار شهادة «جاهز» هنا للمالك وليس للوكيل المحلي.

## I. التراجع
قبل فتح الكتابات وعدم حدوث أفعال مالية جديدة: maintenance، أوقف workers، استعد DB/files snapshot المتوافقة مع code القديم وأعد الإعدادات المحمية نفسها؛ لا تدوّر APP_KEY. تحقق city/config/auth ثم أعد التشغيل عند جاهزية.

بعد حدوث عمليات V2 حقيقية: ممنوع restore أعمى يمحو مدفوعات/حجوزات جديدة. أغلق الكتابات، احفظ الأحداث/المراجع/الطلبات الجديدة، سوِّ الخارجي، وقرر forward-fix أو replay/reconciliation مخططاً. code rollback وحده لا يعيد compatibility مخطط/بيانات قد حذفت. سجل هذا كحالة تشغيل مستقلة لا زر undo.

## J. أشياء لا ينفذها هذا الدليل
لا حذف الحسابات الخارجية لدى بوابات الدفع/Odoo، لا تغيير كلمة مرور/المفاتيح/الدومينات، لا تفعيل مدينة بلا مصدر حدود، لا restore حي لخوادم طرف ثالث، لا نشر متاجر تلقائي، ولا حذف بيانات حقيقية مستقبلية بعد الإطلاق بذريعة أن مشروع ما قبل الإطلاق كان تجريبياً.
