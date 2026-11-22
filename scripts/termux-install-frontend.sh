#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail
cd "$(dirname "$0")/../frontend"
command -v node >/dev/null 2>&1 || { echo "Node.js غير مثبت. نفّذ: pkg install nodejs-lts -y"; exit 1; }
command -v npm >/dev/null 2>&1 || { echo "npm غير متاح مع Node.js"; exit 1; }
npm install
printf '\nاكتمل تثبيت واجهة MADRASA. شغّلها بالأمر: npm run dev -- --host 0.0.0.0\n'
