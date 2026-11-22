# تثبيت MADRASA 0.24.0 على Termux

> احتفظ بنسخة احتياطية من قاعدة البيانات وملف `.env` قبل تحديث مشروع قائم. لا تستخدم `migrate:fresh` على قاعدة تحتوي بيانات مهمة.

## 1) فك الحزمة

```bash
pkg update && pkg upgrade -y
pkg install nodejs-lts php composer unzip -y
mkdir -p ~/projects/madrasa-024-package
cp ~/storage/downloads/madrasa-platform-0.24.0.zip ~/projects/
unzip -q ~/projects/madrasa-platform-0.24.0.zip -d ~/projects/madrasa-024-package
cd ~/projects/madrasa-024-package/MADRASA
```

## 2) إعداد Laravel

```bash
cd backend
composer install
```

للتثبيت الجديد فقط:

```bash
cp .env.example .env
mkdir -p database
touch database/database.sqlite
php artisan key:generate
```

تحقق في `.env` من:

```env
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
DB_FOREIGN_KEYS=true
```

ثم نفّذ:

```bash
php artisan config:clear
php artisan migrate --seed
php artisan test
```

`migrate --seed` يضيف بيانات عرض تجريبية محلية. في التحديث على قاعدة قائمة، نفّذ `php artisan migrate` فقط، ثم قرر إن كنت تريد تشغيل seed بعد أخذ نسخة احتياطية.

## 3) تثبيت وبناء React

```bash
cd ../frontend
cp .env.example .env.local
npm install
npm run build
```

## 4) تشغيل الخادمين

الجلسة الأولى:

```bash
cd ~/projects/madrasa-024-package/MADRASA/backend
php artisan serve --host 0.0.0.0 --port 8000
```

الجلسة الثانية:

```bash
cd ~/projects/madrasa-024-package/MADRASA/frontend
npm run dev -- --host 0.0.0.0
```

افتح `http://127.0.0.1:5173`.

## 5) حسابات العرض المحلية

كلمة المرور الافتراضية: `MadrasaDemo!2026`

| الدور | البريد |
|---|---|
| المدير | `admin@madrasa.test` |
| المعلم | `teacher@madrasa.test` |
| الطالب | `student@madrasa.test` |
| ولي الأمر | `parent@madrasa.test` |

الحسابات والبيانات التجريبية تُنشأ فقط عندما تكون `APP_ENV=local` أو `testing`. لا تستخدم كلمة المرور الافتراضية في الإنتاج.

## 6) اختبار بوابة الطالب

- تحقق من ظهور خمسة مقررات تجريبية على الأقل، مع الصف والشعبة والمعلم والفصل.
- افتح مقرراً وتأكد من ظهور الوحدات والدروس.
- افتح درساً ثم أكمله، وتأكد من حفظ التقدم.
- تحقق من ظهور الواجب والاختبار التجريبيين بمواعيد مستقبلية.
- أرسل الواجب ثم حدّثه قبل موعد الإغلاق.
- تحقق من الإعلانات العامة وإعلان الطلاب.
- سجّل الدخول كولي أمر وتحقق من ظهور الإعلان المخصص له.
- تحقق من رفض الطالب عند محاولة فتح `/api/v1/admin/announcements`.

## 7) اعتماد الإنتاج

لا تعتمد الإصدار إنتاجياً قبل نجاح `npm run build` و`php artisan test`، ومراجعة إعدادات قاعدة البيانات ونسخها الاحتياطية، وتغيير كلمات مرور العرض وتعطيل `APP_DEBUG`.
