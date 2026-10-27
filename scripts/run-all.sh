#!/usr/bin/env bash
# Rebuild the whole Drupal 11 site from the copy of the original database in schema `d6`.
# Usage (from the project root): scripts/run-all.sh
set -euo pipefail
cd "$(dirname "$0")/.."
ddev drush php:script scripts/10-site-shell.php
ddev drush cr
./scripts/15-copy-files.sh
ddev drush php:script scripts/20-import.php
ddev drush cr
ddev drush cex -y
