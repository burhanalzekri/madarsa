# تشغيل MADRASA 0.31.0 على Termux

## 1) تجهيز Termux
```bash
pkg update -y && pkg upgrade -y
pkg install -y nodejs-lts php composer unzip git
```

## 2) فك المشروع
انقل `MADRASA-0.31.0.zip` إلى مجلد التنزيلات، ثم:
```bash
cd ~/storage/downloads
unzip -o MADRASA-0.31.0.zip -d ~/madrasa
cd ~/madrasa/MADRASA
```
إذا لم تكن صلاحية التخزين مفعلة نفّذ `termux-setup-storage` أولاً.

## 3) إعداد Laravel
```bash
cd ~/madrasa/MADRASA/backend
cp .env.example .env
composer install
php artisan key:generate
```
افتح `.env` واضبط بيانات قاعدة البيانات وبيانات البيئة المناسبة لجهازك. ثم نفّذ:
```bash
php artisan migrate --seed
php artisan storage:link
```
لا تنفذ `migrate:fresh` على قاعدة بيانات تحتوي بيانات فعلية لأنه يحذف الجداول والبيانات.

## 4) تثبيت الواجهة
في جلسة Termux نفسها أو جلسة جديدة:
```bash
cd ~/madrasa/MADRASA/frontend
npm install
npm run build
```

## 5) تشغيل الخادم والواجهة
الخادم:
```bash
cd ~/madrasa/MADRASA/backend
php artisan serve --host 0.0.0.0 --port 8000
```
الواجهة للتطوير:
```bash
cd ~/madrasa/MADRASA/frontend
npm run dev -- --host 0.0.0.0
```
افتح عنوان Vite الذي يظهر في الطرفية، غالباً `http://127.0.0.1:5173`.

## ملاحظات
- تأكد من إعداد `VITE_API_BASE_URL` في `frontend/.env` ليتصل عنوان الواجهة بخادم Laravel، مثلاً `http://127.0.0.1:8000/api` عند التشغيل على الجهاز نفسه.
- إذا فتحت المنصة من هاتف آخر على الشبكة، استخدم عنوان IP المحلي للجهاز بدلاً من `127.0.0.1` في إعدادات الاتصال.
- راجع `README_AR.md` و`TERMUX_GUIDE_AR.md` للتفاصيل الإضافية.
- إصدار 0.31.0 لا يضيف migrations جديدة.
