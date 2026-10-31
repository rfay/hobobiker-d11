# Discoveries

Findings about the source while planning (2026-10-30). Counts are from the `d6` schema.

- All stored PHP is the same call: `<?php print tripinfo_table('START','END'); ?>` in 15 nodes (formats 2 and 9).
  Nothing else in the content runs PHP. Node 4281 is in format 2 (PHP code) but contains no PHP.
- `[multisource:…]` (14 nodes, format 9) renders only its first KML/KMZ source; the tripinfo filter emits a
  `<?php tripinfo_map_add_js(); ?>` that the PHP filter then evaluates.
- `[hobophoto:]` is in 119 bodies (formats 10 and 11); no body or comment uses the flickr macros.
- 876 of 1657 aliases point at deleted nodes. Comment 27 is on deleted node 1198. Node 2307 has an empty type.
- D6 comment `status = 0` means published (169 of 447).
- 192 node bodies have format 0 (none set: the site default, Filtered HTML).
- Comments: the original stores 447 but shows none (its comment module is disabled). Drupal 11 first showed the
  168 published ones to visitors; the owner first chose to keep them visible, then (later on 2026-10-30) to match
  the original. All 446 are kept (stored tier); step 10 leaves the comment field off the node displays and gives no
  role `access comments`. The tests do not flag extra text, so they passed either way.
- Files: the image module's 24 files (fids 1-24, D6 path `images/...`) are missing on the original's disk too; they
  keep their file records and are counted as missing. The original's docroot has a symlink `files ->
  sites/default/files`, which old content's `/files/...` links rely on; step 60 reproduces it.
- Only 4 blog image references (of 101 rows) belong to current revisions with a file.
- tripinfo: on the first and last trip days the original still prints a previous/next link to an empty day
  ("‹  to ", " to ›", href tripinfo_item//START/END); reproduced. Bare /tripinfo is a PHP fatal error on the
  original (tripinfo_list() without arguments); the D11 route has defaults instead (declared).
- One [hobophoto:] photo (node 4300, sites/default/files/297234846.jpg) is missing on the original too (404).
- Image derivatives requested in parallel on first view answer 503 while another request generates them; step 75
  pre-generates all 428 photo derivatives.
- Dates: D6 core formatted dates with a fixed offset (date_default_timezone -21600, UTC-6 all year), not
  America/Denver; the Drupal 11 site uses Etc/GMT+6 so "Posted on" dates match (node 1311: February 3rd).
- The front page's "Big Trip Stats" (D6 block 14) was PHP too; a hobobiker block plugin computes the same figures.
  Its "Longest day" cell used a short open tag (<? ... ?>) that never ran, so it shows only "km"; reproduced.
- Installing a theme copies the previous default theme's blocks; step 90 removes them.
- Audio: D6 recorded some mp3 sizes capped at 8,388,607 bytes and showed "8 MB"; Drupal 11 recomputes a file's
  size from disk on save, so D6's figure is kept in its own field for display. The baseline crawl fetched a download
  link, so D6's download counter moved during the crawl (8515 on the node page, 8516 on /audio); the stored figure
  is kept.
- D6 Views date filters read dates in the server's PHP timezone (America/Denver), not D6's own UTC-6 offset; the D6
  date argument rejected years outside its year_range around the current year (default -3:+3), which is why every
  triplog/START--END page lists nothing today.
- D6 linked a path by its newest alias, except on a page requested at another alias of it (lookup cache); the
  module's outbound path processor reproduces that.
- Migrate API comparison (scripts/alt/30-terms-migrate.php): core's d6_taxonomy_term pattern skipped term 31,
  whose parent has a higher tid, with only a notice and "0 failed" (the parent stub had no vocabulary); fixed with a
  stub default. The comparison found a bug in step 30 (setDescription() ignores a format; 30 descriptions had none),
  now fixed: both give identical terms. migrate_drupal is deprecated in Drupal 11.

## The lightbox (found by a person, after every tier passed)

- On the original, clicking a `[hobophoto:]` photo or the blog portrait opens it in the Lightbox2 module's overlay
  (`sites/all/modules/lightbox/js/lightbox.js`, 6.x-1.12, added to every page). Drupal 11 printed the same
  `rel="lightbox[group1][caption]"` links but no script, so a click opened the bare image. No tier clicks, so all
  passed.
- Fix: the original's `lightbox.js`, CSS and images, copied unchanged into the module
  (`lib/lightbox2-6.x-1.12`), with its settings as the original printed them (library `hobobiker/lightbox2`, on
  every page as on D6). An adapter (`js/lightbox2.js`) covers what Drupal 11 changed: `jQuery.noConflict()` (the
  script uses a global `$`), no `$.browser`, `Drupal.settings` is `drupalSettings`, behaviors are `{attach}`.
  The script posts each caption to `system/lightbox2/filter-xss`; that route is ported with D6's allowed tags.
- Checked in a browser on node 1335 and node 1313: the same overlay, image and caption as the original, no
  script errors.
- Step 90 turns off CSS/JS aggregation, so the original's scripts are served as files.

## Favicon (found by a person)

- The original's pages link `/sites/default/files/favicon.ico` (its theme setting); its docroot `/favicon.ico` is a
  different icon, served to browsers that ask for it directly. Drupal 11 linked core's icon. Step 90 sets the theme
  setting to `public://favicon.ico` (the same file, in the copied files), and `web/favicon.ico` is a copy of the
  original's docroot icon. Both match the original byte for byte; the tag is `rel="icon"` (Drupal 11's markup)
  instead of `rel="shortcut icon"`. No tier checks the favicon.

