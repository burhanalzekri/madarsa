# متطلبات تفعيل الخدمات الخارجية

## Cloudflare Stream
يلزم حساب Cloudflare Stream ومعرّف الحساب ومفتاح API محفوظان في متغيرات بيئة الخادم فقط. لا تضع المفاتيح في React أو ملفات ZIP العامة. يجب أن ينشئ Laravel طلب الرفع ويعيد رابط تشغيل موقّعاً بعد التحقق من صلاحية الطالب.

## BigBlueButton
يلزم خادم BigBlueButton صالح، و`BBB_URL` و`BBB_SECRET` على الخادم. ينشئ Laravel روابط join عبر API مع تحقق من تسجيل الطالب في الشعبة والحصة. لا يجوز كشف السر أو إنشاء رابط دخول من الواجهة مباشرة.

## PostgreSQL
اضبط بيانات الاتصال في `backend/.env` ثم نفّذ migrations وseeders بعد تثبيت Composer. لا تستخدم SQLite أو localStorage كقاعدة إنتاجية.
