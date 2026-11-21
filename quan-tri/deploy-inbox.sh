#!/usr/bin/env bash
set -euo pipefail
umask 077
revision=${1:?Pass a full GitHub commit SHA}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]] || exit 1
root=/home/twjmlulv
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
exec 9>"$root/victor-admin-deploy.lock"
flock -n 9 || exit 1
release=$(mktemp -d "$root/victor-inbox-release.XXXXXX")
curl -fLsS --max-time 60 "https://codeload.github.com/biengiohd-create/thevictor/zip/$revision" -o "$release/source.zip"
unzip -oq "$release/source.zip" -d "$release"
source_dir="$release/thevictor-$revision"
ln -s "$root/public_html/api" "$source_dir/api"
for file in "$source_dir"/quan-tri/*.php; do php -l "$file"; done
php -d display_errors=1 "$source_dir/quan-tri/test-workbook.php"
php -d display_errors=1 "$source_dir/quan-tri/test-sheet.php"
php -d display_errors=1 "$source_dir/quan-tri/test-inbox.php"
php -d display_errors=1 "$source_dir/quan-tri/test-team.php"
php -d display_errors=1 "$source_dir/quan-tri/test-people.php"
cp "$root/public_html/api/orders.php" "$release/orders.php"
php "$source_dir/quan-tri/patch-orders-default.php" "$release/orders.php"
php -l "$release/orders.php"
cd "$source_dir/quan-tri/workbook-src"
npm ci --ignore-scripts --no-audit --no-fund --cache "$release/npm-cache"
node --test touch-motion.test.js
node --test "$source_dir/quan-tri/notifications-state.test.js"
node --max-old-space-size=2048 node_modules/vite/bin/vite.js build --configLoader runner
node notices.cjs
backup=$(mktemp -d "$root/victor-inbox-backup.XXXXXX")
cp -a "$root/public_html/quan-tri" "$backup/quan-tri"
cp -a "$root/public_html/api/orders.php" "$backup/orders.php"
VICTOR_DEPLOY_BACKUP="$backup" php -r '$s=new SQLite3("/home/twjmlulv/thevictor-private/orders.sqlite");if(!$s->backup(new SQLite3(getenv("VICTOR_DEPLOY_BACKUP")."/orders.sqlite")))exit(1);'
VICTOR_DEPLOY_BACKUP="$backup" php -r '$s=new SQLite3("/home/twjmlulv/thevictor-private/admin-auth.sqlite");if(!$s->backup(new SQLite3(getenv("VICTOR_DEPLOY_BACKUP")."/admin-auth.sqlite")))exit(1);'
assets="$root/public_html/quan-tri/workbook-assets-v2"
mkdir -p "$assets"
cp -a dist/assets/. "$assets/"
find "$assets" -type d -exec chmod 0755 {} +
find "$assets" -type f -exec chmod 0644 {} +
for file in payroll-runs.php notifications-state.js password-lib.php password.php notifications-lib.php notifications.php notifications.js notifications.css payroll-lib.php activity.js people-lib.php people-api.php people.php people.js people.css identity-view.php team.php lib.php workbook-lib.php inbox-flow.php api.php sheet-api.php workbook-api.php workbook-export.php team-ui.js admin.css admin.js spreadsheet-view.php index.php; do
 install -m 0644 "$source_dir/quan-tri/$file" "$root/public_html/quan-tri/$file.new"
 mv "$root/public_html/quan-tri/$file.new" "$root/public_html/quan-tri/$file"
done
install -m 0644 "$release/orders.php" "$root/public_html/api/orders.php.new"
mv "$root/public_html/api/orders.php.new" "$root/public_html/api/orders.php"
install -m 0700 "$source_dir/quan-tri/payroll-cron.php" "$root/thevictor-private/payroll-cron.php"
php_bin=$(type -P php)
flock_bin=$(type -P flock)
cron_line="15 * * * * $flock_bin -n $root/thevictor-private/payroll-cron.lock $php_bin $root/thevictor-private/payroll-cron.php >> $root/thevictor-private/payroll-cron.log 2>&1 # VICTOR_PAYROLL_JOB"
{ crontab -l 2>/dev/null | sed '/# VICTOR_PAYROLL_JOB$/d' || true; printf '%s\n' "$cron_line"; } | crontab -
printf 'PAYROLL CRON INSTALLED\n'
printf 'INBOX DEPLOYED %s\nBACKUP %s\n' "$revision" "$backup"



