<?php

/**
 * @file
 * Step 80: the main menu (D6 menu-menu, printed whole on every page), the D6 navigation menu's own links (hidden;
 * they gave some pages their breadcrumb) and the front page (the D6 Panel).
 *
 * Menu links keep their mlids as ids, their titles, paths, weights, parents and hidden state. The front page is
 * the hobobiker module's /trip_wrapped_up (D6 site_frontpage), with the search form from core's search module.
 * The menu block shows the whole tree, as the original's theme did (menu_tree_all_data). The theme step places it
 * in the site's own theme; until then it is the current theme's main menu block. The standard profile's own Home
 * link is turned off: menu-menu has its own.
 * Updates links that exist, creates the rest, deletes links D6 does not have.
 * Run: ddev drush php:script scripts/80-menus-front.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

require __DIR__ . '/lib.php';

\Drupal::service('module_installer')->install(['search', 'hobobiker']);

/**
 * Copies a D6 menu's links (matching $where) into a Drupal 11 menu; returns [links, created, hidden, deleted].
 */
function hobo_menu_links(string $d6_menu, string $where, string $menu): array {
  // Parents first: order by the materialized path.
  $rows = d6()->query("SELECT mlid, plid, link_path, link_title, weight, hidden, expanded FROM {menu_links}
    WHERE menu_name = :menu AND $where ORDER BY p1, p2, p3, p4, p5", [':menu' => $d6_menu])->fetchAllAssoc('mlid');
  $created = $hidden = 0;
  foreach ($rows as $row) {
    $link = MenuLinkContent::load($row->mlid);
    if (!$link) {
      $link = MenuLinkContent::create(['id' => $row->mlid])->enforceIsNew();
      $created++;
    }
    $path = $row->link_path === '<front>' ? '/' : '/' . $row->link_path;
    $link->set('menu_name', $menu);
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
  $extra = array_diff(\Drupal::entityQuery('menu_link_content')->accessCheck(FALSE)->condition('menu_name', $menu)->execute(), array_keys($rows));
  $storage->delete($storage->loadMultiple($extra));
  return [count($rows), $created, $hidden, count($extra)];
}

[$main, $main_created, $main_hidden, $main_deleted] = hobo_menu_links('menu-menu', '1 = 1', 'main');
\Drupal::service('plugin.manager.menu.link')->updateDefinition('standard.front_page', ['enabled' => FALSE]);

// The navigation menu's links made by hand (not the modules' own), without the admin pages.
if (!Menu::load('d6-navigation')) {
  Menu::create(['id' => 'd6-navigation', 'label' => 'D6 navigation', 'description' => "The original's navigation menu links: breadcrumbs only."])->save();
}
[$navigation] = hobo_menu_links('navigation', "module = 'menu' AND link_path NOT LIKE 'admin/%'", 'd6-navigation');

// The whole tree on every page.
$theme = \Drupal::config('system.theme')->get('default');
$menu_blocks = 0;
foreach (\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['theme' => $theme, 'plugin' => 'system_menu_block:main']) as $block) {
  $settings = $block->get('settings');
  $block->set('settings', ['level' => 1, 'depth' => 0, 'expand_all_items' => TRUE] + $settings)->save();
  $menu_blocks++;
}

\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/trip_wrapped_up')->save();

printf("main menu links from D6 %d (created %d, hidden %d, extra deleted %d), standard Home link %s; D6 navigation links %d; menu blocks showing the whole tree %d (theme %s); front page %s\n",
  $main, $main_created, $main_hidden, $main_deleted,
  \Drupal::service('plugin.manager.menu.link')->getDefinition('standard.front_page')['enabled'] ? 'on' : 'off', $navigation,
  $menu_blocks, $theme, \Drupal::config('system.site')->get('page.front'));
