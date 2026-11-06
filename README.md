# Hobobiker.com on Drupal 11

hobobiker.com is the blog where Randy Fay and Nancy Lewis documented their 2.5-year bicycle trip through the
Americas (2006–2009) and some earlier trips. It ran on Drupal 6 for more than 15 years. This repository is its
rebuild on Drupal 11 (11.4, PHP 8.4, MariaDB 11.8), started live in Part 3 of "Hobobiker Rides Again: Plan it,
test it, migrate it" and built **entirely by rerunnable scripts** from a copy of the original's database: nothing
configured by hand.

## The sites

| Site | Branch | What it is |
| --- | --- | --- |
| <https://d11.hobobiker.com> | `main` | The Drupal 11 rebuild, reproducing the original page by page, its 20-year-old look included (theme `hobo_golden`, a port of the original's). |
| <https://marlene.hobobiker.com> | `marlene` | The same site and content in a modern, responsive theme, `marlene`: light and dark, a phone-friendly menu, a banner of Randy and Nancy before the Cordillera Blanca. |
| <https://static.hobobiker.com> | ([rfay/hobobiker-static](https://github.com/rfay/hobobiker-static)) | Part 2's static HTML archive of the original. |

The `marlene` theme is named for Marlene Wanberg, whose
[UI Theme Explorer](https://mwanberg.github.io/ai-playground/ui-theme-explorer/)
([source](https://github.com/mwanberg/ai-playground)) is the model for its design tokens. It is generated from
core's Starterkit, so no base theme can change underneath it; see
[web/themes/custom/marlene/README.md](web/themes/custom/marlene/README.md) on the `marlene` branch.

## Related repositories

- [rfay/hobobiker.com](https://github.com/rfay/hobobiker.com): the original Drupal 6 source, and the test suite
  every target is checked against (`test/playwright`).
- [rfay/hobobiker-assets v1.2](https://github.com/rfay/hobobiker-assets/releases/tag/v1.2): the sanitized
  database and the files (earlier releases were withdrawn).
- [rfay/site-migration-kit](https://github.com/rfay/site-migration-kit): the migration kit vendored into the
  test suite.
- [rfay/hobobiker-migration-slides](https://github.com/rfay/hobobiker-migration-slides): the slides.

## How the site is built

Each step in `scripts/` is rerunnable, keeps the original ids (nid, vid, cid, tid, fid, uid) and prints what it
counted. `scripts/run-all.sh` runs them in order; `scripts/00-reset.sh` starts over (a fresh install, the Drupal 6
database reloaded into the schema `d6`, which the steps read through a second connection).

```bash
ddev start
scripts/00-reset.sh     # destroys this project's database, never the original's
scripts/run-all.sh
```

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

The files (1.1 GB) come from the original's `sites/default/files`, copied, never moved. The rules the work
followed are in [AGENTS.md](AGENTS.md): reproduce the site, don't repair it; declare every deliberate change; no
PHP stored in the content is executed.

## Testing

The test suite lives in the original's repository and compares a target with the original's frozen baseline:
semantic content, access, stored content and more. Deliberate differences are declared in its
`expected-differences.json`, scoped by target.

```bash
cd ~/workspace/hobo/hobobiker-copy && ddev exec -d /var/www/html/test/playwright \
  'TEST_BASE_URL=https://hobobiker-d11.ddev.site MIGRATION_TARGET=drupal11 npx playwright test --grep "semantic:|access:|baseline:"'
```

Use `MIGRATION_TARGET=marlene` for the `marlene` branch: it allows the new theme's wording (byline, tag chips,
field labels, read more) on top of the Drupal 11 rebuild's own declared differences.

## Deploying a change

**`marlene`** broke off from Drupal 6 on 2026-11-06: its DDEV project's database is the master, not a rebuild.
Deploy the code, the database **and the files** (`web/sites/default/files`, including `flickr/`: the local copies
of the Flickr photos, from `scripts/flickr-fetch.php`) to marlene.hobobiker.com, then `ddev drush cr` there.
Automatic content fixes are render-time filters in the hobobiker module (`hobobiker_photos`), so they deploy as
code and configuration; the stored posts are not changed.

**`main`** (d11.hobobiker.com) is still the rebuild: code from Git, then

```bash
ddev drush cim -y
ddev drush cron && ddev drush cron && ddev drush cron   # builds the search index, 500 posts a run
ddev drush cr
```

## Further reading

- [docs/PLAN.md](docs/PLAN.md): the plan, with what had to come over, measured.
- [docs/DISCOVERIES.md](docs/DISCOVERIES.md): what the work found, including what the test suite missed and a
  person caught.
- Recaps on drupal.org:
  [Part 1: Road Test](https://www.drupal.org/docs/develop/development-tools/ai-coding-tools-for-drupal-development/drupal-ai-learners-club-sessions/2026-10-16-%E2%80%94-part-1-hobobiker-rides-again-plan-it-test-it-migrate-it-%E2%80%94-road-test-having-claude) (Oct 16),
  [Part 2: The Last Ride](https://www.drupal.org/docs/develop/development-tools/ai-coding-tools-for-drupal-development/drupal-ai-learners-club-sessions/2026-10-23-%E2%80%94-part-2-hobobiker-rides-again-plan-it-test-it-migrate-it-%E2%80%94-the-last-ride-sending-a) (Oct 23), and
  [Part 3: The Long Haul](https://www.drupal.org/docs/develop/development-tools/ai-coding-tools-for-drupal-development/drupal-ai-learners-club-sessions/2026-10-30-%E2%80%94-part-3-hobobiker-rides-again-plan-it-test-it-migrate-it-%E2%80%94-the-long-haul-planning-and) (Oct 30).
- Slides: [Part 1](https://rfay.github.io/hobobiker-migration-slides/),
  [Part 2](https://rfay.github.io/hobobiker-migration-slides/part2.html),
  [Part 3](https://rfay.github.io/hobobiker-migration-slides/part3.html).
