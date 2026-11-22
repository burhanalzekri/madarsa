# تثبيت MADRASA 0.25.0 على Termux

1. فك ضغط الملف وانتقل إلى مجلد المشروع.
2. جهّز backend وفق ملف `.env.example`، ثم نفّذ:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0
```

3. في جلسة Termux أخرى شغّل الواجهة:

```bash
cd frontend
npm install
npm run build
npm run dev -- --host 0.0.0.0
```

4. افتح عنوان Vite الظاهر في الطرفية. اختبر تسجيل الدخول بحسابات المدرسة الفعلية أو بيانات البيئة التجريبية التي تم إعدادها في قاعدة البيانات.

> لا تنفذ `migrate:fresh` على قاعدة بيانات تحتوي بيانات حقيقية.
