# دليل تشغيل MADRASA 0.13.0 على Termux

الدليل يشغّل الواجهة والخادم محلياً على الهاتف لأغراض التطوير والاختبار. يفضل استخدام Termux حديث من مصدر موثوق، وإبقاء التطبيق مفتوحاً أثناء التشغيل.

## 1. تجهيز الهاتف

```bash
pkg update && pkg upgrade -y
pkg install nodejs-lts php composer unzip git -y
termux-setup-storage
mkdir -p ~/projects
```

تحقق من الإصدارات:

```bash
node -v
npm -v
php -v
composer -V
php -m | grep -i sqlite
```
إذا لم يظهر `pdo_sqlite` أو `sqlite3` في وحدات PHP، حدّث حزمة PHP في Termux أو استخدم PostgreSQL/VPS بدلاً من SQLite.

## 2. نقل وفك ضغط المشروع

ضع الملف `madrasa-platform-0.13.0-admin-crud.zip` في مجلد Downloads، ثم:

```bash
cp ~/storage/downloads/madrasa-platform-0.13.0-admin-crud.zip ~/projects/
cd ~/projects
unzip -o madrasa-platform-0.13.0-admin-crud.zip
cd MADRASA
```
إذا اختلف اسم الملف، استخدم اسمه الفعلي في أمر النسخ.

## 3. تشغيل Laravel API لأول مرة

```bash
cd ~/projects/MADRASA/backend
composer install
cp .env.example .env
php artisan key:generate
mkdir -p database
 touch database/database.sqlite
```

افتح ملف الإعداد:

```bash
nano .env
```

تأكد من القيم التالية:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
DB_FOREIGN_KEYS=true
DEMO_ACCOUNT_PASSWORD=MadrasaDemo!2026
```

احفظ في nano بالضغط على `Ctrl+O` ثم Enter ثم `Ctrl+X`.

أنشئ الجداول والبيانات التجريبية:

```bash
php artisan config:clear
php artisan migrate --seed
php artisan test
php artisan serve --host 0.0.0.0 --port 8000
```

اترك نافذة Termux هذه تعمل. اختبر API من المتصفح:
`http://127.0.0.1:8000/api/health`

### حسابات الاختبار المحلية

| الدور | البريد | كلمة المرور الافتراضية |
|---|---|---|
| المدير | admin@madrasa.test | MadrasaDemo!2026 |
| المعلم | teacher@madrasa.test | MadrasaDemo!2026 |
| الطالب | student@madrasa.test | MadrasaDemo!2026 |

تُنشأ حسابات العرض فقط عندما تكون `APP_ENV=local` أو `testing`. لا تستخدم هذه الحسابات في الإنتاج. غيّر `DEMO_ACCOUNT_PASSWORD` في `.env` قبل إنشاء قاعدة جديدة أو إعادة ضبطها.

## 4. تشغيل الواجهة الأمامية

أنشئ إعداد عنوان API للواجهة (العنوان الافتراضي مناسب عند فتح الموقع على الهاتف نفسه):

```bash
cd ~/projects/MADRASA/frontend
cp .env.example .env.local
```

إذا كان Laravel يعمل على جهاز آخر، عدّل `VITE_API_BASE_URL` في `.env.local` إلى عنوان ذلك الجهاز مع `/api/v1`، وتأكد من إضافة أصل الواجهة إلى `FRONTEND_URL` في backend/.env.


افتح جلسة جديدة في Termux (اسحب من اليسار واختر New Session) ثم:

```bash
cd ~/projects/MADRASA/frontend
npm install
npm run dev -- --host 0.0.0.0
```

افتح:
`http://127.0.0.1:5173`

أبقِ جلستي Termux مفتوحتين: واحدة لـ Laravel على المنفذ 8000 وأخرى لـ Vite على 5173. لإيقاف أي خادم ارجع إلى جلسته واضغط `Ctrl+C`.

## 5. التشغيل في المرات القادمة

الجلسة الأولى:

```bash
cd ~/projects/MADRASA/backend && php artisan serve --host 0.0.0.0 --port 8000
```

الجلسة الثانية:

```bash
cd ~/projects/MADRASA/frontend && npm run dev -- --host 0.0.0.0
```

## 6. فحص تسجيل الدخول عبر API

