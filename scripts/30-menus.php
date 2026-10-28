<?php

/**
 * @file
 * The original's main menu: Drupal 6 `menu-menu` (the navigation the theme shows on every page) becomes the main
 * menu, with every level, the same titles, order and nesting. Hidden items are kept, disabled. Links to nodes that
 * do not exist or are not published are hidden by Drupal's access check, as they were on the original.
 * The 8 primary links are not imported: the original's theme never showed them.
 *
 * Rerunnable: removes the main menu's previous links first.
 * Run: ddev drush php:script scripts/30-menus.php
 */

use Drupal\Core\Database\Database;
use Drupal\menu_link_content\Entity\MenuLinkContent;

Database::addConnectionInfo('d6', 'default', ['database' => 'd6'] + Database::getConnectionInfo('default')['default']);
$src = Database::getConnection('default', 'd6');
$log = fn($m) => print($m . "\n");

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$storage->delete($storage->loadByProperties(['menu_name' => 'main']));

// Parents before children (Drupal 6 keeps the path from the root in p1..p9).
$rows = $src->query("SELECT mlid, plid, weight, hidden, link_title, link_path FROM menu_links
  WHERE menu_name = 'menu-menu' ORDER BY depth, p1, p2, p3, p4, p5")->fetchAll(\PDO::FETCH_ASSOC);
$uuid = [];
foreach ($rows as $r) {
  $path = $r['link_path'] === '<front>' ? '/' : '/' . $r['link_path'];
  $link = MenuLinkContent::create([
    'title' => $r['link_title'], 'menu_name' => 'main', 'link' => ['uri' => 'internal:' . $path],
    'weight' => $r['weight'], 'enabled' => !$r['hidden'], 'expanded' => TRUE,
    'parent' => $r['plid'] ? 'menu_link_content:' . $uuid[$r['plid']] : '',
  ]);
  $link->save();
  $uuid[$r['mlid']] = $link->uuid();
}
$hidden = count(array_filter($rows, fn($r) => $r['hidden']));
$log(count($rows) . " main menu links ($hidden hidden, as on the original)");

// Drupal's own "Home" link would duplicate the original's.
\Drupal::service('plugin.manager.menu.link')->updateDefinition('standard.front_page', ['enabled' => FALSE]);
