# تثبيت وتشغيل MADRASA 0.33.0 على Termux

## 1) المتطلبات
```bash
pkg update -y && pkg upgrade -y
pkg install -y nodejs-lts php composer unzip git
```

## 2) فك المشروع
ضع ملف `MADRASA-0.33.0.zip` في مجلد التنزيلات، ثم:
```bash
termux-setup-storage
mkdir -p ~/MADRASA
unzip /sdcard/Download/MADRASA-0.33.0.zip -d ~/MADRASA
cd ~/MADRASA/MADRASA
```
إذا كان اسم المجلد بعد فك الضغط مختلفاً، ادخل إلى المجلد الذي يحتوي `frontend` و`backend`.

## 3) تجهيز الواجهة الأمامية
```bash
cd ~/MADRASA/MADRASA/frontend
npm install
npm run build
```

## 4) تجهيز الخادم
```bash
cd ~/MADRASA/MADRASA/backend
cp .env.example .env
composer install
php artisan key:generate
```
راجع إعدادات قاعدة البيانات داخل `.env` قبل تشغيل الترحيلات. لا تشغّل `migrate:fresh` على قاعدة بيانات تحتوي بيانات فعلية لأنه يحذف الجداول والبيانات.
```bash
php artisan migrate --seed
```
استخدم `--seed` فقط إذا كانت البذور مناسبة لبيئتك؛ في الإنتاج راجع بيانات البذور أولاً.

## 5) التشغيل
في جلسة Termux أولى:
```bash
cd ~/MADRASA/MADRASA/backend
php artisan serve --host 0.0.0.0 --port 8000
```
وفي جلسة ثانية:
```bash
cd ~/MADRASA/MADRASA/frontend
npm run dev -- --host 0.0.0.0
```
افتح عنوان Vite الذي يظهر في الطرفية، غالباً `http://127.0.0.1:5173`.

## ملاحظات
- تأكد من إعداد عنوان API في `frontend/.env` وفق ملف `.env.example` وإعدادات المشروع.
- لا ترفع ملف `.env` أو مفاتيح الدخول إلى مستودع عام.
- يجب إكمال `npm run build` واختبار تسجيل الدخول وصلاحيات المعلم قبل النشر.
