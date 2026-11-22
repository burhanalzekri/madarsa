# تشغيل MADRASA 0.14.0 على Termux

## 1) تجهيز Termux
```bash
pkg update -y && pkg upgrade -y
pkg install nodejs-lts php composer unzip -y
termux-setup-storage
```

## 2) تحديث المشروع مع الحفاظ على قاعدة البيانات والإعدادات
ضع ملف `madrasa-platform-0.14.0-academic-management.zip` في مجلد Downloads، ثم نفّذ الأوامر التالية. ستأخذ نسخة من `.env` وقاعدة SQLite، وتنقل المشروع القديم إلى مجلد احتياطي قبل فك النسخة الجديدة:

```bash
cd ~/projects
mkdir -p ~/projects/MADRASA-backup-0.13.0
cp -a ~/projects/MADRASA/backend/.env ~/projects/MADRASA-backup-0.13.0/.env 2>/dev/null || true
cp -a ~/projects/MADRASA/backend/database/database.sqlite ~/projects/MADRASA-backup-0.13.0/database.sqlite 2>/dev/null || true
mv ~/projects/MADRASA ~/projects/MADRASA-backup-$(date +%Y%m%d-%H%M%S)
unzip -o ~/storage/downloads/madrasa-platform-0.14.0-academic-management.zip -d ~/projects
cp -a ~/projects/MADRASA-backup-0.13.0/.env ~/projects/MADRASA/backend/.env 2>/dev/null || true
mkdir -p ~/projects/MADRASA/backend/database
cp -a ~/projects/MADRASA-backup-0.13.0/database.sqlite ~/projects/MADRASA/backend/database/database.sqlite 2>/dev/null || true
```

إذا لم يكن لديك مشروع سابق في `~/projects/MADRASA`، فك الملف مباشرة إلى `~/projects` وتابع إلى إعداد Laravel.

## 3) إعداد Laravel
```bash
cd ~/projects/MADRASA/backend
composer install
```

إذا كنت تحدث نسخة موجودة، انسخ ملف `.env` وقاعدة البيانات القديمة إلى النسخة الجديدة قبل تشغيل Artisan. لا تنشئ APP_KEY جديداً عند وجود مفتاح مستخدم بالفعل.

للتثبيت الأول فقط:
```bash
cp .env.example .env
php artisan key:generate
mkdir -p database
touch database/database.sqlite
```

ثم:
```bash
php artisan migrate --seed
php artisan optimize:clear
php artisan serve --host 0.0.0.0 --port 8000
```

`migrate --seed` يضيف بيانات العرض التجريبية بطريقة لا تتطلب حذف الجداول. لا تستخدم `php artisan migrate:fresh`.

## 4) تشغيل React
افتح جلسة Termux ثانية:
```bash
cd ~/projects/MADRASA/frontend
npm install
npm run build
npm run dev -- --host 0.0.0.0
```

افتح المتصفح على:
`http://127.0.0.1:5173`

## 5) الحسابات التجريبية
- مدير المدرسة: `admin@madrasa.test`
- المعلم: `teacher@madrasa.test`
- الطالب: `student@madrasa.test`
- كلمة المرور: `MadrasaDemo!2026`

الحسابات التجريبية ينشئها Seeder في بيئة `local` أو `testing` فقط. لا تستخدمها في الإنتاج.

## 6) اختبار المرحلة
1. سجل الدخول كمدير.
2. افتح «الإدارة التعليمية» ثم «الهيكل الأكاديمي».
3. راجع المرحلة والصف والسنة والفصل والمادة التجريبية.
4. أضف مادة جديدة أو صفاً تجريبياً وتأكد من ظهوره بعد تحديث الصفحة.
5. اربط المادة بالصف والفصل الدراسي.
6. أنشئ شعبة ثم أضف إسناداً لمعلم.
7. سجل الخروج وجرب دخول المعلم والطالب، وتأكد من عدم ظهور بوابة الإدارة لهما.

إذا ظهر خطأ API، راجع سجل Laravel:
```bash
tail -n 80 ~/projects/MADRASA/backend/storage/logs/laravel.log
```
