# Agent instructions: hobobiker-d11

The Drupal 11 rebuild of hobobiker.com (Part 3 of "Hobobiker Rides Again"). Drupal 11.4, PHP 8.4, MariaDB
11.8, Olivero. The site is built **entirely by scripts** in `scripts/`, from a copy of the original's
database; nothing is configured by hand. The plan and its decisions are in
`~/workspace/hobo/hobobiker-copy/PART3-PLAN.md`.

## What this environment is

- Local URL: https://hobobiker-d11.ddev.site; also https://hobobiker-d11--remotework--rfay.coder.ddev.com.
- Drush: `ddev drush ...` (Drupal 11 project).
- **Source data:** the schema `d6` in this project's database is a copy of the original's sanitized
  pristine snapshot (`~/workspace/hobo/hobobiker-copy/.tarballs/db.sql.zst`, hobobiker-assets v1.2). The
  scripts only read it. Reload it rather than change it. (The original's own database is also reachable from this
  project's web container, `mysql -h hobobiker-copy-db -udb -pdb db`, a common way to migrate; the copy keeps the
  source fixed while the pipeline is rehearsed. `ddev mysql -h ...` does not work for this.)
- **Files:** read-only source is the original's files directory,
  `~/workspace/hobo/hobobiker-copy/docroot/sites/default/files` (1.1 GB). Copy, never move or edit.
- The model this pipeline started from: `~/workspace/randyfay-d11/scripts/`.
- The tests are **not** here: they run from `hobobiker-copy` against this site:

  ```bash
  cd ~/workspace/hobo/hobobiker-copy && ddev exec -d /var/www/html/test/playwright \
    'TEST_BASE_URL=https://hobobiker-d11.ddev.site MIGRATION_TARGET=drupal11 npx playwright test --grep "semantic:|access:|asset:|baseline:"'
  ```

  Every project (`hobobiker-copy`, `hobobiker-d11`) must be running first.

## The pipeline

Rebuild everything (about 30 seconds): `scripts/run-all.sh`

| Step | What it does |
|---|---|
| `10-site-shell.php` | modules (incl. the custom `hobobiker`), 10 text formats (one per Drupal 6 format in use), image styles, 11 content types, 4 vocabularies, fields, comment type (comments stored, never shown), site settings |
| `15-copy-files.sh` | the original's files, same paths (incremental rsync, 1.1 GB; runs before the import) |
| `20-import.php` | users, terms, file records, nodes (current revision, CCK fields, terms), aliases, comments, with the original ids |
| `drush cex` | the configuration, to `config/sync` |

`web/modules/custom/hobobiker`: the `[hobophoto:]` and (later) `[tripinfo:]` text filters and the blog breadcrumb.
`web/themes/custom/hobo`: an Olivero subtheme with the Drupal 6 theme's node meta lines, pager and feed icon.

**Caching is off in this copy** (`web/sites/default/settings.local.php`): page, dynamic page and render caches use
the null backend and Twig recompiles changed templates, so a test never sees a stale page. The exported config
keeps `page_cache` and `dynamic_page_cache` enabled; a production copy leaves out settings.local.php.
Drupal's database layer reads `[brackets]` in a query string as a quoted identifier: pass a regex as a placeholder.

## What is NOT disposable

- **Production** (hobobiker.com, www.hobobiker.com, the host `ddevprod.thefays.us`): never connect to it or
  write to it. Never run `ddev pull`, `ddev push`, `ddev auth ssh`, or `rsync`/`scp`/`ssh` to a remote host.
  **Never request `hobobiker.com`** (old content hardcodes it; the suite treats it as the target).
- **Git:** `rfay` is the real GitHub repository (rfay/hobobiker-d11). Never `git push`. Do not commit
  unless the human asks.
- The sibling `hobobiker-copy` is the reference for every test: never modify it, its database or its files.
  Its frozen baseline (`test/playwright/baseline/`) is read-only.
- A tripwire hook (`.claude/hooks/deny-production.sh`, registered in the uncommitted
  `.claude/settings.local.json`, patterns in `.agent-protected-patterns`) blocks commands that match these
  rules. If it fires, stop and ask; do not work around it.
- **Never write a secret value or personal data** into a file, commit, log or message. Check by length,
  hash or count.

## Ground rules

- **Reproduce the site, don't repair it.** A page broken on the original stays broken the same way; an
  unpublished page still answers 403. Record inconsistencies in `hobobiker-copy/DISCOVERIES.md` ("Part 3").
- Deliberate changes are declared in `hobobiker-copy/test/playwright/expected-differences.json` with
  `"target": "drupal11"`, exact (`equals` or an anchored `regex`), each with a reason.
- **Every script reports a count** and is rerunnable: it removes what it imported before, then imports
  again with the original ids (nid, cid, tid, fid, uid), so `node/N` and aliases keep working.
- The configuration the scripts produce is exported to `config/sync` (`drush cex`) and kept in git.
- Drupal 6 source schema: current revision is `node.vid = node_revisions.vid`; `url_alias` has
  `src`/`dst`; uploads are listed in `files` (only 44 rows: most of the 1,793 files on disk are linked
  from bodies, not attached).
