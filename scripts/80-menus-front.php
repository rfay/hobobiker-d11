<?php

/**
 * @file
 * Step 80: the main menu (D6 menu-menu, printed whole on every page) and the front page (the D6 Panel).
 *
 * Menu links keep their mlids as ids, their titles, paths, weights, parents and hidden state. The front page is
 * the hobobiker module's /trip_wrapped_up (D6 site_frontpage), with the search form from core's search module.
 * The menu block shows the whole tree, as the original's theme did (menu_tree_all_data). The theme step places it
 * in the site's own theme; until then it is the current theme's main menu block.
 * Updates links that exist, creates the rest, deletes main-menu links D6 does not have.
 * Run: ddev drush php:script scripts/80-menus-front.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

require __DIR__ . '/lib.php';

\Drupal::service('module_installer')->install(['search', 'hobobiker']);

// Parents first: order by the materialized path.
$rows = d6()->query("SELECT mlid, plid, link_path, link_title, weight, hidden, expanded FROM {menu_links}
  WHERE menu_name = 'menu-menu' ORDER BY p1, p2, p3, p4, p5")->fetchAllAssoc('mlid');
$created = $hidden = 0;
foreach ($rows as $row) {
  $link = MenuLinkContent::load($row->mlid);
  if (!$link) {
    $link = MenuLinkContent::create(['id' => $row->mlid])->enforceIsNew();
    $created++;
  }
  $path = $row->link_path === '<front>' ? '/' : '/' . $row->link_path;
  $link->set('menu_name', 'main');
  $link->set('title', $row->link_title);
  $link->set('link', ['uri' => 'internal:' . $path]);
  $link->set('weight', $row->weight);
  $link->set('enabled', !$row->hidden);
  $link->set('expanded', (bool) $row->expanded);
  $link->set('parent', $row->plid ? 'menu_link_content:' . MenuLinkContent::load($row->plid)->uuid() : '');
  $link->save();
  $hidden += $row->hidden ? 1 : 0;
}
$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$extra = array_diff(\Drupal::entityQuery('menu_link_content')->accessCheck(FALSE)->condition('menu_name', 'main')->execute(), array_keys($rows));
$storage->delete($storage->loadMultiple($extra));

// The whole tree on every page.
$theme = \Drupal::config('system.theme')->get('default');
$menu_blocks = 0;
foreach (\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['theme' => $theme, 'plugin' => 'system_menu_block:main']) as $block) {
  $settings = $block->get('settings');
  $block->set('settings', ['level' => 1, 'depth' => 0, 'expand_all_items' => TRUE] + $settings)->save();
  $menu_blocks++;
}

\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/trip_wrapped_up')->save();

printf("menu links from D6 %d (created %d, hidden %d), in main menu %d, extra deleted %d; menu blocks showing the whole tree %d (theme %s); front page %s\n",
  count($rows), $created, $hidden, \Drupal::entityQuery('menu_link_content')->accessCheck(FALSE)->condition('menu_name', 'main')->count()->execute(),
  count($extra), $menu_blocks, $theme, \Drupal::config('system.site')->get('page.front'));
