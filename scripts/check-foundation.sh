#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo '[1/3] PHP syntax checks'
find backend -name '*.php' -print0 | xargs -0 -n1 php -l
echo '[2/3] Composer manifest validation'
php -r '$d=json_decode(file_get_contents("backend/composer.json"), true); if (!$d || json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, "Invalid composer.json\n"); exit(1); } echo "composer.json OK\n";'
echo '[3/3] Frontend dependency/build check'
cd frontend
npm run build
echo 'Foundation checks passed.'
