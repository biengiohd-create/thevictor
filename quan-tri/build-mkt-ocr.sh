#!/bin/bash
set -euo pipefail
target=$1
build=$(mktemp -d)
trap 'rm -rf "$build"' EXIT
cd "$build"
npm init -y >/dev/null
npm install --ignore-scripts --no-audit --no-fund --save-exact tesseract.js@7.0.0 tesseract.js-core@7.0.0
mkdir -p "$target"
cp node_modules/tesseract.js/dist/tesseract.esm.min.js node_modules/tesseract.js/dist/worker.min.js "$target/"
cp node_modules/tesseract.js-core/tesseract-core*.js node_modules/tesseract.js-core/tesseract-core*.wasm "$target/"
for lang in eng vie; do curl --fail --silent --show-error --location "https://tessdata.projectnaptha.com/4.0.0_fast/$lang.traineddata.gz" -o "$target/$lang.traineddata.gz"; gzip -t "$target/$lang.traineddata.gz"; done
cp node_modules/tesseract.js/LICENSE "$target/LICENSE-tesseract.txt"
sha256sum "$target"/* > "$target/manifest.sha256"
