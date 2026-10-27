<?php

/**
 * @file
 * The home page: the original's Panels page (page_manager "trip_wrapped_up", its site_frontpage), rebuilt from its
 * panes in the schema `d6` as config for the hobobiker module's /trip_wrapped_up route.
 *
 * Panes: node teasers, the search form, and custom block 14 "Statistics", whose PHP is replaced by the HTML it
 * rendered (scripts/data/statistics-block.html; the site is frozen, PART3-PLAN.md D6). The language switcher pane
 * (one language) showed nothing and is left out. Idempotent.
 * Run: ddev drush php:script scripts/60-front-page.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Database\Database;

Database::addConnectionInfo('d6', 'default', ['database' => 'd6'] + Database::getConnectionInfo('default')['default']);
$src = Database::getConnection('default', 'd6');
$log = fn($m) => print($m . "\n");

// The statistics block, as a reusable custom block.
$info = 'Statistics';
$existing = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['info' => $info]);
$block = $existing ? reset($existing) : BlockContent::create(['type' => 'basic', 'info' => $info, 'reusable' => TRUE]);
$block->set('body', ['value' => file_get_contents(__DIR__ . '/data/statistics-block.html'), 'format' => 'full_html_no_restrictions'])->save();

// D6 threecol_25_50_25_stacked regions => core layout_threecol_25_50_25 regions.
$regions = ['top' => 'top', 'left' => 'first', 'middle' => 'second', 'right' => 'third', 'bottom' => 'bottom'];
$handler = unserialize($src->query("SELECT conf FROM page_manager_handlers WHERE task = 'page' AND subtask = 'trip_wrapped_up'")->fetchField());
$did = $handler['did'];
$panes = [];
foreach ($src->query('SELECT panel, type, subtype, configuration FROM panels_pane WHERE did = :did AND shown = 1 ORDER BY panel, position', [':did' => $did]) as $p) {
  $conf = unserialize($p->configuration);
  $pane = match (TRUE) {
    $p->type === 'node' => ['type' => 'node', 'id' => (string) $conf['nid'], 'title' => ''],
    $p->subtype === 'search-0' => ['type' => 'search', 'id' => '', 'title' => 'Search'],
    $p->subtype === 'block-14' => ['type' => 'block_content', 'id' => $info, 'title' => 'Statistics'],
    default => NULL,
  };
  if ($pane) {
    $panes[] = ['region' => $regions[$p->panel]] + $pane;
  }
  else {
    $log("pane $p->type/$p->subtype left out");
  }
}
\Drupal::configFactory()->getEditable('hobobiker.front_page')->set('panes', $panes)->save();
\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/trip_wrapped_up')->save();
$log(count($panes) . ' front page panes; front page /trip_wrapped_up');
