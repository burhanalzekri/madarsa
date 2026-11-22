# تشغيل MADRASA 0.34.0 على Termux

1. ثبّت Termux من مصدره الرسمي ثم نفّذ:

```bash
pkg update -y && pkg upgrade -y
pkg install -y php composer nodejs-lts git unzip
```

2. فك ضغط المشروع وانتقل إلى مجلد المشروع:

```bash
unzip MADRASA-0.34.0.zip
cd MADRASA
```

3. جهّز Laravel:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
mkdir -p database
touch database/database.sqlite
```

تأكد من أن إعداد SQLite في `backend/.env` يشير إلى `database/database.sqlite` (وهو الإعداد الافتراضي)، ثم نفّذ:

```bash
php artisan migrate --seed
php artisan optimize:clear
```

بيانات العرض تُنشأ تلقائياً في بيئة `local` أو `testing` فقط. غيّر `DEMO_ACCOUNT_PASSWORD` في `.env` قبل مشاركة بيئة العرض.

4. شغّل الواجهة الخلفية في نافذة Termux الأولى:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

5. في نافذة Termux ثانية، شغّل الواجهة الأمامية:

```bash
cd ~/MADRASA/frontend
npm install
npm run build
npm run dev -- --host 0.0.0.0
```

افتح عنوان Vite الذي يظهر في الطرفية. اضبط عنوان API في إعدادات المشروع/ملف البيئة الأمامية إذا كان عنوان الخادم مختلفاً عن الإعداد الافتراضي.

## حسابات تجريبية
كلمة المرور الافتراضية لجميع حسابات العرض: `MadrasaDemo!2026`

- الإدارة: `admin@madrasa.test`
- المعلم: `teacher@madrasa.test`
- الطالب الأول: `student@madrasa.test`
- طلاب الشعبة أ: `student.a02@madrasa.test` إلى `student.a10@madrasa.test`
- طلاب الشعبة ب: `student.b01@madrasa.test` إلى `student.b10@madrasa.test`
- ولي الأمر: `parent@madrasa.test`

## ملاحظة الوسائط
روابط الفيديو والصوت في الدروس التجريبية أمثلة عامة للتأكد من ظهور عناصر التشغيل؛ استبدلها بروابط فيديو وصوت تعليمية تخص الدروس عند الاستخدام الفعلي.
