# UX — أوامر وسجل الفحص الساكن

التاريخ 2026-10-07. PHP 8.4.24 / Dart 3.11.1 windows_x64 / Git 2.53.0.windows.2 / Python 3.14.3 كانت متاحة؛ لم تثبت حزم أو تحدث SDK.

المدى من SHA في UX_BASELINE.json إلى المصدر النهائي؛ UX_CHANGED_FILES.json يسجل كل مسار source مع SHA-256. UX_STATIC_SUMMARY.json نتيجة فحص UTF-8 وJSON والترجمات والملفات المحمية وgit diff. تقارير UX-01..05 تسجل فحوصها المرحلية؛ التالي فحص تجميعي جديد على النسخة النهائية، لا ترحيل نجاح من نسخة سابقة.

```powershell
php --version
& 'C:\flutter\bin\cache\dart-sdk\bin\dart.exe' --version
git --version
python --version
# داخل كل مشروع Flutter، باستخدام sha ذلك المشروع من UX_BASELINE.json:
$files = @(git diff $baselineSha --name-only -- '*.dart')
& 'C:\flutter\bin\cache\dart-sdk\bin\dart.exe' format $files
& 'C:\flutter\bin\cache\dart-sdk\bin\dart.exe' analyze $files
# داخل Admin Panel:
$files = @(git diff 3ef8528c6cf01e82ebb2ab4d9ebf30b73cf02846 --name-only -- '*.php' | Where-Object { $_ -notlike '*.blade.php' })
foreach ($file in $files) { php -l $file }
# لكل repo:
git diff $baselineSha --check
git status --short
git branch --show-current
git log --oneline "$baselineSha..HEAD"
```

- PHP: 19 ملفاً، جميعها نجاح؛ السجل UX_STATIC_PHP.txt. أعيد lint لملفات PHP الثلاثة بعد إصلاحات تدقيق العنوان/أهلية reserve النهائية ونجح. Blade: ثلاثة قوالب راجعت نصياً فقط.
- Dart: 42 ملفاً إجمالاً (38 عميل/ويب + 2 فرع + 2 فني). لا errors أو warnings؛ 19/1/8 info في سجلات UX_STATIC_DART_*.txt. format نهائي دون تنزيل حزم.
- Python standard library فقط: decode UTF-8 للـ70 ملف source المعدل؛ رفض U+FFFD؛ json.loads لست ملفات ترجمة؛ مطابقة مفاتيح ux_ وliteral .tr في الإضافات بالعربية والإنجليزية؛ مطابقة مفاتيح journey/quality PHP نصياً. جميعها بلا مفاتيح ناقصة ضمن النطاق المفحوص.
- git diff --check للـsource repos: exit 0. manifests/lockfiles/.env/brand_tokens/fonts لم ترد في أسماء الفروق. فحص نمطي لمفاتيح خاصة/tokens معروفة في الإضافات لم يجد تطابقاً، دون طباعة قيم حساسة.
- لا JavaScript معدلاً، فلا node --check مطلوب. لا تحليل متعذر بسبب نقص الأدوات. المحاولة المرحلية `dart analyze --no-fatal-infos` رُفض خيارها؛ أعيد الأمر المدعوم بنجاح، ولم يحتسب الرفض نجاحاً.
- تتبع يدوي: auth/ownership، expiry، خدمة فعالة وغير محذوفة، geo، branch coverage/capacity، locks/transactions/idempotency، فصل payment/service/invoice، gallery uploads/escaping، توافق الحقول والمسارات.

هذه الأوامر ليست تشغيل Laravel أو تطبيق Dart/Flutter، ولم تجر اختبارات أو migrations أو network integration.
