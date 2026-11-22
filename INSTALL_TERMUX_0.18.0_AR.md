# تثبيت MADRASA 0.18.0 على Termux

## 1) تجهيز الملف واستبدال نسخة المشروع بأمان

إذا لم تكن قد منحت Termux صلاحية التخزين من قبل، نفّذ:

```bash
termux-setup-storage
```

تأكد أن ملف ZIP موجود في مجلد Download، ثم نفّذ (عدّل مسار الملف إذا حفظته في مكان آخر):

```bash
cd ~/projects || exit 1
BACKUP="MADRASA_backup_$(date +%Y%m%d_%H%M%S)"
mv MADRASA "$BACKUP"
unzip -q /sdcard/Download/madrasa-platform-0.18.0-parent-portal.zip -d ~/projects

# استعادة إعدادات الاتصال والبيانات المحلية والملفات المرفوعة
[ -f "$BACKUP/backend/.env" ] && cp "$BACKUP/backend/.env" MADRASA/backend/.env
[ -f "$BACKUP/backend/database/database.sqlite" ] && cp "$BACKUP/backend/database/database.sqlite" MADRASA/backend/database/database.sqlite
[ -d "$BACKUP/backend/storage/app/public" ] && cp -a "$BACKUP/backend/storage/app/public/." MADRASA/backend/storage/app/public/
```

لا تحذف مجلد `MADRASA_backup_*` حتى تتأكد من تشغيل النسخة الجديدة وسلامة بياناتك. إذا كانت قاعدة البيانات خارجية مثل MySQL/PostgreSQL، فإن ملف `.env` المستعاد يحافظ على إعدادات الاتصال ولا تنسخ قاعدة البيانات كملف.

## 2) تحديث وتشغيل الخادم

في جلسة Termux الأولى:

```bash
cd ~/projects/MADRASA/backend
composer install
php artisan migrate
php artisan storage:link
php artisan optimize:clear
php artisan serve --host 0.0.0.0 --port 8000
```

## 3) تشغيل الواجهة

في جلسة Termux ثانية:

```bash
cd ~/projects/MADRASA/frontend
npm install
npm run build
npm run dev -- --host 0.0.0.0 --port 5173
```

افتح `http://127.0.0.1:5173`.

## 4) حساب ولي الأمر التجريبي

إذا كانت هذه بيئة تطوير جديدة، وتريد بيانات العرض التجريبي، نفّذ مرة واحدة من مجلد backend:

```bash
php artisan db:seed --class=DatabaseSeeder
```

- البريد: `parent@madrasa.test`
- كلمة المرور: `MadrasaDemo!2026`
- الطالب التجريبي المرتبط: `S-DEMO-001`

**تحذير مهم:** تشغيل Seeder في بيئة local/testing يعيد تعيين كلمات مرور حسابات العرض التجريبي. لا تشغّله على قاعدة بيانات إنتاج أو مدرسة حقيقية. إذا كنت تحدث قاعدة بيانات موجودة من 0.17.0 فلا تحتاج إلى Seeder لتطبيق الترحيل الجديد؛ `php artisan migrate` يكفي، ويمكنك إنشاء حساب ولي الأمر الحقيقي من لوحة المدير.

## 5) ربط ولي أمر حقيقي

من لوحة المدير افتح «أولياء الأمور» وأدخل اسم ولي الأمر وبريده والرقم المدرسي للطالب. عند إنشاء حساب جديد أدخل كلمة مرور لا تقل عن 8 أحرف. إذا كان البريد مرتبطاً بحساب ولي أمر قائم، اترك كلمة المرور فارغة وسيُربط الطالب بالحساب نفسه. يمكن ربط أكثر من طالب بالحساب الواحد.

## 6) سلامة البيانات

- الترحيل يضيف جدول الربط فقط ولا يحذف بيانات قديمة.
- لا تستخدم `migrate:fresh` ولا تحذف قاعدة البيانات.
- لا تحذف مجلد النسخة الاحتياطية قبل اختبار الدخول كمدير ومعلم وطالب وولي أمر.
- التقارير تعرض الحضور والدرجات المسجلة فعلياً؛ لا تنشئ قيماً افتراضية.
