<?php

/**
 * @file
 * Step 90: the site's own theme, hobo_golden (web/themes/custom/hobo_golden), ported from golden_hour + hobo_golden.
 *
 * Installs it as the default theme with the original's logo (sites/default/files/logo.jpg) and places its blocks:
 * the main menu (whole tree), breadcrumb, messages, tabs and the page content. The original showed no site name,
 * slogan, mission or search box in the page chrome. Also the site timezone: D6 formatted dates at a fixed UTC-6
 * (date_default_timezone -21600, no daylight saving), so Etc/GMT+6 (the "Posted on" dates).
 * Run: ddev drush php:script scripts/90-theme.php
 */

use Drupal\block\Entity\Block;

require __DIR__ . '/lib.php';

\Drupal::service('theme_installer')->install(['hobo_golden']);
\Drupal::configFactory()->getEditable('system.theme')->set('default', 'hobo_golden')->save();
\Drupal::configFactory()->getEditable('hobo_golden.settings')
  ->set('logo', ['use_default' => FALSE, 'path' => 'public://logo.jpg'])
  // The original's shortcut icon setting (its /favicon.ico is a different icon, web/favicon.ico here).
  ->set('favicon', ['use_default' => FALSE, 'path' => 'public://favicon.ico', 'mimetype' => 'image/x-icon'])
  ->set('features', ['logo' => TRUE, 'name' => FALSE, 'slogan' => FALSE, 'node_user_picture' => FALSE, 'comment_user_picture' => FALSE, 'favicon' => TRUE])
  ->save();
\Drupal::configFactory()->getEditable('system.date')->set('timezone.default', 'Etc/GMT+6')->set('timezone.user.configurable', FALSE)->save();
// The original's site name and slogan (page titles: "Title | name", or "name | slogan" on a page with no title).
$site = fn($name) => unserialize(d6()->query('SELECT value FROM {variable} WHERE name = :name', [':name' => $name])->fetchField(), ['allowed_classes' => FALSE]);
\Drupal::configFactory()->getEditable('system.site')->set('name', $site('site_name'))->set('slogan', $site('site_slogan'))->save();
// CSS and JS served file by file (no aggregation), so the original's scripts and styles can be read and debugged.
\Drupal::configFactory()->getEditable('system.performance')->set('css.preprocess', FALSE)->set('js.preprocess', FALSE)->save();

$blocks = [
  'hobo_golden_main_menu' => ['system_menu_block:main', 'primary_menu', ['level' => 1, 'depth' => 0, 'expand_all_items' => TRUE]],
  'hobo_golden_breadcrumbs' => ['system_breadcrumb_block', 'breadcrumb', []],
  'hobo_golden_messages' => ['system_messages_block', 'highlighted', []],
  'hobo_golden_local_tasks' => ['local_tasks_block', 'highlighted', []],
  'hobo_golden_content' => ['system_main_block', 'content', []],
];
foreach ($blocks as $id => [$plugin, $region, $settings]) {
  $block = Block::load($id) ?? Block::create(['id' => $id, 'theme' => 'hobo_golden', 'plugin' => $plugin]);
  $block->setRegion($region);
  $block->set('settings', ['id' => $plugin, 'label' => '', 'label_display' => '0', 'provider' => explode(':', $plugin)[0] === 'system_menu_block' ? 'system' : 'core'] + $settings + $block->get('settings'));
  $block->setWeight(0);
  $block->save();
}
// Only these: installing a theme copies the previous default theme's blocks (branding, "Powered by", ...).
$storage = \Drupal::entityTypeManager()->getStorage('block');
$copied = array_diff(\Drupal::entityQuery('block')->condition('theme', 'hobo_golden')->execute(), array_keys($blocks));
$storage->delete($storage->loadMultiple($copied));
drupal_flush_all_caches();

$placed = \Drupal::entityQuery('block')->condition('theme', 'hobo_golden')->count()->execute();
printf("theme %s (default), logo %s, favicon %s, blocks placed %d (copied ones removed %d), timezone %s, aggregation %s\n", \Drupal::config('system.theme')->get('default'),
  theme_get_setting('logo.url', 'hobo_golden'), theme_get_setting('favicon.url', 'hobo_golden'), $placed, count($copied), \Drupal::config('system.date')->get('timezone.default'),
  \Drupal::config('system.performance')->get('js.preprocess') ? 'on' : 'off');
