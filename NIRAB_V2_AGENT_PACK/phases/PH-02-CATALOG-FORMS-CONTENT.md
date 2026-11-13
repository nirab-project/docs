# PH-02 — الكتالوج والنماذج والمحتوى

**التنفيذ:** CODE ONLY. تسبقها PH-01. لا بيئة ولا DB ولا تشغيل اختبارات في هذه المرحلة.

## اقرأ أولاً
- [specs/S02-SERVICE-STUDIO-FORMS.md](../specs/S02-SERVICE-STUDIO-FORMS.md)

## المهام المتسلسلة
1. نفذ Service/Offer draft-version-publish-pause/archive وcontent/media، مع snapshots وcopy service؛ أكواد مستقلة عن الأسماء واللغة.
2. نفذ form compiler/validator/normalizer لكل نوع، conditions DAG وحدود العمق/التكرار، وstable instance IDs وحذف hidden answers. لا تجلب السعر من نص الوصف.
3. نفذ reference-data dependency مثل car brand→model وتفضيلات الغسيل والخانات الشرطية. مكتبة components موحدة لAPI/admin/client.
4. نفذ RequirementsEvaluator من قواعد config: مدة ومهارات وفريق ومهام وبفر وكمية فعلية؛ حفظ capability registry وmapping واجهات المهارات.
5. نفذ uploads init/finalize/read-link/private authorization وMIME/size limits وorphan cleanup job غير مشغّل. لمحتوى الخدمة assets نيراب/placeholder أصلي محلي فقط.
6. نفذ publish/simulate validation وتحديث city readiness ليقرأ V2 offers دون دورة تمنع تهيئة أول خدمة. جغرافيا/حارس المدينة لا يتغيران.
7. جهز templates التسع كتعريفات قابلة للتحرير وليس services names switches. لا إنشاء حساب/طلب خارجي بمجرد view.

## مخرجات إلزامية
- Catalog/Form/Requirements services وAPIs
- content/media authorization
- service templates code
- اختبارات PH-02 المكتوبة

## اختبارات تكتب هنا وتنفذ فقط في PH-12
repeat غرف مختلفة، حد العدد، cycle detection، missing required عند visibility، false vs null، archive published referenced، schema change أثناء draft، private media IDOR.

## بوابة اكتمال الكود
كل component مدعوم خادمياً مع validation وpublication، وبإمكان seed خدمة جديدة بلا if حسب اسمها.

## التقرير
استخدم `templates/PHASE-REPORT.md`: الملفات المعدلة، قرارات التنفيذ، العقود المتأثرة، مراجعة static، `runtime_tests=not_run_by_design`، المخاطر. لا تسمح لهذا التقرير بإعلان نجاح runtime. أي نقص ينتقل كسجل صريح ولا يترك وظيفة مطلوبة غير منفذة عند CODE-READY.
