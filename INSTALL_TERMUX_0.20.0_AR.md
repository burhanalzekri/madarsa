# تحديث MADRASA إلى 0.20.0 على Termux

## 1. النسخ الاحتياطي
```bash
cd ~/projects || exit 1
BACKUP="MADRASA_backup_$(date +%Y%m%d_%H%M%S)"
cp -a MADRASA "$BACKUP"
```

## 2. فك الإصدار الجديد
ضع `madrasa-platform-0.20.0-student-learning.zip` في Download ثم:
```bash
cd ~/projects || exit 1
unzip -q /sdcard/Download/madrasa-platform-0.20.0-student-learning.zip -d ~/projects
```
إذا ظهر طلب استبدال، لا تستبدل ملفات `.env` أو قاعدة البيانات المحلية؛ استعدهما من النسخة الاحتياطية بعد الفك:
```bash
cp "$BACKUP/backend/.env" MADRASA/backend/.env 2>/dev/null || true
cp "$BACKUP/backend/database/database.sqlite" MADRASA/backend/database/database.sqlite 2>/dev/null || true
```

## 3. تحديث وتشغيل Laravel
```bash
cd ~/projects/MADRASA/backend
composer install
php artisan migrate
php artisan optimize:clear
php artisan test
php artisan serve --host 0.0.0.0 --port 8000
```
لا تستخدم `migrate:fresh` ولا تشغّل Seeder على بيانات مدرسة حقيقية.

## 4. بناء الواجهة وتشغيلها
في جلسة Termux ثانية:
```bash
cd ~/projects/MADRASA/frontend
npm install
npm run build
npm run dev -- --host 0.0.0.0 --port 5173
```
افتح `http://127.0.0.1:5173`.

## 5. اختبار تسليم الواجب
- ادخل بحساب طالب مرتبط بشعبة لديها واجب منشور.
- اكتب إجابة ثم اضغط «إرسال الواجب».
- تأكد من ظهور حالة «تم التسليم» بعد تحديث البيانات.
- قبل اعتماد المعلم للدرجة، يمكن تحديث الإجابة ما دام الموعد مفتوحاً.
- بعد اعتماد الدرجة، يجب أن يرفض الخادم أي محاولة تعديل.

احتفظ بالنسخة الاحتياطية حتى تتأكد من دخول المدير والمعلم والطالب وولي الأمر وسلامة البيانات.
