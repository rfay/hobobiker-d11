<?php

/**
 * @file
 * The theme: hobo_golden (web/themes/custom/hobo_golden), the original's theme ported: its page and node markup,
 * its CSS, images and menu script. Logo and favicon as the original's theme settings had them.
 *
 * Idempotent. Run: ddev drush php:script scripts/40-theme.php
 */

use Drupal\block\Entity\Block;

$theme = 'hobo_golden';
\Drupal::service('theme_installer')->install([$theme]);
\Drupal::configFactory()->getEditable('system.theme')->set('default', $theme)->save();
// The first pass used an Olivero subtheme; it is gone.
if (\Drupal::service('theme_handler')->themeExists('hobo')) {
  \Drupal::service('theme_installer')->uninstall(['hobo']);
}

// The original: logo sites/default/files/logo.jpg (the painted banner), favicon sites/default/files/favicon.ico.
\Drupal::configFactory()->getEditable("$theme.settings")
  ->set('logo', ['use_default' => FALSE, 'path' => 'public://logo.jpg'])
  ->set('favicon', ['use_default' => FALSE, 'path' => 'public://favicon.ico', 'mimetype' => 'image/vnd.microsoft.icon'])
  ->save();

// Exactly the blocks the original's page had; anything copied in when the theme was installed goes.
$blocks = [
  'main_menu' => ['system_menu_block:main', 'primary_menu', 0, ['level' => 1, 'depth' => 0, 'expand_all_items' => TRUE]],
  'breadcrumbs' => ['system_breadcrumb_block', 'breadcrumb', 0, []],
  'messages' => ['system_messages_block', 'highlighted', 0, []],
  'help' => ['help_block', 'help', 0, []],
  'page_title' => ['page_title_block', 'content', -10, []],
  'local_tasks' => ['local_tasks_block', 'content', -9, []],
  'content' => ['system_main_block', 'content', 0, []],
];
$keep = [];
foreach ($blocks as $id => [$plugin, $region, $weight, $settings]) {
  $keep[] = $blockId = "{$theme}_$id";
  $block = Block::load($blockId) ?? Block::create(['id' => $blockId, 'theme' => $theme, 'plugin' => $plugin]);
  $block->setRegion($region)->setWeight($weight)
    ->set('settings', ['id' => $plugin, 'label' => '', 'label_display' => '0', 'provider' => \Drupal::service('plugin.manager.block')->getDefinition($plugin)['provider']] + $settings)
    ->save();
}
$removed = 0;
foreach (\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['theme' => $theme]) as $block) {
  if (!in_array($block->id(), $keep, TRUE)) {
    $block->delete();
    $removed++;
  }
}
print "Theme: $theme (" . count($keep) . " blocks; $removed others removed)\n";
