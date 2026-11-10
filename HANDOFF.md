# Handoff: marlene.hobobiker.com (last updated 2026-11-07)

For whoever works on this site next, a person or an agent. Read [AGENTS.md](AGENTS.md) first: its production
rules still hold. What was found along the way is in [docs/DISCOVERIES.md](docs/DISCOVERIES.md).

## Where it stands

- **Branch `marlene`** is the site at marlene.hobobiker.com: the Drupal 11 rebuild of hobobiker.com in a modern
  theme (`marlene`), with its content cleaned up. It **broke off from Drupal 6 on 2026-11-06**: it is no longer
  rebuilt from the D6 copy, and **the marlene DDEV project's database is the master** (worktree
  `~/workspace/hobo/marlene`, https://marlene.ddev.site).
- **Branch `main`** is d11.hobobiker.com, the faithful rebuild (theme `hobo_golden`); "reproduce, don't repair"
  still applies there. Both are on Drupal 11.4.12 (PHP 8.4, MariaDB 11.8); `composer audit` was clean on
  2026-11-07.
- Tests: the suite in hobobiker-copy passes in full against marlene with `MIGRATION_TARGET=marlene` (2538, on
  2026-11-07). Every deliberate change is declared there, `"target": "marlene"`.

## Working on it

- Work in the marlene DDEV project, never on production. Its database holds the `d6` schema too (loaded as
  `scripts/00-reset.sh` does), which some scripts read.
- **Never run `scripts/00-reset.sh` or `scripts/run-all.sh` here**: they would replace the master database with a
  rebuild from Drupal 6 and lose every edit since the break.
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
| **hobobiker.com** (after the cutover below) | This branch, `marlene`: the living site | Yes: this is where the site goes on |
| marlene.hobobiker.com | The same, for review before the cutover; afterwards a staging copy, or retired | Review only |
| d11.hobobiker.com | Branch `main`: the faithful Drupal 11 rebuild of the original | Only security updates |
| old.hobobiker.com | The original Drupal 6 site, kept as it was | Never |
| static.hobobiker.com | Part 2's static HTML archive (GitHub Pages, rfay/hobobiker-static) | Never |

- **The old versions are frozen references.** old.hobobiker.com and static.hobobiker.com show what the site was;
  nothing is fixed or added there. d11.hobobiker.com shows the rebuild before it changed; it only needs Drupal
  security releases while it stays up (or retire it). Drupal 6 and its PHP are long out of support: if
  old.hobobiker.com stays public, keep it behind a password, or let static.hobobiker.com be the public archive.
  Its posts link to `http://hobobiker.com/...`, so after the cutover those links lead to the new site.
- **Where the master copy lives changes at the cutover.** Until then it is the marlene DDEV project here, and its
  database goes up to the server. From the cutover on, **the production database is the master**: content is
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

## Deploying: marlene.hobobiker.com for review, then hobobiker.com

The human deploys; agents never connect to the production server. The commands assume a DDEV project per site
under `~/workspace/` on that server, as `.ddev/providers/rsync.yaml` does for `d11.hobobiker.com`; adjust names.

**1. Get ready, here (the marlene DDEV project):**

```bash
cd ~/workspace/hobo/marlene
git status                        # clean: everything committed
git push rfay marlene
ddev drush config:status          # "No differences"
ddev snapshot --name deploy-$(date +%F)
```

**2. Once, on the server: the marlene.hobobiker.com project.**

```bash
git clone -b marlene git@github.com:rfay/hobobiker-d11 ~/workspace/marlene.hobobiker.com
cd ~/workspace/marlene.hobobiker.com
ddev config --project-name=marlene-hobobiker --additional-fqdns=marlene.hobobiker.com
ddev start                              # the post-start hook runs composer install
ln -s sites/default/files web/files     # old posts link to /files/...; Git does not carry this symlink
```

There is no `settings.local.php` on the server, and there must not be: without it, Drupal caches pages and
aggregates CSS and JS, as production should.

**3. The database and the files, from here to the server.** The rsync provider pushes a snapshot of the whole
database and makes the server's `web/sites/default/files` match this one (`--delete`), `flickr/` included:

```bash
ddev auth ssh
ddev push rsync --environment=remote_root=workspace/marlene.hobobiker.com
```

