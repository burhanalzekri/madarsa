#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail
cd "$(dirname "$0")/../backend"
command -v php >/dev/null 2>&1 || { echo "PHP غير مثبت أو غير متاح."; exit 1; }
[ -f .env ] || { echo "ملف .env غير موجود. انسخ .env.example إلى .env واضبط قاعدة البيانات ثم نفّذ php artisan key:generate."; exit 1; }
[ -f vendor/autoload.php ] || { echo "حزم Composer غير مثبتة. نفّذ composer install أولاً."; exit 1; }
php artisan serve --host 0.0.0.0 --port 8000
