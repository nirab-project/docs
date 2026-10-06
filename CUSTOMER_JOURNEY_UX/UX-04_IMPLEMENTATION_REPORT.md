# UX-04 — الرئيسية وتفاصيل الخدمة والمحتوى

اكتمل التعديل المصدري داخل هوية main. لم يجر فحص مرئي على هاتف أو متصفح.

- الرئيسية للهاتف والويب تبدأ بالتصنيفات ثم مداخل الخدمات/العروض/الباقات، ثم البنرات والحملات القائمة قبل إبراز الفروع؛ أعيد ترتيب Widgets القائمة دون تغيير brand_tokens أو الخطوط أو قواعد التخفيض.
- تفاصيل الخدمة تحتفظ بتبويبات HTML الوصف وFAQ والمراجعات الموجودة، وتضيف معرض صور ومدة تقديرية وإيضاح تسعير العرض وزر طلب سفلي SafeArea يستدعي ServiceActionHelper نفسه.
- ServiceGallery يعرض ترتيب API ويستخدم CustomImage للفشل؛ fallback إلى صورة الغلاف والمصغرة، ويخفي المعرض عند غياب كل الصور. الوصف الفارغ لا يسبب استثناءً، وقوائم خيارات الخدمة الفارغة لا تقرأ العنصر صفر.
- لا توجد بنية معرض سابقاً: امتداد services.gallery_images JSON فقط. الإدارة تنشئ/ترتب/تزيل مراجع حتى 12 صورة بالرفع الموجود؛ لا مكتبة صور جديدة. الخدمة تتحقق من نوع وحجم الملفات وتسمح فقط بالإبقاء على صور السجل نفسه، ولا تقبل مسارات يرسلها المستخدم.
- صور S3/public تستخدم getDisk وfile_uploader وgetIdentityImageFullPath الموجودة. لا حذف ملف من التخزين أثناء تعديل مراجع المعرض؛ الملفات غير المشار إليها تحتاج سياسة تنظيف مستقلة لاحقاً.
- ما يشمله العمل وما يستثنيه يبقى محتوى HTML قابلاً للتحرير من الإدارة. لا نصوص خدمات أو ادعاءات ضمان أو سياسات خصم جديدة ثابتة في التطبيق.

## العقود والمهاجرات
Service API يضيف gallery_image_urls المرتبة؛ العميل القديم يتجاهلها والجديد يعود إلى الغلاف/المصغرة مع Backend أقدم.
2026_10_07_000002_add_service_gallery_images.php يضيف JSON nullable متوافقاً مع MySQL وله down(). غير مطبق. ينشر بعد تطبيق المهاجرة المصرح بها مستقبلاً وقبل حفظ معرض من الإدارة.

## الفحوص
php -l نجح لستة ملفات PHP. Blade راجع نصياً (include ضمن form، حقول مضبوطة، escaped output، CSRF النموذج الموجود)، دون تجميع Laravel. Dart format/analyze الأول لستة ملفات: دون أخطاء وتحذيرات، ملاحظة أسلوبية واحدة؛ يشمل الإغلاق النهائي الإصلاحات الإضافية الصغيرة. لا تغييرات اعتماديات/brand_tokens.

## الملفات
- `Admin Panel/Modules/ServiceManagement/Entities/Service.php`
- `Admin Panel/Modules/ServiceManagement/Http/Controllers/Web/Admin/ServiceController.php`
- `Admin Panel/Modules/ServiceManagement/Resources/views/admin/create.blade.php`
- `Admin Panel/Modules/ServiceManagement/Resources/views/admin/edit.blade.php`
- `Admin Panel/Modules/ServiceManagement/Database/Migrations/2026_10_07_000002_add_service_gallery_images.php`
- `Admin Panel/Modules/ServiceManagement/Resources/views/admin/partials/_gallery.blade.php`
- `Admin Panel/Modules/ServiceManagement/Services/ServiceGalleryService.php`
- `Admin Panel/resources/lang/ar/journey.php`
- `Admin Panel/resources/lang/en/journey.php`
- `User app and web/assets/language/ar.json`
- `User app and web/assets/language/en.json`
- `User app and web/lib/common/widgets/service_widget_vertical.dart`
- `User app and web/lib/feature/create_post/widget/subcategory_service_view.dart`
- `User app and web/lib/feature/home/home_screen.dart`
- `User app and web/lib/feature/home/web_home_screen.dart`
- `User app and web/lib/feature/service/model/service_model.dart`
- `User app and web/lib/feature/service/view/service_details_screen.dart`
- `User app and web/lib/feature/service/widget/service_info_card.dart`
- `User app and web/lib/feature/service/widget/service_overview.dart`
- `User app and web/lib/feature/service/widget/service_gallery.dart`