(Not run here: agents may not run `ddev push`. Check `ddev push --help` for the `--environment` flag; if it is
missing, copy `.ddev/providers/rsync.yaml` to `marlene.yaml` with that `remote_root` and run `ddev push marlene`.)

**4. On the server:**

```bash
ddev drush cr
ddev drush config:status          # "No differences"
ddev drush status                 # Drupal 11.4.x, database connected
```

Set up cron (for example the ddev-cron add-on, or a host crontab running `ddev drush cron` hourly); it keeps the
search index current.

**5. Review marlene.hobobiker.com:** the front page and a few posts on a phone and a desktop, light and dark; a
photo enlarging in the lightbox; a trip day with its map; search; /aplv, /espanol, /podcast. The first visits to
pages with many photos may answer 503 for some thumbnails ("Image generation in progress") until each is
generated once; reload.

**6. Before the cutover:**
- **Mail.** DDEV's hardened images send no mail (`sendmail_path` still names Mailpit, which is not running; see
  https://docs.ddev.com/en/stable/users/topics/hosting/#caveats). `settings.php` switches Drupal to core's
  Symfony mailer over SMTP when `MAILER_DSN` is set, and sets the site's From address from `SITE_MAIL`. On the
  server only, in `.ddev/.env.web` (ignored by Git), then `ddev restart`:

  ```bash
  MAILER_DSN=smtp://USER:PASSWORD@smtp.mailgun.org:587   # Mailgun SMTP credentials, URL-encoded
  SITE_MAIL=address-on-the-mailgun-domain
  ```

  The From domain needs Mailgun's DKIM record and `include:mailgun.org` in its SPF. Check with
  `ddev drush cget system.mail --include-overridden`, then send yourself a real mail (a one-time login link for
  uid 1; delete it after reading) and check "Show original" for SPF and DKIM `PASS`:

  ```bash
  ddev drush php:eval '\Drupal::service("plugin.manager.mail")->mail("user", "password_reset", "you@example.org", "en", ["account" => \Drupal\user\Entity\User::load(1)]);'
  ```

  Without the variables (local development), mail goes to Mailpit as before.
- **Freeze content** on the source of the final push: after the last push, edit only on the new site.
- **Move the original** to old.hobobiker.com, password-protected or not, as decided above. Snapshot it first
  (`ddev snapshot` in its project).

**7. The cutover.** DDEV's router decides which project answers which name:

```bash
# In the Drupal 6 project on the server: give up the names
ddev config --additional-fqdns=old.hobobiker.com && ddev restart
# In the marlene project: take them (keep marlene.hobobiker.com while it is useful)
ddev config --additional-fqdns=hobobiker.com,www.hobobiker.com,marlene.hobobiker.com && ddev restart
```

Check https://hobobiker.com and https://www.hobobiker.com (certificates), a few old addresses (`/node/3994`,
`/inuvik_to_ushuaia`, `/files/The%20Women%20of%20Friendship%20Bridge%20Guatemala.pdf`,
`/tripinfo_item/2007-08-03/2007-06-28/2008-04-14`), search and the contact form.

**Rollback:** swap the names back (`--additional-fqdns` on both projects) and restart both; the Drupal 6 site and
its database are untouched.

**8. After the cutover:** production is the master (see above). For a code change: commit and push here, then on
the server `git pull`, `ddev composer install`, `ddev drush updb -y`, `ddev drush cim -y`, `ddev drush cr`. To
work on a fresh copy here, `ddev pull` from production (a provider like `.ddev/providers/rsync.yaml`, pointed at
the hobobiker.com project).

**d11.hobobiker.com** (`main`, still the rebuild): `git pull`, `ddev composer install`, `ddev drush updb -y`,
`ddev drush cim -y`, three `ddev drush cron` runs (the search index, 500 posts a run), `ddev drush cr`. `main`
still commits `settings.local.php`, so it runs without caching: fine for a reference site, or apply this branch's
change there too.

**Drupal updates** (both branches): `ddev composer update 'drupal/core*' -W`, `ddev drush updb -y`,
`ddev drush cr`, the suite, commit, deploy as code. Watch `ddev composer audit`.

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
7. **`main`.** If d11.hobobiker.com is no longer needed as the faithful rebuild, retire it and keep `marlene`; if
   it stays, keep its Drupal core in step (security releases).

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
