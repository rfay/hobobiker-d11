# Plan: rebuilding hobobiker.com on Drupal 11

The site is built from an empty Drupal 11.4 install by numbered, rerunnable steps in `scripts/`, run in order by
`scripts/run-all.sh`. **Method: plain PHP scripts** (`ddev drush php:script`). They read the `d6` schema through a
second connection and create entities with their original ids (nid, vid, cid, tid, fid, uid). The source is a
non-standard Drupal 6 site (custom modules, a CCK triplog, gone modules: audio, acidfree, image, panels), it is one
cutover, and scripts are quick to write and fix live. Each script first deletes what an earlier run created, then
prints counts.

## What has to come over (measured)

| | Source (d6) | Test baseline |
|---|---|---|
| Nodes | 1296 (1255 published), 12 types incl. 1 with no type (nid 2307) | stored: 1296 nodes |
| Comments | 447 (169 published; D6 `status=0` means published); cid 27 is on deleted node 1198 | stored: 447 |
| Terms | 82 in 4 vocabularies (Adventures 30, Image Galleries 1, Access Level 2, Topics 49); 1044 node-term links | stored: 82 |
| Aliases | 1657; **876** point at deleted nodes | semantic: 2455 pages (1601 node paths, 854 routes) |
| Files | 44 `files` rows; 1793 assets on disk (1.1 GB, already copied to `web/sites/default/files`) | 1793 assets |
| Render-time content | 119 bodies with `[hobophoto:]`, 14 with `[multisource:]`, 15 with `<?php print tripinfo_table('A','B'); ?>`, 0 with flickr macros | |

## The steps, as built (`scripts/run-all.sh`; from nothing: `scripts/00-reset.sh && scripts/run-all.sh`, about 70 s)

| Step | Creates | Prints (last run) |
|---|---|---|
| `00-reset.sh` | Fresh install, `d6` reloaded | d6 1296 nodes, 447 comments |
| `10-structure.php` | comment + `hobobiker` modules; 11 content types (D6 names); body, terms, comment fields; triplog/wp CCK fields with D6 labels, order and date formats; audio tag/file fields; poll fields; 10 text formats (one per D6 format); 4 image styles (the imagecache presets) | 11 types, 66 field instances, 4 vocabularies |
| `20-users.php` | 4 users, uids kept, no hash or email copied | 4 users, 3 roles |
| `30-terms.php` | 82 terms, tids, parents (second pass) | per vocabulary 30/1/2/49 |
| `40-nodes.php` | 1296 nodes (nid, vid), current revision; teaser as summary; PHP rewritten to `[tripinfo_table:]`; node 2307 -> page, "(no title)"; poll choices | 1255 published, 15 rewritten, 0 PHP left |
| `50-comments.php` | 446 comments, cids, threads, status inverted; cid 27 dropped | 168 published, 34 replies |
| `60-files.php` | 44 files (fids); `/files` symlink; blog photos; audio files, tags, counters, D6 sizes | 24 missing on disk (as on D6) |
| `65-views.php` | The D6 Views translated from their stored definitions (20), blog module pages, audio page, feeds; date-range argument | 20 made, 3 skipped (named) |
| `70-aliases.php` | Aliases whose path routes here (ids kept) | 503 made, 923 of deleted nodes dropped |
| `75-hobobiker-module.php` | The module's filters in the formats D6 used them in; photo derivatives | 428 photo macros |
| `80-menus-front.php` | Main menu (77 links, mlids); front page `/trip_wrapped_up` | 77 links |
| `90-theme.php` | `hobo_golden` theme (port of golden_hour + hobo_golden + D6 module CSS); timezone UTC-6 as D6 | 5 blocks |
| `99-validate.php` | Nothing: `validate()` on every entity plus reference checks | 0 violations |
| `alt/30-terms-migrate.php` | Not in run-all: step 30 with the Migrate API, for comparison (identical result) | 82 imported |

