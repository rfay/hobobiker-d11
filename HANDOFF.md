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
- **Automatic fixes are render-time filters** (the `hobobiker_photos` filter in the hobobiker module): the stored
  posts stay as they are and the fix deploys as code and configuration. **A fix that needs judgment is an edit to
  the content**, as a new revision with a log message, done by a small rerunnable script in `scripts/` so it is
  on record (see the table in the README).
- After a change: `ddev drush cex -y`, run the suite (README, "Testing"), and declare any deliberate difference.

## Deploying

The human deploys; agents never connect to ddevprod.thefays.us.

- **marlene.hobobiker.com:** the code (Git), the database and the files (`web/sites/default/files`, including
  `flickr/`), then `ddev drush cr`. The first visits to pages with many photos may answer 503 for some thumbnails
  ("Image generation in progress") until each is generated once.
- **d11.hobobiker.com:** `git pull`, `ddev composer install`, `ddev drush updb -y`, `ddev drush cim -y`, three
  `ddev drush cron` runs (the search index, 500 posts a run), `ddev drush cr`.
- **Drupal updates** (both branches): `ddev composer update 'drupal/core*' -W`, `ddev drush updb -y`,
  `ddev drush cr`, the suite, commit. Watch `ddev composer audit`.

## Done since the break from Drupal 6

| Area | What | Where |
| --- | --- | --- |
| Look | Theme `marlene`: design tokens, light and dark, disclosure menu, banner of Randy and Nancy, favicon set; WCAG 2.2 AA in the theme (contrast, reflow, focus, target size) | `web/themes/custom/marlene` |
| Search | Content search for every visitor (Drupal 11.4's `search_node`; also on `main`) | `scripts/85-search.php` |
| Photos | Local copies of the Flickr photos (1024 px) and their titles; small photos enlarge in the lightbox; links to Flickr photo pages open the local copy; captions and alt text for every photo | `scripts/flickr-fetch.php`, `hobobiker_photos` filter |
| Photos | Thumbnails the right way up: `image_effects` auto-orient; 13 photos with sideways pixels given the EXIF orientation | `scripts/fix-sideways-photos.php` |
| Photos | Captions written for the 8 photos whose title was a camera number; node 4300's broken vicuñas photo fixed; a truncated photo removed from node 3880 | `scripts/caption-photos.php` |
| Leftovers | Flickr "Originally uploaded by" credits, Babelfish links, the old blog links under posts, Flash players (YouTube plays again), Flickr's retired slideshow embeds (now links), absolute hobobiker.com links | filter, `scripts/remove-babelfish.php`, theme |

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
2. **Old map and video embeds.** Google's classic My Maps (`maps.google.com/maps/ms`, `maps?f=q` with a KMZ),
   a Google My Maps (`/maps/d/`), mapufacture.com (gone) and a CNN video. Not verified: several pass a
   hobobiker.com file to Google, so requesting them would reach production. The site already draws KML routes
   itself with Leaflet (the tripinfo maps, `[multisource:]`): the KMZ-based embeds could become those maps.
3. **Spanish posts.** 17 posts are tagged Español but their pages do not say so (`lang="es"`, WCAG 3.1.2); the
   theme could set it on posts with that tag.
4. **The mailing list.** The /latest_stories header invites readers to subscribe at /subscribe, which answers 403
   (an unpublished page). Remove the sentence or the page's status, the owner's call.
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
