#!/usr/bin/env bash
set -euo pipefail
umask 077
revision=${1:?Usage: deploy-admin-sheet.sh COMMIT_SHA}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]] || { echo 'Invalid commit SHA'; exit 1; }
root=/home/twjmlulv
exec 9>"$root/victor-admin-deploy.lock"
flock -n 9 || { echo 'Another deployment is running'; exit 1; }
release=$(mktemp -d "$root/victor-admin-release.XXXXXX")
curl -fLsS --max-time 60 "https://codeload.github.com/biengiohd-create/thevictor/zip/$revision" -o "$release/source.zip"
unzip -oq "$release/source.zip" -d "$release"
source_dir="$release/thevictor-$revision"
ln -s "$root/public_html/api" "$source_dir/api"
for file in "$source_dir"/quan-tri/*.php; do php -l "$file"; done
php "$source_dir/quan-tri/test-sheet.php"
backup=$(mktemp -d "$root/victor-admin-backup.XXXXXX")
cp -a "$root/public_html/quan-tri" "$backup/quan-tri"
VICTOR_DEPLOY_BACKUP="$backup" php -r '$s=new SQLite3("/home/twjmlulv/thevictor-private/orders.sqlite");if(!$s->backup(new SQLite3(getenv("VICTOR_DEPLOY_BACKUP")."/orders.sqlite")))exit(1);'
# Only deploy the reviewed admin files. Storefront and private data stay separate.
for file in team.php sheet-api.php sheet.php sheet-core.js sheet.js sheet.css lib.php api.php index.php; do
 install -m 0644 "$source_dir/quan-tri/$file" "$root/public_html/quan-tri/$file.new"
 mv "$root/public_html/quan-tri/$file.new" "$root/public_html/quan-tri/$file"
done
printf 'Deployed commit: %s\nBackup: %s\n' "$revision" "$backup"
