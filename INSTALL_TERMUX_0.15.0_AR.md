# تشغيل MADRASA 0.15.0 على Termux

> خذ نسخة احتياطية من مجلد المشروع وقاعدة البيانات قبل التحديث.

## 1) تحديث الملفات
```bash
cd ~/projects
cp -a MADRASA MADRASA-backup-0.14.0-$(date +%Y%m%d-%H%M%S)
unzip -o ~/storage/downloads/madrasa-platform-0.15.0-content-enrollment.zip -d ~/projects
```
احتفظ بملف `.env` وقاعدة البيانات. لا تستبدلهما بملفات من النسخة المضغوطة.

## 2) تشغيل الخلفية
```bash
cd ~/projects/MADRASA/backend
composer install
mkdir -p storage/app/private
php artisan migrate
php artisan optimize:clear
php artisan serve --host 0.0.0.0 --port 8000
```
لا تستخدم `php artisan migrate:fresh`.

## 3) تشغيل الواجهة — جلسة Termux ثانية
```bash
cd ~/projects/MADRASA/frontend
npm install
npm run build
npm run dev -- --host 0.0.0.0
```
افتح `http://127.0.0.1:5173`.

## 4) الرفع
الحد الأعلى للملف 50MB. إذا رفض PHP ملفات كبيرة، اضبط `upload_max_filesize` و`post_max_size`. تحفظ الملفات داخل `backend/storage/app/private` ولا تنشئ لها رابطاً عاماً.

## 5) اختبار النسخة
1. سجل الدخول كمدير.
2. افتح «الوحدات والدروس»، اختر المقرر، أضف وحدة ودرساً وارفع PDF وانشره.
3. ادخل بحساب الطالب المرتبط بالصف نفسه وتأكد من ظهور الدرس والمرفق.
4. أكمل الدرس وتحقق من تحديث التقدم.
5. افتح «تسجيل الطلاب والنقل»، سجّل طالباً، ثم جرّب النقل أو الترقية وتحقق من سجل الحركة.

حسابات الاختبار تُنشأ بواسطة seeder في بيئة `local/testing` فقط: `admin@madrasa.test` و`teacher@madrasa.test` و`student@madrasa.test`، وكلمة المرور `MadrasaDemo!2026`.

**ملاحظة الاختبار:** فحص PHP نجح. لم يكتمل بناء React في بيئة إعداد الملف لعدم توفر حزم npm؛ شغّل `npm install` ثم `npm run build` على Termux وتأكد من نجاحهما قبل الإنتاج. كما لم تتوفر Composer هنا لتشغيل اختبارات Laravel.
