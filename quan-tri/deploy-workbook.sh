#!/usr/bin/env bash
set -euo pipefail
umask 077
revision=${1:?Pass a full GitHub commit SHA}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]] || exit 1
root=/home/twjmlulv
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
exec 9>"$root/victor-admin-deploy.lock"
flock -n 9 || exit 1
release=$(mktemp -d "$root/victor-workbook-release.XXXXXX")
curl -fLsS --max-time 60 "https://codeload.github.com/biengiohd-create/thevictor/zip/$revision" -o "$release/source.zip"
unzip -oq "$release/source.zip" -d "$release"
source_dir="$release/thevictor-$revision"
ln -s "$root/public_html/api" "$source_dir/api"
for file in "$source_dir"/quan-tri/{workbook-lib.php,workbook-api.php,spreadsheet-view.php,sheet.php,team-ui.js,test-workbook.php}; do
 if [[ "$file" = *.php ]]; then php -l "$file"; fi
done
php "$source_dir/quan-tri/test-workbook.php"
cd "$source_dir/quan-tri/workbook-src"
npm ci --ignore-scripts --no-audit --no-fund --cache "$release/npm-cache"
node --max-old-space-size=2048 node_modules/vite/bin/vite.js build --configLoader runner
node notices.cjs
backup=$(mktemp -d "$root/victor-workbook-backup.XXXXXX")
cp -a "$root/public_html/quan-tri" "$backup/quan-tri"
VICTOR_DEPLOY_BACKUP="$backup" php -r '$s=new SQLite3("/home/twjmlulv/thevictor-private/orders.sqlite");if(!$s->backup(new SQLite3(getenv("VICTOR_DEPLOY_BACKUP")."/orders.sqlite")))exit(1);'
assets="$root/public_html/quan-tri/workbook-assets-v2"
mkdir -p "$assets"
# New files become readable by the web server, while build and source stay private.
cp -a dist/assets/. "$assets/"
find "$assets" -type d -exec chmod 0755 {} +
find "$assets" -type f -exec chmod 0644 {} +
for file in workbook-lib.php workbook-api.php spreadsheet-view.php team-ui.js sheet.php; do
 install -m 0644 "$source_dir/quan-tri/$file" "$root/public_html/quan-tri/$file.new"
 mv "$root/public_html/quan-tri/$file.new" "$root/public_html/quan-tri/$file"
done
printf 'WORKBOOK DEPLOYED %s\nBACKUP %s\n' "$revision" "$backup"
