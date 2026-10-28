#!/usr/bin/env bash
# Start over: a fresh Drupal 11 standard install, and the Drupal 6 source reloaded into the schema `d6` from the
# sanitized snapshot (hobobiker-assets v1.2; DDEV 1.25.5 imports .zst directly). Then run scripts/run-all.sh.
# Destroys this project's database (never the original's). The files directory is kept: the copy step is
# incremental.
# Usage (from the project root): scripts/00-reset.sh [path/to/db.sql.zst]
set -euo pipefail
cd "$(dirname "$0")/.."
snapshot="${1:-$HOME/workspace/hobo/hobobiker-copy/.tarballs/db.sql.zst}"
[ -f "$snapshot" ] || { echo "no snapshot at $snapshot" >&2; exit 1; }
# The installer prints the generated admin password; it is not shown (log in with: ddev drush uli).
ddev drush site:install standard -y --site-name="Hobobiker.com" 2>&1 | grep -v -i "password" || true
ddev import-db --database=d6 --file="$snapshot"
echo "d6: $(ddev mysql -N -e 'SELECT COUNT(*) FROM d6.node') nodes, $(ddev mysql -N -e 'SELECT COUNT(*) FROM d6.comments') comments"
