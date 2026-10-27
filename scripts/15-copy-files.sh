#!/usr/bin/env bash
# Copies the public files of the ORIGINAL (read-only source: the sibling hobobiker-copy project) into this site,
# at the same paths, so every /sites/default/files/... URL stays the same. Local copy only; nothing is written to
# the original. Incremental: a rerun copies only what changed. Runs before the import, which checks each file
# record against these files (the import never deletes file bytes).
set -euo pipefail
src="${1:-$HOME/workspace/hobo/hobobiker-copy/docroot/sites/default/files}"
dst="$(cd "$(dirname "$0")/.." && pwd)/web/sites/default/files"
[ -d "$src" ] || { echo "no source files at $src" >&2; exit 1; }
# Skip Drupal 6's aggregated css/js and tmp. Its imagecache derivatives are kept: old URLs still point at them.
# Drupal 11's own generated directories (styles, css, js, php) are never touched.
excl=(--exclude=/css --exclude=/js --exclude=/tmp --exclude=/styles --exclude=/php)
rsync -rtL "${excl[@]}" "$src"/ "$dst"/
# The original also served the files directory at /files (a symlink in its docroot); old content links there.
ln -sfn sites/default/files "$(dirname "$dst")/../../files"
echo "copied: $(find "$dst" -type f -not -path "$dst/styles/*" -not -path "$dst/css/*" -not -path "$dst/js/*" -not -path "$dst/php/*" | wc -l) files in $dst"
