<?php

/**
 * @file
 * The views' header texts without hobobiker.com as a hostname, and the e-mail list gone (owner's decision,
 * 2026-11-07). The companion of scripts/remove-hostname.php, for configuration.
 *
 * - /latest_stories: no invitation to the e-mail list.
 * - /espanol: the e-mail address (written by an inline script) becomes the contact page; the Babelfish line goes.
 * - /podcast: no "type in http://hobobiker.com/podcast/feed" (that address never was a feed).
 * - /mexico_route: a commented-out map token that named the domain goes.
 * - /subscribe (node 206, unpublished: "Subscribe to the Hobobiker.com Email List") is deleted, with its aliases.
 * Each edit must match once; rerunnable (a view already edited is skipped).
 * Run: ddev drush php:script scripts/remove-hostname-config.php
 */

use Drupal\node\Entity\Node;
use Drupal\views\Entity\View;

$edits = [
  'hobo_latest_stories' => [
    ['~Are you on our mailing list\? For a note from us once a month, <a href="/subscribe">subscribe here</a>\.\s*~', ''],
  ],
  'hobo_espanol_view' => [
    ['~Mándanos en <script\b.*?</script><noscript>.*?</noscript>~su', 'Mándanos un mensaje en nuestra <a href="/contact">página de contacto</a>.'],
    ['~\s*<br/><br/>\s*Puedes ver una traducción de todo el website <a [^>]*babelfish[^>]*>por haz click aquí</a>~u', ''],
  ],
  'hobo_podcast' => [
    ['~go to the Advanced menu and select "Subscribe to Podcast"\. Type in http://hobobiker\.com/podcast/feed - or you can just search for "hobobiker" in the Itunes Store~', 'search for "hobobiker" in the iTunes Store'],
  ],
  'hobo_mexico_route' => [
    ['~<!--(?:(?!-->).)*hobobiker\.com(?:(?!-->).)*-->\s*~s', ''],
  ],
];

$changed = 0;
foreach ($edits as $id => $replacements) {
  $view = View::load($id);
  $display = &$view->getDisplay('default');
  $value = &$display['display_options']['header']['area']['content']['value'];
  if (!preg_match('~hobobiker\.com|babelfish|/subscribe~i', $value)) {
    continue;
  }
  foreach ($replacements as [$pattern, $with]) {
    $value = preg_replace($pattern, $with, $value, -1, $n);
    if ($n !== 1) {
      throw new \RuntimeException("$id: expected one match for $pattern, found $n");
    }
  }
  $view->save();
  $changed++;
  unset($display, $value);
}

$deleted = 0;
if ($node = Node::load(206)) {
  $node->delete();
  $deleted = 1;
}

$left = \Drupal::database()->query('SELECT name FROM {config} WHERE data LIKE :p OR data LIKE :q', [':p' => '%//hobobiker.com%', ':q' => '%www.hobobiker.com%'])->fetchCol();
printf("views edited %d; subscribe page deleted %d; config still naming the hostname: %s\n", $changed, $deleted, $left ? implode(', ', $left) : 'none');
