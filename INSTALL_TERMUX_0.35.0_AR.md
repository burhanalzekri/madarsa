# تشغيل MADRASA 0.35.0 على Termux

1. فك ضغط المشروع وانتقل إلى مجلد `MADRASA`.
2. ثبّت متطلبات PHP وComposer وNode.js كما هو موضح في `INSTALL_TERMUX_0.34.0_AR.md`.
3. من مجلد `frontend` نفّذ:

```bash
npm install
npm run build
```

4. شغّل Laravel من مجلد `backend` بالطريقة المعتادة (`php artisan serve --host 0.0.0.0`).
5. شغّل واجهة Vite أثناء التطوير باستخدام `npm run dev -- --host 0.0.0.0`.

هذا الإصدار إصلاح واجهة فقط ولا يتطلب ترحيلات قاعدة بيانات جديدة.
