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
- Comments: the original stores 447 but shows none (its comment module is disabled). On Drupal 11 the 168
  published comments are visible to visitors. Owner's decision (2026-10-30): keep them visible. The tests do not
  flag extra text, so no expected-differences entry is involved.
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
