#!/usr/bin/env bash
set -euo pipefail
umask 077
revision=${1:?Pass GitHub commit SHA}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]] || exit 1
root=/home/twjmlulv
exec 9>"$root/victor-admin-deploy.lock"
flock -n 9 || exit 1
release=$(mktemp -d "$root/victor-header-release.XXXXXX")
curl -fLsS --max-time 60 "https://codeload.github.com/biengiohd-create/thevictor/zip/$revision" -o "$release/source.zip"
unzip -oq "$release/source.zip" -d "$release"
src="$release/thevictor-$revision/quan-tri"
ln -s "$root/public_html/api" "$release/thevictor-$revision/api"
for file in team.php api.php sheet-api.php index.php test-team.php; do php -l "$src/$file"; done
php "$src/test-team.php"
php "$src/test-inbox.php"
backup=$(mktemp -d "$root/victor-header-backup.XXXXXX")
for file in team.php api.php sheet-api.php index.php admin.js admin.css analytics-admin.js; do cp -a "$root/public_html/quan-tri/$file" "$backup/$file"; done
for file in team.php api.php sheet-api.php admin.css admin.js analytics-admin.js index.php; do
 install -m 0644 "$src/$file" "$root/public_html/quan-tri/$file.new"
 mv "$root/public_html/quan-tri/$file.new" "$root/public_html/quan-tri/$file"
done
printf 'HEADER DEPLOYED %s\nBACKUP %s\n' "$revision" "$backup"
