# تشغيل وتجهيز MADRASA 0.37.0 على Termux

> استخدم Termux محدثاً. يفضل تشغيل الخادم على جهاز/استضافة موثوقة للمدرسة، وليس هاتفاً شخصياً كخادم إنتاج دائم.

## 1) المتطلبات
```bash
pkg update && pkg upgrade -y
pkg install php nodejs-lts git unzip -y
```
ثبّت Composer وفق تعليمات Composer الرسمية المتوافقة مع Termux، وتأكد من توفر `composer --version`.

## 2) فك الضغط
```bash
termux-setup-storage
cd ~/storage/downloads
unzip MADRASA-0.37.0.zip
cd MADRASA/backend
```

## 3) إعداد Laravel
```bash
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
```
افتح `.env` واضبط `APP_ENV=production` و`APP_DEBUG=false` و`APP_URL` إلى عنوان HTTPS الفعلي. اضبط قاعدة البيانات بما يناسب استضافتك. لا تترك كلمات مرور افتراضية.

### قاعدة SQLite (تجربة محلية فقط)
```bash
mkdir -p database
 touch database/database.sqlite
```
في `.env` استخدم `DB_CONNECTION=sqlite` و`DB_DATABASE=database/database.sqlite`.

### إنشاء الجداول ومدير المدرسة
```bash
php artisan migrate --force
php artisan madrasa:create-admin
```
سيطلب الأمر اسم المدير وبريده واسم المدرسة وكلمة مرور مخفية وتأكيدها. اختر كلمة مرور قوية لا تقل عن 12 حرفاً. لا ترسلها لأحد ولا تحفظها داخل ملفات المشروع.

**لا تنفذ `php artisan migrate --seed` في الإنتاج.** بيانات العرض التجريبي متاحة فقط في بيئتي `local/testing`.

## 4) إعداد الواجهة
في نافذة Termux ثانية:
```bash
cd ~/storage/downloads/MADRASA/frontend
npm install
npm run build
```
انشر محتويات `frontend/dist` على خادم HTTPS مضبوط لتمرير `/api` إلى Laravel، أو استخدم خادم الواجهة للتطوير فقط:
```bash
npm run dev -- --host 0.0.0.0
```

## 5) تشغيل Laravel محلياً للاختبار فقط
```bash
cd ~/storage/downloads/MADRASA/backend
php artisan serve --host 0.0.0.0 --port 8000
```
لا تستخدم `artisan serve` كخادم إنتاج عام.

## 6) فحوص ما قبل الاستخدام
```bash
cd ~/storage/downloads/MADRASA/backend
php artisan route:list --path=api
php artisan test
cd ../frontend
npm run build
```
أنشئ حساب مدير، ثم سجّل الدخول وأضف معلماً وطالباً وصفاً وشعبة، واربط الطالب بولي أمر، ثم اختبر الدخول بكل دور والتأكد من عدم الوصول إلى بيانات دور آخر.

## 7) النسخ الاحتياطي
- SQLite: أوقف الكتابة مؤقتاً ثم انسخ ملف `backend/database/database.sqlite` إلى وسيط آمن مشفر.
- MySQL/MariaDB: استخدم `mysqldump` ببيانات اعتماد محفوظة خارج المستودع.
- احتفظ بنسخ يومية وأسبوعية، واختبر الاستعادة دورياً.
- لا تعتبر تصدير JSON من المتصفح نسخة احتياطية لقاعدة بيانات المدرسة.

## 8) حسابات العرض
لا توجد كلمات مرور عرض مخصصة للإنتاج. حسابات `@madrasa.test` وكلمة مرور العرض مخصصة حصراً لبيئات `local/testing` ولا تستخدم في المدرسة.

## ملاحظات وحدود معروفة
- وحدات الإدارة المحلية موضحة بتنبيه داخل الواجهة؛ لا تستخدمها كسجلات رسمية.
- إعدادات الهوية الحالية محلية في المتصفح ولا تحفظ في الخادم.
- هذا الإصدار يحتاج اجتياز بناء Vite واختبارات Laravel على جهاز التشغيل قبل إدخال بيانات فعلية.
