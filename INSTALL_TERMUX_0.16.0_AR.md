# تشغيل وتحديث MADRASA 0.16.0 على Termux

> التحديث يحافظ على قاعدة SQLite وملف `.env` الموجودين. لا تحذف قاعدة البيانات ولا تستخدم `migrate:fresh`.

## 1) أوقف الخادمين القديمين
في جلستي Termux اللتين يعمل فيهما Laravel وVite اضغط `Ctrl+C`.

## 2) خذ نسخة احتياطية كاملة
```bash
cd ~/projects
cp -a MADRASA "MADRASA-backup-$(date +%Y%m%d-%H%M%S)"
```

## 3) فك الإصدار الجديد فوق المشروع
ضع ZIP في Downloads ثم نفّذ:
```bash
cd ~/projects
unzip -o ~/storage/downloads/madrasa-platform-0.16.0-assessment-login-fix.zip -d ~/projects
```

ملف `.env` وقاعدة `database/database.sqlite` غير مضمنين في ZIP حتى لا يستبدل ملفك المحلي.

## 4) حدّث Laravel وقاعدة البيانات
```bash
cd ~/projects/MADRASA/backend
composer install
php artisan migrate --seed
php artisan optimize:clear
php artisan serve --host 0.0.0.0 --port 8000
```

إذا لم تكن Composer مثبتة: `pkg install composer -y`، ثم أعد `composer install`.

## 5) شغّل React/Vite في جلسة Termux ثانية
```bash
cd ~/projects/MADRASA/frontend
cp .env.example .env.local
npm install
npm run build
npm run dev -- --host 0.0.0.0 --port 5173
```

إذا سأل النظام عن استبدال `.env.local` فوافق؛ المطلوب أن يحتوي على:
```env
VITE_API_BASE_URL=/api/v1
API_PROXY_TARGET=http://127.0.0.1:8000
```

## 6) افتح المنصة وافحص الاتصال
افتح في متصفح الهاتف:
`http://127.0.0.1:5173`

واختبر نقطة صحة الخادم مباشرة:
`http://127.0.0.1:8000/api/health`

يجب أن ترى JSON فيه `"status":"ok"`. كما يجب أن تعمل نقطة الفحص عبر Vite:
`http://127.0.0.1:5173/api/health`

إذا ظهرت `Failed to fetch` مرة أخرى:
1. تأكد أن نافذة Laravel ما زالت تعمل على المنفذ 8000.
2. تأكد أن Vite يعمل على المنفذ 5173.
3. نفّذ `cp .env.example .env.local` داخل frontend ثم أوقف Vite وأعد تشغيله؛ Vite لا يقرأ تغييرات env بعد بدء التشغيل.
4. افتح العنوان `127.0.0.1` لا عنواناً قديماً محفوظاً في المتصفح، ثم حدّث الصفحة تحديثاً كاملاً.

## 7) حسابات الاختبار
تُنشأ/تُحدّث الحسابات التجريبية محلياً عند تنفيذ seeder:
- المدير: `admin@madrasa.test`
- المعلم: `teacher@madrasa.test`
- الطالب: `student@madrasa.test`
- كلمة المرور: `MadrasaDemo!2026`

لا تعمل هذه الحسابات إلا في `local` أو `testing`.

## ملاحظات مهمة
- لا تستبدل `.env` ولا تغيّر `APP_KEY` الموجود.
- لا تنفذ `php artisan migrate:fresh` أو تحذف ملف SQLite.
- نفّذ `php artisan migrate --seed` فقط؛ الـ seeder مصمم لتحديث حسابات وبيانات العرض دون حذف سجلاتك.
- إذا كان لديك بيانات فعلية، خذ نسخة احتياطية مستقلة قبل أي تحديث.
