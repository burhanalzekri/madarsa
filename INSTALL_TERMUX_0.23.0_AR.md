# تثبيت وتشغيل MADRASA 0.23.0 على Android باستخدام Termux

هذا الدليل يشمل التثبيت لأول مرة والتحديث من إصدار سابق. نفّذ أوامر كل جلسة كما هي، مع تعديل اسم مجلد المشروع فقط إذا اخترت اسماً مختلفاً.

## أ) تجهيز Termux

يفضل استخدام إصدار حديث من Termux. افتح Termux ونفّذ:

```bash
pkg update && pkg upgrade -y
pkg install nodejs-lts php composer unzip git nano curl -y
termux-setup-storage
mkdir -p ~/projects
```

تحقق من الأدوات:

```bash
node -v
npm -v
php -v
composer -V
php -m | grep -Ei 'pdo|sqlite|mbstring|openssl|fileinfo|tokenizer|xml|curl'
```

يلزم PHP 8.2 أو أحدث، وامتدادات Laravel المعتادة، وبالأخص `pdo_sqlite` عند استخدام SQLite.

## ب) وضع ملف ZIP وفك ضغطه

1. نزّل `madrasa-platform-0.23.0-academic-reports.zip` إلى مجلد Downloads في الهاتف.
2. نفّذ:

```bash
cp ~/storage/downloads/madrasa-platform-0.23.0-academic-reports.zip ~/projects/
mkdir -p ~/projects/madrasa-023-package
unzip -q ~/projects/madrasa-platform-0.23.0-academic-reports.zip -d ~/projects/madrasa-023-package
mv ~/projects/madrasa-023-package/MADRASA ~/projects/MADRASA-0.23.0
cd ~/projects/MADRASA-0.23.0
```

إذا كان اسم الملف الذي نزّلته مختلفاً، استخدم الاسم الفعلي في أمر `cp`.

## ج) إعداد Laravel لأول مرة

```bash
cd ~/projects/MADRASA-0.23.0/backend
composer install
cp .env.example .env
mkdir -p database
 touch database/database.sqlite
```

افتح الإعدادات:

```bash
nano .env
```

للتشغيل المحلي بقاعدة SQLite، تأكد من القيم التالية (لا تمسح بقية إعدادات الملف):

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
DB_FOREIGN_KEYS=true
```

احفظ في nano عبر `Ctrl+O` ثم Enter ثم `Ctrl+X`.

بعد ذلك:

```bash
php artisan key:generate
php artisan config:clear
php artisan migrate --seed
php artisan test
```

> `migrate --seed` مناسب لقاعدة جديدة فقط. لا تعِد تشغيل seed عشوائياً على قاعدة تحتوي بيانات حقيقية.

## د) إعداد الواجهة وبناؤها

```bash
cd ~/projects/MADRASA-0.23.0/frontend
cp .env.example .env.local
npm install
npm run build
```

إذا انتهى الأمر بنجاح ستظهر مجلدات البناء داخل `frontend/dist`.

## هـ) تشغيل المنصة — جلستان منفصلتان

**الجلسة الأولى: Laravel API**

```bash
cd ~/projects/MADRASA-0.23.0/backend
php artisan serve --host 0.0.0.0 --port 8000
```

اترك الجلسة مفتوحة. اختبر الخادم من المتصفح:
`http://127.0.0.1:8000/api/health`

**الجلسة الثانية: واجهة React/Vite**

افتح New Session في Termux ثم:

```bash
cd ~/projects/MADRASA-0.23.0/frontend
npm run dev -- --host 0.0.0.0
```

افتح في متصفح الهاتف:
`http://127.0.0.1:5173`

لإيقاف أي خادم، ارجع إلى جلسته واضغط `Ctrl+C`.

## و) اختبار الإصدار

1. سجّل الدخول بحساب طالب فعلي.
2. افتح «نتائجي وتقاريري» وتحقق من ظهور الحضور والدرجات المعتمدة.
3. اضغط «طباعة التقرير» وتحقق من معاينة الطباعة.
4. سجّل الدخول بحساب ولي أمر مرتبط بطالب.
5. افتح ملف الابن واضغط «عرض التقرير الأكاديمي الشامل» ثم جرّب الطباعة.
6. تحقق من أن ولي الأمر لا يستطيع فتح تقرير طالب غير مرتبط بحسابه.

## ز) تحديث مشروع موجود مع الحفاظ على البيانات

**لا تحذف مجلد المشروع القديم ولا تستبدل قاعدة البيانات ولا تنفذ `migrate:fresh`.** أنشئ نسخة احتياطية أولاً.

إذا كان المشروع السابق في `~/projects/MADRASA` ويستخدم SQLite:

```bash
cd ~/projects
mkdir -p backups
cp -a MADRASA/backend/database/database.sqlite backups/database-before-0.23.0.sqlite 2>/dev/null || true
cp -a MADRASA/backend/.env backups/backend-env-before-0.23.0 2>/dev/null || true
```

بعد فك الإصدار الجديد في `~/projects/MADRASA-0.23.0`، انسخ إعدادات البيئة وقاعدة البيانات:

```bash
cp ~/projects/MADRASA/backend/.env ~/projects/MADRASA-0.23.0/backend/.env
cp ~/projects/MADRASA/backend/database/database.sqlite ~/projects/MADRASA-0.23.0/backend/database/database.sqlite
```

ثم حدّث الاعتماديات ونفّذ الترحيلات الآمنة:

```bash
cd ~/projects/MADRASA-0.23.0/backend
composer install --no-interaction
php artisan optimize:clear
php artisan migrate --force
```

وابنِ الواجهة:

```bash
cd ~/projects/MADRASA-0.23.0/frontend
npm install
npm run build
```

شغّل الخادمين من المجلد الجديد كما في القسم (هـ). احتفظ بالمجلد القديم والنسخة الاحتياطية حتى تتأكد من نجاح الدخول والتقارير.

إذا كنت تستخدم MySQL أو PostgreSQL بدلاً من SQLite، لا تنفذ أوامر نسخ ملف قاعدة البيانات؛ احتفظ بإعدادات `.env` نفسها وخذ نسخة احتياطية من قاعدة البيانات بالطريقة المناسبة لمحركك.

## ح) حلول سريعة

- `composer: command not found`: نفّذ `pkg install composer -y`.
- `npm: command not found`: نفّذ `pkg install nodejs-lts -y`.
- خطأ SQLite driver: تحقق من `php -m` وتوفر `pdo_sqlite`.
- المنفذ 8000 أو 5173 مستخدم: أوقف الخادم السابق بـ `Ctrl+C` أو اختر منفذاً آخر واضبط عنوان API/Proxy تبعاً لذلك.
- خطأ اتصال الواجهة بالخادم: شغّل Laravel أولاً، ثم افتح `http://127.0.0.1:8000/api/health` وتأكد من أن Vite يعمل على 5173.
- لا تشارك ملف `.env` أو رموز الدخول أو بيانات الطلاب.

## تنبيه أمان

الحسابات التجريبية مخصصة لبيئة التطوير فقط إن كانت مضافة في قاعدة بياناتك. لا تستخدم `APP_DEBUG=true` أو كلمات المرور التجريبية أو خادم `artisan serve` كإعداد إنتاج عام. لا ترفع `.env` أو `vendor/` أو `node_modules/` إلى GitHub.
