# Hobobiker.com on Drupal 11

hobobiker.com is the blog where Randy Fay and Nancy Lewis documented their 2.5-year bicycle trip through the
Americas (2006–2009) and some earlier trips. It ran on Drupal 6 for more than 15 years. This repository is its
rebuild on Drupal 11 (11.4, PHP 8.4, MariaDB 11.8), started live in Part 3 of "Hobobiker Rides Again: Plan it,
test it, migrate it" and built **entirely by rerunnable scripts** from a copy of the original's database.

**This branch, `marlene`, is hobobiker.com** (since 2026-11-10): the same rebuild in a modern theme, with its
content cleaned up. It broke off from Drupal 6 on 2026-11-06; production's database is now the master. For where it stands
and where it could go next, read **[HANDOFF.md](HANDOFF.md)**.

## The sites

| Site | Branch | What it is |
| --- | --- | --- |
| <https://hobobiker.com> | `marlene` | The site in a modern, responsive theme, `marlene`: light and dark, a phone-friendly menu, a banner of Randy and Nancy before the Cordillera Blanca; photos local and enlargeable, captioned, the right way up; content search. |
| (not served) | `main` | The faithful rebuild, reproducing the original page by page, its 20-year-old look included (theme `hobo_golden`, a port of the original's). |
| <https://old.hobobiker.com> | | The Drupal 6 original, a backup, to be stopped. |
| <https://static.hobobiker.com> | ([rfay/hobobiker-static](https://github.com/rfay/hobobiker-static)) | Part 2's static HTML archive of the original. |

The `marlene` theme is named for Marlene Wanberg, whose
[UI Theme Explorer](https://mwanberg.github.io/ai-playground/ui-theme-explorer/)
([source](https://github.com/mwanberg/ai-playground)) is the model for its design tokens. It is generated from
core's Starterkit, so no base theme can change underneath it; see
[web/themes/custom/marlene/README.md](web/themes/custom/marlene/README.md).

## Related repositories

- [rfay/hobobiker.com](https://github.com/rfay/hobobiker.com): the original Drupal 6 source, and the test suite
  every target is checked against (`test/playwright`).
- [rfay/hobobiker-assets v1.2](https://github.com/rfay/hobobiker-assets/releases/tag/v1.2): the sanitized
  database and the files (earlier releases were withdrawn).
- [rfay/site-migration-kit](https://github.com/rfay/site-migration-kit): the migration kit vendored into the
  test suite.
- [rfay/hobobiker-migration-slides](https://github.com/rfay/hobobiker-migration-slides): the slides.

## Working on this branch

Work in the marlene DDEV project (worktree `~/workspace/hobo/marlene`, https://marlene.ddev.site); its database
is the master. **Do not run `scripts/00-reset.sh` or `scripts/run-all.sh` here**: they rebuild from Drupal 6
and would lose every change since the break. Snapshot before changing content (`ddev snapshot`).

Automatic content fixes are render-time filters in the hobobiker module: the stored posts are not changed. A fix
that needs judgment is an edit to the posts, made by a rerunnable script and saved as new revisions with a log
message. Those since the break:

| Script | What it did |
| --- | --- |
| `scripts/flickr-fetch.php` | Local 1024 px copies of the Flickr photos the posts show or link to, and their Flickr titles (`public://flickr/manifest.json`), for the `hobobiker_photos` filter |
| `scripts/fix-sideways-photos.php` | 13 photos with sideways pixels given the EXIF orientation that turns them upright; their photo tokens made portrait |
| `scripts/caption-photos.php` | Captions for 8 photos whose Flickr title was only a camera number; a broken photo path fixed |
| `scripts/remove-babelfish.php` | Nine links to Babelfish, the dead machine translation, taken out of six posts |

The `hobobiker_photos` filter makes small Flickr photos enlarge to their local copy, points links to Flickr photo
pages at it, gives every photo a caption and alt text, removes Flickr's "Originally uploaded by" credits, turns
Flash players and Flickr's retired slideshow embeds into players or links, and makes old hobobiker.com links
site-relative. `image_effects` turns thumbnails upright from their EXIF orientation.

## Testing

The test suite lives in the original's repository and compares a target with the original's frozen baseline:
semantic content, access, stored content and more. Deliberate differences are declared in its
`expected-differences.json`, scoped by target; this branch's are `"target": "marlene"`.

```bash
cd ~/workspace/hobo/hobobiker-copy && ddev exec -d /var/www/html/test/playwright \
  'TEST_BASE_URL=https://marlene.ddev.site MIGRATION_TARGET=marlene npx playwright test --grep "semantic:|access:|baseline:"'
```

For `main`, use `TEST_BASE_URL=https://hobobiker-d11.ddev.site MIGRATION_TARGET=drupal11`.

## Deploying

hobobiker.com runs this branch in a DDEV project on ddevprod.thefays.us; **its database is the master**, so only
code and configuration go up. The human deploys; agents never connect to the server. Develop and verify here,
commit, `git push rfay marlene`; on the server:

```bash
ddev snapshot --name before-$(git rev-parse --short HEAD)
git pull
ddev composer install
ddev drush updb -y
ddev drush cim -y
ddev drush cr
```

(`ddev restart` too when `.ddev/` changed.) A fresh copy here: `ddev pull rsync`; never `ddev push rsync`, which
would overwrite production. What lives only on the server (mail settings in `.ddev/.env.web`, the `web/files`
symlink, cron, the Postfix relay) and how to check a deploy: **[HANDOFF.md](HANDOFF.md#production-how-hobobikercom-runs-cut-over-2026-11-10)**.

## How the site was first built

Each step in `scripts/` is rerunnable, keeps the original ids (nid, vid, cid, tid, fid, uid) and prints what it
counted. `scripts/run-all.sh` runs them in order; `scripts/00-reset.sh` starts over (a fresh install, the Drupal 6
database reloaded into the schema `d6`, which the steps read through a second connection). On `main` that is
still how the site is made; on this branch it is history.

| Step | What it does |
| --- | --- |
| 10 | The structure: content types, fields, vocabularies, text formats and image styles, from Drupal 6's own definitions |
| 20 | The 4 users, with their uids, names, dates, status and roles |
| 30 | The 82 terms, with their vocabularies, descriptions, weights and parents |
| 40 | The 1296 nodes, with their nids and vids, at their current revision |
| 50 | The 446 comments, with their threads (the one on a deleted node is dropped) |
| 60 | The 44 managed files and what references them; the `/files` path the old content links to |
| 65 | The listing pages: the Drupal 6 Views and the blog module's pages |
| 70 | The URL aliases, for every path that exists on Drupal 11 |
| 75 | The `hobobiker` module (the original's custom filters, lightbox, trip info, audio) |
| 80 | The main menu, the navigation links behind the breadcrumbs, and the front page |
| 85 | Content search for every visitor, and its index |
| 90 | The original's look, theme `hobo_golden` |
| 95 | (`marlene` branch) The modern look, theme `marlene`, as the default |
| 99 | Checks the data is valid Drupal 11 data; changes nothing |

The files (1.1 GB) come from the original's `sites/default/files`, copied, never moved. The rules the rebuild
followed are in [AGENTS.md](AGENTS.md).

## Further reading

- [HANDOFF.md](HANDOFF.md): where this branch stands, how to work on it and deploy it, how change is managed, and future paths.
- [docs/DISCOVERIES.md](docs/DISCOVERIES.md): what the work found, including what the test suite missed and a
  person caught.
- [docs/PLAN.md](docs/PLAN.md): the rebuild's plan, with what had to come over, measured.
- Recaps on drupal.org:
  [Part 1: Road Test](https://www.drupal.org/docs/develop/development-tools/ai-coding-tools-for-drupal-development/drupal-ai-learners-club-sessions/2026-10-16-%E2%80%94-part-1-hobobiker-rides-again-plan-it-test-it-migrate-it-%E2%80%94-road-test-having-claude) (Oct 16),
  [Part 2: The Last Ride](https://www.drupal.org/docs/develop/development-tools/ai-coding-tools-for-drupal-development/drupal-ai-learners-club-sessions/2026-10-23-%E2%80%94-part-2-hobobiker-rides-again-plan-it-test-it-migrate-it-%E2%80%94-the-last-ride-sending-a) (Oct 23), and
  [Part 3: The Long Haul](https://www.drupal.org/docs/develop/development-tools/ai-coding-tools-for-drupal-development/drupal-ai-learners-club-sessions/2026-10-30-%E2%80%94-part-3-hobobiker-rides-again-plan-it-test-it-migrate-it-%E2%80%94-the-long-haul-planning-and) (Oct 30).
- Slides: [Part 1](https://rfay.github.io/hobobiker-migration-slides/),
  [Part 2](https://rfay.github.io/hobobiker-migration-slides/part2.html),
  [Part 3](https://rfay.github.io/hobobiker-migration-slides/part3.html).
