# Handoff: hobobiker.com (last updated 2026-11-10)

For whoever works on this site next, a person or an agent. Read [AGENTS.md](AGENTS.md) first: its production
rules still hold. What was found along the way is in [docs/DISCOVERIES.md](docs/DISCOVERIES.md).

## Where it stands

- **Branch `marlene` is hobobiker.com** since the cutover on 2026-11-10: the Drupal 11 rebuild in a modern theme
  (`marlene`), with its content cleaned up. It **broke off from Drupal 6 on 2026-11-06** and is no longer rebuilt
  from the D6 copy. **The production database is the master**; the marlene DDEV project here (worktree
  `~/workspace/hobo/marlene`, https://marlene.ddev.site) is a development copy. How production runs and how to
  deploy: "Production" below.
- **Branch `main`** is the faithful rebuild (theme `hobo_golden`), no longer served; "reproduce, don't repair"
  still applies there. Both are on Drupal 11.4.12 (PHP 8.4, MariaDB 11.8); `composer audit` was clean on
  2026-11-07.
- Tests: the suite in hobobiker-copy passes in full against marlene with `MIGRATION_TARGET=marlene` (2538, on
  2026-11-07). Every deliberate change is declared there, `"target": "marlene"`.

## Working on it

- Work in the marlene DDEV project, never on production. Its database holds the `d6` schema too (loaded as
  `scripts/00-reset.sh` does), which some scripts read.
- **Never run `scripts/00-reset.sh` or `scripts/run-all.sh` here**: they would replace this copy with a rebuild
  from Drupal 6, without every edit since the break. Refresh it from production instead (`ddev pull rsync`).
- Take a snapshot before any change to content: `ddev snapshot --name before-WHAT`.
- `web/sites/default/settings.local.php` is for this development copy only (no caching, no CSS/JS aggregation)
  and is not in Git, as Drupal intends; production has none. Settings every environment needs go in
  `settings.php`.
- **Automatic fixes are render-time filters** (the `hobobiker_photos` filter in the hobobiker module): the stored
  posts stay as they are and the fix deploys as code and configuration. **A fix that needs judgment is an edit to
  the content**, as a new revision with a log message, done by a small rerunnable script in `scripts/` so it is
  on record (see the table in the README).
- After a change: `ddev drush cex -y`, run the suite (README, "Testing"), and declare any deliberate difference.

## The sites, and managing change from here

| Site | What it is | Changes? |
| --- | --- | --- |
| **hobobiker.com**, www.hobobiker.com | This branch, `marlene`: the living site (since 2026-11-10) | Yes: this is where the site goes on |
| (not served) | Branch `main`: the faithful Drupal 11 rebuild of the original; d11. and marlene.hobobiker.com answer 404 | Only security updates, if served again |
| old.hobobiker.com | The original Drupal 6 site, kept as it was: a backup, to be stopped | Never |
| static.hobobiker.com | Part 2's static HTML archive (GitHub Pages, rfay/hobobiker-static) | Never |

- **The old versions are frozen references.** old.hobobiker.com and static.hobobiker.com show what the site was;
  nothing is fixed or added there. `main` keeps the rebuild before it changed, in Git. Drupal 6 and its PHP are
  long out of support: old.hobobiker.com is a backup with no password, to be stopped (the owner's decision);
  static.hobobiker.com is the public archive. Its posts link to `http://hobobiker.com/...`, so after the cutover those links lead to the new site.
- **The master copy is production's database** since the cutover. Before it, the marlene DDEV project here was
  the master and its database went up to the server once. Now **the production database is the master**: content is
  edited on hobobiker.com in Drupal, and a development copy is made by pulling production down (`ddev pull`),
  never the reverse. Code and configuration go one way: develop here, commit, push to GitHub, `git pull` on the
  server.
- **Kinds of change.** A fix to how content is shown is code (a filter or the theme) and deploys as code. A change
  to what a post says is an edit, made in Drupal on production (or, for many posts at once, a reviewed script run
  on a fresh copy of production first, then on production). Configuration changes are exported
  (`ddev drush cex`), committed, and imported on the server (`ddev drush cim`).
- **The test suite** (hobobiker-copy) compares with the original. It stays useful as a regression check while the
  content is close to the original: declare deliberate changes for `marlene`. When the site has moved on far
  enough that the declarations are noise, freeze the suite at its last passing run and rely on checks of the new
  site instead.

## Production: how hobobiker.com runs (cut over 2026-11-10)

The human deploys; agents never connect to the server. Agents may make read-only HTTP requests to the public
sites (AGENTS.md).

**Where it is.** ddevprod.thefays.us, DDEV project `hobobiker` in `~/workspace/d11.hobobiker.com` (the directory
kept the rebuild's name), branch `marlene`, DDEV's hardened images and Let's Encrypt. It answers for hobobiker.com
and www.hobobiker.com; d11. and marlene.hobobiker.com no longer answer (404). The Drupal 6 original is
old.hobobiker.com: a backup, no password, to be stopped. ddevprod takes nightly snapshots of every project.

**How it got there.** The d11 project was switched to `marlene`; this project's database and files went up with
`ddev push rsync` (the provider restores the snapshot by name since `48f7d4b0`); the `post-start` hook ran
`composer install`; `ddev drush cr`; the hostnames moved from the Drupal 6 project. **That push was the last.**
The production database is the master now: `ddev push rsync` would overwrite its content and files.

**In Git** (deployed as code):
- `settings.php`: errors hidden from visitors (`system.logging error_level = 'hide'`; DDEV's settings would show
  them); mail and notification addresses from the environment (below). No `settings.local.php` on the server.
- `.ddev/nginx/redirect.conf`: http answers 301 to https (the old site's snippet).
- `config/sync`: page max-age one hour (`Cache-Control: max-age=3600, public`); the page cache answers `HIT`.

**On the server only** (not in Git; recreate them if the project is ever rebuilt):
- The symlink `web/files -> sites/default/files` (old posts link to `/files/...`).
- `.ddev/.env.web` (ignored by Git), then `ddev restart`:

  ```bash
  MAILER_DSN=smtp://host.docker.internal:25?auto_tls=false   # the host's Postfix, from the web container
  SITE_MAIL=from-address                                     # the site's From address
  NOTIFY_MAIL=address-for-security-update-notices            # comma-separated for more
  ```

  `config/sync` still holds the sanitized snapshot's `example.*` addresses; these override them.
- Mail: hardened images send none (`sendmail_path` names Mailpit, which is not running; see
  https://docs.ddev.com/en/stable/users/topics/hosting/#caveats), so Drupal sends with core's `symfony_mailer`
  over SMTP to the host's Postfix, which relays through Mailgun. Postfix listens on all interfaces;
  `mynetworks` and a ufw rule (`allow from 172.16.0.0/12 to any port 25`) admit the Docker networks (the web
  container leaves from its project network, `ddev-hobobiker_default`). ufw's default deny keeps 25 closed to
  the internet.
- Cron on the host (search index, update checks).

**Deploying a change.** Develop and verify here, commit, `git push rfay marlene`; on the server:

```bash
ddev snapshot --name before-$(git rev-parse --short HEAD)
git pull
ddev restart                 # only when .ddev/ or .env.web changed; it also runs composer install
ddev composer install
ddev drush updb -y
ddev drush cim -y
ddev drush cr
chmod a-w web/sites/default/settings.php   # git checks it out writable; the status report warns
```

Then check from outside: the front page and `/node/3994` (200, `Cache-Control: max-age=3600`), `http://` (301),
`/files/The%20Women%20of%20Friendship%20Bridge%20Guatemala.pdf` (200), search, `/rss.xml`. **Rollback:**
`git checkout` the previous commit, `ddev snapshot restore before-<commit>`, `ddev drush cr`.

**Checking mail** (no login link in it):

```bash
ddev drush php:eval '$to = "you@example.org"; $c = \Drupal::config("system.site"); $m = \Drupal::service("plugin.manager.mail")->createInstance("symfony_mailer"); $msg = $m->format(["to" => $to, "subject" => "hobobiker.com mail test " . date("c"), "body" => ["A test from hobobiker.com, sent by Drupal through the server SMTP."], "headers" => ["From" => "\"" . $c->get("name") . "\" <" . $c->get("mail") . ">", "Content-Type" => "text/plain; charset=UTF-8"]]); echo $m->mail($msg) ? "sent to $to from " . $c->get("mail") . "\n" : "FAILED\n";'
sudo tail -5 /var/log/mail.log    # status=sent
```

**A fresh copy here.** `ddev pull rsync` (`.ddev/providers/rsync.yaml` points at the production project) replaces
this project's database and files with production's.

**Drupal updates:** `ddev composer update 'drupal/core*' -W`, `ddev drush updb -y`, `ddev drush cr`, the suite,
commit, deploy as above. Watch `ddev composer audit`.

**`main`** (the faithful rebuild) is no longer served anywhere; it stays in Git as the reference. To serve it
again, give it its own DDEV project on the server.

## Done since the break from Drupal 6

| Area | What | Where |
| --- | --- | --- |
| Look | Theme `marlene`: design tokens, light and dark, disclosure menu, banner of Randy and Nancy, favicon set; WCAG 2.2 AA in the theme (contrast, reflow, focus, target size) | `web/themes/custom/marlene` |
| Search | Content search for every visitor (Drupal 11.4's `search_node`; also on `main`) | `scripts/85-search.php` |
| Photos | Local copies of the Flickr photos (1024 px) and their titles; small photos enlarge in the lightbox; links to Flickr photo pages open the local copy; captions and alt text for every photo | `scripts/flickr-fetch.php`, `hobobiker_photos` filter |
| Photos | Thumbnails the right way up: `image_effects` auto-orient; 13 photos with sideways pixels given the EXIF orientation | `scripts/fix-sideways-photos.php` |
| Photos | Captions written for the 8 photos whose title was a camera number; node 4300's broken vicuñas photo fixed; a truncated photo removed from node 3880 | `scripts/caption-photos.php` |
| Leftovers | Flickr "Originally uploaded by" credits, Babelfish links, the old blog links under posts, Flash players (YouTube plays again), Flickr's retired slideshow embeds (now links) | filter, `scripts/remove-babelfish.php`, theme |
| Hostname | No hobobiker.com as a hostname in content or configuration: links and files site-relative, map tokens loading local KMZ files, e-mail addresses replaced by the contact page, commented-out embeds that loaded files from the domain removed; the e-mail list and its /subscribe page deleted. Node 4111, an unpublished maintenance log, keeps its history | `scripts/remove-hostname.php`, `scripts/remove-hostname-config.php` |
| Settings | `settings.local.php` (no caching, no aggregation) is local only and out of Git; production caches pages and aggregates CSS and JS | `.gitignore`, `config/sync/system.performance.yml` |

## Future paths

Roughly in order of value.

1. **Independence from Flickr.** The December 2025 migration (hobobiker-copy's `README_FLICKR_MIGRATION.md`)
   brought the photos shown in posts home; this branch did the rest of those. What still depends on Flickr is
   about 55 links to albums, album slideshows, tag pages and the photostream (all working on 2026-11-07: the
   account has about 6,450 photos, against the 524 used in posts). If the account lapses, they break. The path:
   request the account's data export on flickr.com (originals plus album and photo metadata, descriptions
   included; it is free), import the albums the posts link to as local galleries (a script, like
   `flickr-fetch.php`; Lightbox2 already has a slideshow mode), and let the filter point album, slideshow and tag
   links at them. Until then, three small fixes: two album links end in `/set` and answer 404 (drop the suffix),
   two links go to Flickr's image servers for photos the site already has, and the links could use https.
2. **Third-party map and video embeds.** The route pages show Google My Maps (`google.com/maps/d/...`), and one
   post each embeds mapufacture.com (gone) and a CNN video; not verified. The site draws KML routes itself with
   Leaflet (the tripinfo maps, `[multisource:]`), from the KMZ files it holds: the My Maps could become those.
3. **Spanish posts.** 17 posts are tagged Español but their pages do not say so (`lang="es"`, WCAG 3.1.2); the
   theme could set it on posts with that tag.
4. **Mail.** Needed before the cutover (see Deploying, step 6): on DDEV the contact form's mail goes to Mailpit.
5. **Content left as it was.** 41 unpublished posts, and 446 stored comments (168 published) that are hidden, as
   the original hid them (its comment module was off). Review, publish or delete.
6. **Alt text quality.** Every photo has alt text now, from its Flickr title or file name; good, but generic in
   places. The photos that matter most (the front page, the trip summaries) deserve descriptions written by
   someone who was there.
7. **`main`.** It is no longer served (its d11 project became hobobiker.com). Keep it in Git as the reference, or
   give it a DDEV project of its own again and keep its Drupal core in step.

## How the checks were done

- **The suite:** README, "Testing".
- **Accessibility:** axe-core 4.10 (WCAG 2.0, 2.1, 2.2 A and AA rules) run in Playwright from hobobiker-copy's
  web container, on ten representative pages, light and dark, desktop and phone, menus open; plus scripts that
  tab through pages (focus visible, focus not hidden under the sticky header, target size) and look for sideways
  scrolling at 320 px and at 200% text. These were one-off scripts, not kept in the repository.
- **Photos:** contact sheets of every photo the posts show (ImageMagick `montage`, in the web container), checked
  by eye and by the owner.
- Never request hobobiker.com or ddevprod.thefays.us; the tripwire hook (`.claude/hooks/deny-production.sh`)
  blocks commands that mention them. If it fires, stop and ask.