من جلسة ثالثة يمكن تجربة تسجيل الدخول:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"login":"admin@madrasa.test","password":"MadrasaDemo!2026","device_name":"termux-test"}'
```
استبدل البريد بحساب المعلم أو الطالب لاختبار بقية الأدوار. سيعيد الخادم رمز Sanctum. لا تشارك الرمز مع أحد.

## 7. إعادة إنشاء قاعدة بيانات العرض من الصفر

**هذا الأمر يحذف قاعدة البيانات المحلية الحالية وبياناتها نهائياً.** لا تنفذه إذا أضفت بيانات تريد الاحتفاظ بها.

```bash
cd ~/projects/MADRASA/backend
rm -f database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh --seed
```

## 8. ملاحظات وحلول سريعة

- `composer: command not found`: نفّذ `pkg install composer -y`.
- `Class PDO not found` أو SQLite driver missing: تحقق من وحدات PHP؛ يلزم `pdo_sqlite`.
- `npm: command not found`: نفّذ `pkg install nodejs-lts -y`.
- المنفذ مستخدم: أوقف الخادم السابق بـ `Ctrl+C` أو غيّر المنفذ.
- خطأ 419/اتصال: تحقق من `APP_URL` و`FRONTEND_URL` وCORS. الواجهة تستخدم API لتسجيل الدخول والتحقق من الجلسة ولوحتي المعلم والطالب الأوليتين. تأكد أن `VITE_API_BASE_URL` يشير إلى الخادم الصحيح.
- لا ترفع `.env` أو `vendor/` أو `node_modules/` إلى GitHub.

## 9. التحديث من الإصدار 0.12.0 مع الاحتفاظ بقاعدة البيانات

إذا كان المشروع السابق موجوداً على الهاتف، لا تحذف قاعدة البيانات ولا تنفذ `migrate:fresh`.

1. احتفظ بنسخة احتياطية من `backend/database/database.sqlite` وملف `backend/.env`.
2. فك الإصدار الجديد في مجلد منفصل، ثم انسخ ملف `.env` القديم إلى `backend/.env` في الإصدار الجديد.
3. انسخ قاعدة البيانات القديمة إلى `backend/database/database.sqlite` في الإصدار الجديد (إذا كنت تستخدم SQLite).
4. نفّذ:

```bash
cd ~/projects/MADRASA/backend
composer install
php artisan migrate --force
php artisan optimize:clear
```

5. ثبّت حزم الواجهة ثم شغّلها:

```bash
cd ~/projects/MADRASA/frontend
npm install
npm run dev -- --host 0.0.0.0
```

لا تشغّل `migrate:fresh` عند التحديث لأنه يمسح الجداول والبيانات.

## 10. ما الذي تغير في 0.13.0؟

- ربط إدارة الطلاب والمعلمين في لوحة المدير بواجهات Laravel API.
- جلب سجلات الطلاب والمعلمين من قاعدة بيانات المدرسة عند دخول المدير.
- إنشاء حساب طالب/معلم من لوحة المدير، مع بريد إلكتروني وكلمة مرور إلزامية للحساب الجديد.
- تعديل بيانات الطالب والمعلم مع إبقاء كلمة المرور دون تغيير.
- أرشفة الحساب من خلال API مع الاحتفاظ بالسجل الأكاديمي/الوظيفي.
- تحديث القوائم بعد كل عملية، وإظهار أخطاء الخادم للمستخدم.
- الوحدات الأخرى (الصفوف والشعب والمواد والدروس والواجبات والاختبارات والحضور والإعدادات) لا تزال غير مربوطة بالكامل وتظهر بيانات محلية تجريبية.

## 11. حدود النسخة الحالية

- تسجيل دخول موحد متصل بـ `POST /api/v1/auth/login` باستخدام Laravel Sanctum.
- حفظ رمز الجلسة محلياً والتحقق منه عند إعادة فتح التطبيق عبر `GET /api/v1/me`.
- تسجيل خروج يبطل الرمز عبر `POST /api/v1/auth/logout`.
- توجيه الحساب تلقائياً حسب الدور: مدير المدرسة، المعلم، الطالب.
- لوحة المعلم تقرأ الإسنادات وأعداد الواجبات والتسليمات من Laravel API.
- لوحة الطالب تقرأ التقدم والواجبات والاختبارات من Laravel API.
- إظهار تنبيه واضح في لوحة المدير بأن وحدات الإدارة القديمة لا تزال محلية.

## 10. حدود النسخة الحالية

هذا إصدار تطوير مرحلي. تسجيل الدخول وتوجيه الأدوار ولوحتا المعلم والطالب الأوليتان متصلون بالخادم، كما أصبحت إدارة الطلاب والمعلمين في بوابة المدير متصلة بعمليات CRUD في قاعدة البيانات. ما زالت بقية وحدات المدير محلية تجريبية ولم تُربط بعد بقاعدة البيانات. واجهة الطالب تعرض ملخصاً وواجبات واختبارات، ولم يكتمل بعد مشغل الدروس وتحديث التقدم وتسليم الواجبات من الواجهة. كما لم تكتمل بعد اختبارات أمن وتكامل شاملة، وخدمات الفيديو والفصول الافتراضية. لا تستخدمه للإنتاج أو لبيانات طلاب حقيقية قبل إكمال هذه الأعمال واجتياز اختبارات القبول.