Result on a site built from nothing: stored 1445/1445, semantic 2457/2457, layout 2450/2450, access 81/81, assets
1781/1781, with 20 declared `drupal11` differences.

## How each Drupal 6 behavior is reproduced

- **`hobobiker_filter` (`[hobophoto:path=…,orientation=…,caption=…]`)** becomes a D11 filter plugin
  `hobophoto` in the `hobobiker` module. The stored text stays as is. At render it emits the original's markup:
  `div.flickrfloat > div.flickr-img-wrapper > a[rel=lightbox][href=/path] > div > img` from the image style
  `240x180` (or `180x240` for portrait), with the caption as alt/title and as a line. The tests match images by
  file name, so the `styles/…` URL instead of `imagecache/…` is fine.
- **The PHP filter (`tripinfo_table`)**: no PHP runs. `40-nodes.php` replaces each `<?php print
  tripinfo_table('A','B'); ?>` with the token `[tripinfo_table:A,B]`, which a filter plugin renders with the
  same table from the triplog fields. Format 2 (PHP code) maps to Full HTML.
- **`tripinfo` routes**: `/tripinfo/{start}/{end}` and `/tripinfo_item/{date}/{start}/{end}` as module routes
  (controller ported from the D6 queries, now an entity query on triplog nodes), with the Leaflet/KML map from
  the module's `lib/` (copied).
- **`[multisource:…]`** (format 9): a filter plugin that renders the same map div and "Explore in Google Earth"
  link, attaching the Leaflet library instead of the PHP-evaluated `drupal_add_js`.
- **Line breaks / URL filters**: formats 5 and 10 get `filter_autop` and `filter_url`, like the D6 filters.
- **Gone modules**: audio, acidfree, image, forum and poll become plain content types holding their body (and
  the audio file); poll choices are written into the body only if a page test needs them (declared).

## Declared differences (`expected-differences.json`, `"target": "drupal11"`)

- The 15 rewritten bodies: the three stored lines `<?php`, `print tripinfo_table('A','B');`, `?>` each,
  exact per node (owner's rule: no stored PHP).
- Comment 27 on deleted node 1198 (owner allowed dropping it).
- Anything the theme cannot match, added one at a time, exact, with a reason.

## Showing the data is valid Drupal 11 data

`99-validate.php` loads every node, comment, term, user, file and alias and runs `$entity->validate()` (field
constraints, references, formats, required values), plus referential checks (every comment's node and parent exist,
every term reference and file reference resolves, every alias's target exists). It prints counts per entity type
and the number of violations, which should be 0. `drush config:status` and `drush updatedb:status` are clean.

## What the tests should say after each step

- After 40: **access** passes (published 200 at `node/N`, the 41 unpublished 403). **stored** nodes and terms pass
  except the 15 declared PHP bodies; comments fail (none yet).
- After 50: **stored** passes fully with the 2 kinds of declared entries. *(Target: here by about minute 12.)*
- After 70: semantic node pages answer at their aliases; lines pass where the theme does not matter.
- After 80–90: menus, front page, listing and taxonomy routes, then layout. Remaining failures are counted by
  category and either fixed in the theme or declared.

## Risks

- **Time.** 25 minutes is enough for steps 10–50 and part of 60–90. The views/theme steps (most of the 854 routes)
  are the long tail and may run past the session.
- **Comment threading and ids**: inserting with fixed cids and D6 `thread` values must not be renumbered by
  Drupal; checked by the stored tier (cid → nid).
- **Format mapping**: a body in a format whose filters differ (e.g. no `filter_autop`) loses line breaks on the
  page; the stored tier will not see it, semantic will.
- **Access Level vocabulary** (term_access): if it hid content from anonymous users on the original, the access
  tier will show it; not yet confirmed.
- **Dead aliases**: 876 dropped by owner decision; any one that the baseline still links to shows up as a link
  failure and gets examined before declaring.
- **Data rules**: user emails and password hashes are never copied or printed; checks use counts only.
