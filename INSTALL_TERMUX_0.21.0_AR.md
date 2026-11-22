# تثبيت MADRASA 0.21.0 على Termux

## 1) نسخ احتياطي ثم فك الإصدار

```bash
termux-setup-storage
cd ~/projects || exit 1
BACKUP="MADRASA_backup_$(date +%Y%m%d_%H%M%S)"
mv MADRASA "$BACKUP"
unzip -q /sdcard/Download/madrasa-platform-0.21.0-teacher-workspace.zip -d ~/projects

# استعادة إعدادات الخادم وقاعدة SQLite والملفات المرفوعة
[ -f "$BACKUP/backend/.env" ] && cp "$BACKUP/backend/.env" MADRASA/backend/.env
[ -f "$BACKUP/backend/database/database.sqlite" ] && cp "$BACKUP/backend/database/database.sqlite" MADRASA/backend/database/database.sqlite
[ -d "$BACKUP/backend/storage/app/public" ] && cp -a "$BACKUP/backend/storage/app/public/." MADRASA/backend/storage/app/public/
```

لا تحذف مجلد النسخة الاحتياطية قبل اختبار المدير والمعلم والطالب وولي الأمر.

## 2) تشغيل Laravel

```bash
cd ~/projects/MADRASA/backend
composer install
php artisan optimize:clear
php artisan serve --host 0.0.0.0 --port 8000
```

لا توجد ترحيلات جديدة في 0.21.0. إذا كنت تحدث إصداراً أقدم وتوجد ترحيلات سابقة غير مطبقة، راجع سجل الإصدار السابق قبل تنفيذ `php artisan migrate`. لا تستخدم `migrate:fresh`.

## 3) تشغيل واجهة React

```bash
cd ~/projects/MADRASA/frontend
npm install
npm run build
npm run dev -- --host 0.0.0.0 --port 5173
```

افتح `http://127.0.0.1:5173`.

## 4) ما تختبره في بوابة المعلم

1. سجّل الدخول بحساب معلم لديه مواد مسندة.
2. تأكد من ظهور الشعب والمواد الصحيحة.
3. أنشئ وحدة ودرساً في مادة مسندة، ثم جرّب النشر وإلغاء النشر.
4. أنشئ واجباً وحدد موعده ودرجته، ثم جرّب التعديل والنشر.
5. أرسل واجباً بحساب طالب في الشعبة، ثم افتح التسليم وصححه من حساب المعلم.
6. جرّب تقرير تقدم الطلاب لشعبة المعلم.
7. تأكد أن المعلم لا يستطيع تعديل مادة أو درس خارج إسناده عبر API.
8. تأكد أن حذف واجب له تسليمات أو درس له سجلات تقدم يُرفض برسالة واضحة.

## 5) التحقق قبل الإنتاج

```bash
cd ~/projects/MADRASA/backend
php artisan test

cd ~/projects/MADRASA/frontend
npm run build
```

هذه النسخة تطويرية إلى حين نجاح الاختبارات الفعلية على بيئة المشروع وقاعدة بياناته.
