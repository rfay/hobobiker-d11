# Agent instructions: hobobiker-d11

The Drupal 11 rebuild of hobobiker.com, started live in Part 3 of "Hobobiker Rides Again". Drupal 11.4, PHP 8.4,
MariaDB 11.8, the standard profile. **This is the start state: an empty site, no pipeline yet.** The site is to be
built **entirely by rerunnable steps** in `scripts/` (the Migrate API or plain scripts, whichever the plan
chooses), from a copy of the original's database; nothing configured by hand. The owner's decisions are given in
the session's opening prompt.

## What this environment is

- Local URL: https://hobobiker-d11.ddev.site; also https://hobobiker-d11--remotework--rfay.coder.ddev.com.
- Drush: `ddev drush ...`.
- **Source data:** the schema `d6` in this project's database is a copy of the original's sanitized snapshot
  (`~/workspace/hobo/hobobiker-copy/.tarballs/db.sql.zst`). Read it from PHP with a second connection
  (`Database::addConnectionInfo('d6', ...)`), or with `ddev mysql d6`. Never change it; `scripts/00-reset.sh`
  starts over (a fresh install, `d6` reloaded). The original's own database is also reachable from this project's
  web container (`mysql -h hobobiker-copy-db -udb -pdb db`).
- **The original, running:** https://hobobiker-copy.ddev.site. Its code is in
  `~/workspace/hobo/hobobiker-copy/docroot` (Drupal 6; theme `sites/all/themes/hobo_golden` on `golden_hour`;
  custom modules `tripinfo` and `hobobiker_filter`).
- **Files:** the original's are in `~/workspace/hobo/hobobiker-copy/docroot/sites/default/files` (1.1 GB). Copy,
  never move or edit. `web/sites/default/files` here may already hold a copy from a rehearsal.
- **The tests** run from `hobobiker-copy` against this site (every project running first):

  ```bash
  cd ~/workspace/hobo/hobobiker-copy && ddev exec -d /var/www/html/test/playwright \
    'TEST_BASE_URL=https://hobobiker-d11.ddev.site MIGRATION_TARGET=drupal11 npx playwright test --grep "semantic:|access:|baseline:"'
  # Stored content (bodies, comments, terms): export the target, then run the stored tier.
  cd ~/workspace/hobo/hobobiker-copy/test/playwright && node kit/scripts/stored-exporters/drupal11.mjs \
    --project-dir ~/workspace/hobo/hobobiker-d11 --out tmp/stored-d11.json
  cd ~/workspace/hobo/hobobiker-copy && ddev exec -d /var/www/html/test/playwright \
    'STORED_TARGET_EXPORT=tmp/stored-d11.json MIGRATION_TARGET=drupal11 npx playwright test --grep "stored:"'
  ```

- Caching is off in this copy (`web/sites/default/settings.local.php`), so a test never sees a stale page.

## What is NOT disposable

- **Production** (hobobiker.com, www.hobobiker.com, the host `ddevprod.thefays.us`): never connect to it or write
  to it. Never run `ddev pull`, `ddev push`, `ddev auth ssh`, or `rsync`/`scp`/`ssh` to a remote host.
  **Never request `hobobiker.com`** (old content hardcodes it; the suite treats it as the target).
- **Git:** `rfay` is the real GitHub repository (rfay/hobobiker-d11). Never `git push`. Do not commit unless the
  human asks.
- `hobobiker-copy` is the reference for every test: never modify it, its database or its files. Its frozen
  baseline (`test/playwright/baseline/`) is read-only.
- A tripwire hook (`.claude/hooks/deny-production.sh`, patterns in `.agent-protected-patterns`) blocks commands
  that match these rules. If it fires, stop and ask; do not work around it.
- **Never write a secret value or personal data** into a file, commit, log or message. Check by length, hash or
  count.

## Ground rules

- **Reproduce the site, don't repair it.** A page broken on the original stays broken the same way; an
  unpublished page still answers 403. Record findings in `docs/DISCOVERIES.md` here.
- Deliberate changes are declared in `hobobiker-copy/test/playwright/expected-differences.json` with
  `"target": "drupal11"`, exact (`equals` or an anchored `regex`), each with a reason.
- **Every script reports a count** and is rerunnable, keeping the original ids (nid, cid, tid, fid, uid).
- **No PHP stored in the content is executed** (the owner's rule): content that ran PHP on the original is
  rewritten.
- Drupal 6: the current revision is `node.vid = node_revisions.vid`; `url_alias` has `src`/`dst`.
- Drupal's database layer reads `[brackets]` in a query string as a quoted identifier: pass patterns as
  placeholders.
- After each step: stop, summarize what was done and what it measured, explain the next step, and ask before going
  on.
