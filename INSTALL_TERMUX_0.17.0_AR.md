# تحديث وتشغيل MADRASA 0.17.0 على Termux

> التحديث يحافظ على قاعدة البيانات وملف `.env`. لا تستخدم `migrate:fresh` ولا تحذف مجلد database.

## 1) نسخة احتياطية

```bash
cd ~/projects
mkdir -p ~/MADRASA-backup-0.17.0
cp -a MADRASA/backend/.env ~/MADRASA-backup-0.17.0/.env
cp -a MADRASA/backend/database ~/MADRASA-backup-0.17.0/database
```

## 2) فك الإصدار الجديد

نزّل `madrasa-platform-0.17.0-student-teacher-portals.zip` إلى Downloads ثم:

```bash
cd ~/projects
unzip -o ~/storage/downloads/madrasa-platform-0.17.0-student-teacher-portals.zip -d ~/projects
```

إذا فككت الملفات فوق المجلد الموجود، لا تستبدل `.env` أو قاعدة البيانات. إذا اخترت مجلداً جديداً، انسخ إعداداتك وقاعدة البيانات من النسخة الاحتياطية إلى `MADRASA/backend/`.

## 3) تحديث قاعدة البيانات وإضافة البيانات التجريبية

```bash
cd ~/projects/MADRASA/backend
composer install
php artisan migrate
```

لإضافة المواد الأربع الإضافية والجدول الأسبوعي التجريبي وحسابات العرض في بيئة التطوير فقط:

```bash
php artisan db:seed --class=DatabaseSeeder
```

> Seeder يعيد ضبط كلمات مرور حسابات العرض الثلاثة إلى `MadrasaDemo!2026` في بيئة local/testing. لا تشغله على الإنتاج أو على قاعدة بيانات مدرسة فعلية.

ثم:

```bash
php artisan optimize:clear
php artisan serve --host 0.0.0.0 --port 8000
```

## 4) تشغيل الواجهة

افتح جلسة Termux ثانية:

```bash
cd ~/projects/MADRASA/frontend
npm install
npm run build
npm run dev -- --host 0.0.0.0 --port 5173
```

افتح في المتصفح: `http://127.0.0.1:5173`

## 5) حسابات الاختبار

- المدير: `admin@madrasa.test`
- المعلم: `teacher@madrasa.test`
- الطالب: `student@madrasa.test`
- كلمة المرور: `MadrasaDemo!2026`

## 6) التحقق من الوظائف

1. ادخل بحساب الطالب: يجب أن تظهر مقررات الصف الحالي، اختيار المادة، الوحدات والدروس، والجدول الأسبوعي والواجبات والاختبارات. لا تظهر أدوات الإدارة.
2. اخرج وادخل بحساب المعلم: يجب أن تظهر المواد والشعب المسندة والدروس والموارد وجدول المعلم والاختبارات والحضور. لا تظهر أدوات الإدارة.
3. ادخل بحساب المدير: افتح «الجدول الدراسي» وأضف حصة من إسناد قائم. ستظهر في جدول المعلم والطلاب المرتبطين بالشعبة.
4. تحقق من أن تعارض وقت المعلم أو الشعبة يمنع حفظ الحصة.
5. افتح `http://127.0.0.1:8000/api/health` للتحقق من اتصال Laravel.

## 7) استكشاف الأخطاء

- إذا ظهر `Failed to fetch`: تأكد أن خادم Laravel يعمل في الجلسة الأولى وأن Vite يعمل في الثانية.
- إذا ظهرت رسالة لا توجد مقررات: تأكد من وجود تسجيل طالب نشط في شعبة، وسنة دراسية حالية، وفصل دراسي نشط، ومواد مرتبطة بالصف.
- إذا ظهر جدول فارغ: تأكد من وجود إسناد معلم للشعبة والمادة، ثم أضف حصصاً من لوحة المدير > الجدول الدراسي.
- لا تحذف `madrasa-session` أو قاعدة البيانات إلا عند معالجة مشكلة جلسة محددة وبعد أخذ نسخة احتياطية.