## Pager and edit forms (found by a person)

- Pager: Drupal 11's pager markup (`nav.pager`, `pager__item`) is not styled by the original's CSS, so it showed as a
  bulleted list. hobo_golden's `pager.html.twig` prints D6's `theme_pager()` list instead: links by the page's alias
  (D6 `url()`), no `page` parameter on page 1, `class="active"` on every link, and no trailing ellipsis when the
  window ends one page before the last (D6's `$i < $pager_max`; Drupal 11 prints one).
- A page past the end: D6 Views showed page 1, D6 core's `pager_query()` (the blog module's pages) the last page
  with its content; Drupal 11 shows an empty page with the last page current. The module's pager plugin
  `hobobiker_full` (option `out_of_range`: first/last) reproduces each; step 65 uses it for every full pager.
- Edit forms: step 10 configured how nodes display but saved no form displays, so node/N/edit showed only the base
  fields (no body, tags or CCK fields). Step 10 now puts every field on the edit form with the default widget.
  No tier logs in, so none saw it.

## Found by comparing the two sites page by page (not by the test suite)

Scripts run over every baseline page on both sites (2,455): text on Drupal 11 that is nowhere on the original's
page, breadcrumbs, `<title>`, head feed links, pager markup; and full-page screenshots of every 20th page compared
pixel by pixel. The suite checks that the original's content is present, so it does not see content a target adds,
styling, or behavior.

- Submitted line ("By … - Posted on …"): D6 showed it where the theme setting was on (audio, blog, forum, story) or
  absent for a type D6 still had (poll); acidfree and image (modules off) had none.
- Tags: D6 ordered them case-insensitively (MySQL collation).
- Blog link for an anonymous author: "'s blog" (D6 used the empty account name).
- Trip tables: D6 compared its stored `YYYY-MM-DDT00:00:00` with the bare end date as strings, so the end date's day
  was never listed; reproduced.
- Breadcrumbs: D6's came from the router path and its navigation menu, whose hidden links (now the `d6-navigation`
  menu, step 80) gave five pages "Home » Inuvik to Ushuaia". Alias segments, the tripinfo callbacks and term pages
  (Views, no parents) gave none. Module builder `hobobiker.d6_breadcrumb`.
- Read-more: titled "Read the whole post"; a teaser not ending in `</p>` gets it as an appended `div`.
- Page titles: the original's site name and slogan (step 90); the front page is "name | slogan".
- Head feed links: "RSS - blogs" and "RSS - NAME's blog", as the blog module set them.
- "Skip to main content": Drupal 11's link removed (`html.html.twig`).
- Main menu: the standard profile's own Home link turned off (step 80); D6's `system-menus.css` and the other
  module stylesheets the original aggregated (user, CCK, CTools, date, filefield, fieldgroup, Panels, Views, audio)
  added to the theme. Menu boxes now measure the same as the original's.
- HTML corrector: D6's `_filter_htmlcorrector()` ported (`hobobiker_htmlcorrector`, in place of core's in 8 formats,
  step 75). It read `<br/>` as an element `br/` and closed it with `</br/>`, which browsers show as a line break: 69
  bodies had more vertical space on the original.
- Deliberate changes (owner's decisions, declared in expected-differences.json):
  - Node 3953's teaser ends inside an unclosed `<!--`; on the original, browsers hid everything after it on four
    listing pages. The corrector closes it, so those posts show.
  - Audio: an HTML5 `<audio>` player (290x24) where the original embedded a Flash player.
- Not reproduced, by the owner's decision: the original's other scripts (simplemenu, thickbox, Panels, preload),
  its `<head>` meta tags (nodewords) and Drupal 11's own; audio download and play counts, which keep changing on the
  running original.
- CCK field order: D6 placed fields around the body (weight 0) by weight, then label case-insensitively, a field of
  the body's weight after it; the wp fields had been put before the body. Step 10 now derives the weights so.
- Screenshot sample (every 20th page, 123) after these fixes, on a site rebuilt from nothing: 77 pages identical
  pixel for pixel, 122 within 0.5%; the one large difference is a listing with node 3953's teaser (the deliberate
  change), the small ones the audio player (HTML5 instead of Flash).
