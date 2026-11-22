# ترقية MADRASA إلى 0.26.0 على Termux / Ubuntu proot

> هذه ترقية فوق 0.25.0. احتفظ بقاعدة البيانات وملف `.env` الحاليين؛ لا تستبدلهما بملفات المثال ولا تستخدم `migrate:fresh`.

## 1) نسخة احتياطية

من داخل بيئة Ubuntu في proot، ومن مجلد المشروع الحالي:

```bash
cd ~/MADRASA/backend
cp database/database.sqlite "database/database.sqlite.backup-$(date +%Y%m%d-%H%M%S)" 2>/dev/null || true
cp .env .env.backup-0.26.0
```

إذا كان مسار المشروع مختلفاً، استخدم مسارك الفعلي بدلاً من `~/MADRASA`.

## 2) إعدادات التشغيل التي يجب الحفاظ عليها

- لا تعِد تفعيل `$middleware->statefulApi()` في `backend/bootstrap/app.php`؛ تسجيل الدخول هنا يعتمد على Bearer Token.
- احتفظ بقيم `APP_URL` و`SESSION_DOMAIN` و`SANCTUM_STATEFUL_DOMAINS` ومفتاح `APP_KEY` الحالية كما تم ضبطها في بيئة Ubuntu proot. لا تدوّر `APP_KEY` أثناء الترقية.

## 3) تطبيق ترحيل قاعدة البيانات

```bash
cd ~/MADRASA/backend
php artisan migrate:status
```

راجع الحالة أولاً. لأن جدول `educational_resources` أُنشئ يدوياً في جلسة سابقة، إذا ظهر ترحيل `2026_11_18_000002_create_educational_operations_tables` بحالة Pending مع أن الجدول موجود بالفعل، **توقف ولا تنفذ migrate أو migrate:fresh** وأرسل نتيجة `migrate:status` حتى نطابق سجل الترحيلات مع القاعدة دون فقد بيانات. إذا كان ذلك الترحيل مسجلاً كمكتمل، نفّذ:

```bash
php artisan migrate --force
```

الهجرة الجديدة تضيف `classroom_id` إلى `school_announcements` ولا تحذف الإعلانات السابقة؛ تبقى الإعلانات العامة ذات `classroom_id = null` عامة حسب الأدوار المستهدفة.

## 4) تثبيت وبناء الواجهة

```bash
cd ~/MADRASA/frontend
npm install
npm run build
```

## 5) فحوص الخادم

```bash
cd ~/MADRASA/backend
php artisan optimize:clear
php artisan route:list --path=api/v1/teacher/announcements
php artisan test
```

## 6) التشغيل

شغّل Laravel من بيئة Ubuntu proot على المنفذ 8000، وشغّل Vite من جلسة Termux/Ubuntu ثانية وفق إعدادات التشغيل السابقة. لا تشغّل `php -S` مباشرة من مجلد Termux إذا كان ذلك يعيد خطأ صلاحيات إنشاء lock.

## 7) اختبار مركز التواصل

1. سجّل الدخول بحساب معلم لديه إسناد لمادة في شعبة محددة.
2. افتح «التواصل» من اختصارات بوابة المعلم.
3. أنشئ إعلاناً موجهاً للطلاب أو أولياء الأمور في شعبة مسندة إليه.
4. تحقق من ظهور الإعلان للطالب المسجل في الشعبة.
5. تحقق من ظهوره لولي الأمر المرتبط بالطالب.
6. تحقق من عدم ظهوره لطالب أو ولي أمر من شعبة أخرى، ومن رفض محاولة المعلم استهداف شعبة غير مسندة إليه.

> لم تُنفذ هذه الخطوات على قاعدة بيانات المستخدم ضمن إعداد الحزمة؛ يجب إجراء التحقق الفعلي قبل اعتماد الإصدار للإنتاج.
