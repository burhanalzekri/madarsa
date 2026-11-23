#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail
cd "$(dirname "$0")/../backend"
echo '== PHP syntax =='
find app bootstrap config database routes tests -name '*.php' -print0 | xargs -0 -n1 php -l
php -l artisan
php -l public/index.php
echo '== Composer validation =='
composer validate --no-check-publish
echo '== Runtime checks =='
php artisan --version
php artisan route:list --path=api
php artisan test
