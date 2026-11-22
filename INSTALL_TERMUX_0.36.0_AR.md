# تشغيل MADRASA 0.36.0 على Termux

## المتطلبات
- Termux محدث.
- PHP وComposer وNode.js وnpm.
- قاعدة بيانات MySQL/MariaDB وفق إعداد المشروع.

## التثبيت
1. فك ضغط `MADRASA-0.36.0.zip`.
2. افتح مجلد المشروع ثم مجلد `backend`.
3. انسخ `.env.example` إلى `.env` واضبط اتصال قاعدة البيانات وبيانات التطبيق.
4. نفّذ:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
```

5. في نافذة Termux ثانية، افتح `frontend` ونفّذ:

```bash
npm install
npm run build
npm run dev -- --host 0.0.0.0
```

6. شغّل خادم Laravel من مجلد `backend`:

```bash
php artisan serve --host 0.0.0.0
```

استخدم عنوان الشبكة المحلي الذي يعرضه Vite لفتح الواجهة. راجع `README_AR.md` وملف `.env.example` لأي إعدادات إضافية خاصة ببيئتك.

> إذا كان المشروع مثبتاً مسبقاً، احتفظ بملف `.env` وقاعدة بياناتك. لا تحذف قاعدة البيانات ولا تعِد تشغيل seeder على بيانات حقيقية قبل أخذ نسخة احتياطية.
