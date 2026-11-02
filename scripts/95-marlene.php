<?php

/**
 * @file
 * Step 95: the new look, the marlene theme (web/themes/custom/marlene), as the default theme.
 *
 * Generated from core's Starterkit (no base theme to break under it), with design tokens after Marlene Wanberg's
 * UI Theme Explorer, light and dark schemes, a mobile-first disclosure menu and a banner of Randy and Nancy before
 * the Cordillera Blanca. Places its blocks as step 90 placed hobo_golden's: the main menu (whole tree),
 * breadcrumb, messages, tabs and the page content. hobo_golden stays installed: to go back,
 * drush config:set system.theme default hobo_golden.
 * Run: ddev drush php:script scripts/95-marlene.php
 */

use Drupal\block\Entity\Block;

require __DIR__ . '/lib.php';

\Drupal::service('theme_installer')->install(['marlene']);
\Drupal::configFactory()->getEditable('system.theme')->set('default', 'marlene')->save();
\Drupal::configFactory()->getEditable('marlene.settings')
  // The header shows the site name as a wordmark, not the old banner logo; the theme prints its own favicon set
  // (ico, svg, touch icon, manifest: templates/layout/html.html.twig), so Drupal's single favicon link is off.
  ->set('logo', ['use_default' => FALSE, 'path' => ''])
  ->set('favicon', ['use_default' => TRUE, 'path' => ''])
  ->set('features', ['logo' => FALSE, 'name' => FALSE, 'slogan' => FALSE, 'node_user_picture' => FALSE, 'comment_user_picture' => FALSE, 'favicon' => FALSE])
  ->save();

$blocks = [
  'marlene_main_menu' => ['system_menu_block:main', 'primary_menu', ['level' => 1, 'depth' => 0, 'expand_all_items' => TRUE]],
  'marlene_breadcrumbs' => ['system_breadcrumb_block', 'breadcrumb', []],
  'marlene_messages' => ['system_messages_block', 'highlighted', []],
  'marlene_local_tasks' => ['local_tasks_block', 'highlighted', []],
  'marlene_content' => ['system_main_block', 'content', []],
];
foreach ($blocks as $id => [$plugin, $region, $settings]) {
  $block = Block::load($id) ?? Block::create(['id' => $id, 'theme' => 'marlene', 'plugin' => $plugin]);
  $block->setRegion($region);
  $block->set('settings', ['id' => $plugin, 'label' => '', 'label_display' => '0', 'provider' => explode(':', $plugin)[0] === 'system_menu_block' ? 'system' : 'core'] + $settings + $block->get('settings'));
  $block->setWeight(0);
  $block->save();
}
// Only these: installing a theme copies the default theme's blocks.
$storage = \Drupal::entityTypeManager()->getStorage('block');
$copied = array_diff(\Drupal::entityQuery('block')->condition('theme', 'marlene')->execute(), array_keys($blocks));
$storage->delete($storage->loadMultiple($copied));
drupal_flush_all_caches();

$placed = \Drupal::entityQuery('block')->condition('theme', 'marlene')->count()->execute();
$icons = count(glob(\Drupal::service('extension.list.theme')->getPath('marlene') . '/images/favicon/*'));
printf("theme %s (default), favicon files %d, blocks placed %d (copied ones removed %d)\n", \Drupal::config('system.theme')->get('default'),
  $icons, $placed, count($copied));
