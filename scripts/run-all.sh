#!/usr/bin/env bash
# Build the whole site from the d6 source, in order. Each step is rerunnable and prints its counts.
# From scratch: scripts/00-reset.sh && scripts/run-all.sh
set -euo pipefail
cd "$(dirname "$0")/.."
for step in scripts/[1-9][0-9]-*.php; do
  echo "== $step"
  ddev drush php:script "$step"
done
