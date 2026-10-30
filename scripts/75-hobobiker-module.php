<?php

/**
 * @file
 * Step 75: the hobobiker module (web/modules/custom/hobobiker) and its filters in the formats that used them.
 *
 * The D6 site's render-time behavior: [hobophoto:] (D6 formats 10 and 11, hobobiker_filter), the trip map
 * [multisource:] (format 9, tripinfo), and [tripinfo_table:] in place of the stored PHP (formats 2 and 9). The
 * module's routes are tripinfo/START/END and tripinfo_item/DATE/START/END. Weights are D6's.
 * Run: ddev drush php:script scripts/75-hobobiker-module.php
 */

use Drupal\filter\Entity\FilterFormat;
use Drupal\image\Entity\ImageStyle;

\Drupal::service('module_installer')->install(['hobobiker']);

$filters = [
  'php_code' => ['tripinfo_table' => 0],
  'tripinfo_google_map' => ['multisource' => -10, 'tripinfo_table' => -9],
  // D6 ran same-weight filters by module name: the line break converter (filter) before hobobiker_filter.
  'flickr_full_html_linebreaks' => ['hobophoto' => 10],
  'flickr_full_html' => ['hobophoto' => 10],
];
$enabled = 0;
foreach ($filters as $format_id => $add) {
  $format = FilterFormat::load($format_id);
  foreach ($add as $filter_id => $weight) {
    $format->setFilterConfig($filter_id, ['status' => TRUE, 'weight' => $weight]);
    $enabled++;
  }
  $format->save();
}

// Generate every photo's derivative now: on first view, parallel requests for one derivative get a 503 while
// another request holds the lock.
$derivatives = $generated = $missing = 0;
foreach (\Drupal::database()->query('SELECT body_value FROM {node__body} WHERE body_value LIKE :p', [':p' => '%[hobophoto:%'])->fetchCol() as $body) {
  preg_match_all('/\[hobophoto:(.+?)\]/', $body, $macros);
  foreach ($macros[1] as $macro) {
    $config = \Drupal\hobobiker\Plugin\Filter\HobophotoFilter::splitConfig($macro);
    $style = ImageStyle::load($config['orientation'] === 'portrait' ? '180x240' : '240x180');
    $source = 'public://' . preg_replace('@^sites/default/files/@', '', $config['path']);
    $derivative = $style->buildUri($source);
    $derivatives++;
    if (!file_exists($source)) {
      $missing++;
    }
    elseif (!file_exists($derivative)) {
      $generated += $style->createDerivative($source, $derivative) ? 1 : 0;
    }
  }
}
printf("photo macros %d: derivatives generated now %d, source missing %d\n", $derivatives, $generated, $missing);

$count = fn(string $pattern) => \Drupal::database()->query('SELECT COUNT(*) FROM {node__body} WHERE body_value LIKE :p', [':p' => $pattern])->fetchField();
printf("module hobobiker %s; filters enabled %d in %d formats; bodies using hobophoto %d, multisource %d, tripinfo_table %d; routes %d\n",
  \Drupal::moduleHandler()->moduleExists('hobobiker') ? 'on' : 'off', $enabled, count($filters),
  $count('%[hobophoto:%'), $count('%[multisource:%'), $count('%[tripinfo_table:%'),
  count(\Drupal::service('router.route_provider')->getRoutesByNames(['hobobiker.tripinfo', 'hobobiker.tripinfo_item'])));
