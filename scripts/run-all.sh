#!/usr/bin/env bash
# Rebuild the whole Drupal 11 site from the copy of the original database in schema `d6`.
# Usage (from the project root): scripts/run-all.sh
set -euo pipefail
cd "$(dirname "$0")/.."
# New or changed module code (plugins, routes, services) must be known before the shell uses it.
ddev drush cr
ddev drush php:script scripts/10-site-shell.php
ddev drush cr
./scripts/15-copy-files.sh
ddev drush php:script scripts/20-import.php
ddev drush php:script scripts/30-menus.php
ddev drush php:script scripts/40-theme.php
ddev drush php:script scripts/50-views.php
ddev drush php:script scripts/60-front-page.php
ddev drush cr
ddev drush cex -y
