# تشغيل MADRASA 0.43.0 في Termux

## المتطلبات
- Termux محدث، PHP 8.2+، Composer، Node.js/npm، وSQLite أو MySQL.

## فك الضغط
```bash
pkg update -y && pkg upgrade -y
pkg install php composer nodejs unzip -y
termux-setup-storage
cd ~/storage/downloads
unzip MADRASA-0.43.0.zip -d MADRASA-0.43.0
cd MADRASA-0.43.0
```

## الخادم الخلفي
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```
اضبط قاعدة البيانات في `.env` ثم نفذ: `php artisan migrate --seed`. إذا كنت تحدث تثبيتاً سابقاً، احتفظ بنسخة احتياطية ولا تستبدل قاعدة البيانات.

شغّل الخادم: `php artisan serve --host 0.0.0.0 --port 8000`.

## الواجهة
في جلسة Termux ثانية:
```bash
cd ~/storage/downloads/MADRASA-0.43.0/frontend
npm install
npm run dev -- --host 0.0.0.0
```
افتح عنوان Vite الظاهر في الطرفية.

## استخدام المرحلة الرابعة
1. سجل الدخول كمدير مدرسة.
2. افتح «الدروس والاختبارات وإنجاز الطلاب».
3. من تبويب «الاختبارات» أضف أسئلة متعددة، وحدد نوع ودرجة كل سؤال. تأكد أن مجموع الدرجات لا يتجاوز الدرجة النهائية.
4. افتح «تحليل المواد والتنبيهات» لمراجعة مستوى الإنجاز حسب المادة والشعبة والطلاب الذين لديهم واجبات متأخرة أو إنجاز منخفض.
5. راجع الحالات مع المعلم؛ التنبيهات لا ترسل رسائل تلقائية.

## ملاحظات
- يلزم إسناد المادة للمعلم المختار قبل إنشاء اختبار.
- الإجابات المقالية تتطلب تصحيح المعلم.
- لم يتم اختبار البناء الإنتاجي وعمليات قاعدة البيانات في بيئة التطوير الحالية؛ نفذ `php artisan test` و`npm run build` على جهازك قبل الاستخدام الرسمي.
