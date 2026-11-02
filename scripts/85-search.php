<?php

/**
 * @file
 * Step 85: content search, as the original had it: /search/node for every visitor, over every node.
 *
 * Drupal 11.4 moved node search out of the search module into its submodule search_node (step 80 installs search,
 * for the front page's search form; without search_node there is no /search/node page). The roles get the D6
 * search permissions they had (D6 "search content" and "use advanced search", for anonymous and authenticated
 * users). D6's search_cron_limit (500) becomes the index's batch size, and the whole index is rebuilt here, so a
 * fresh site can be searched at once (cron keeps it current after).
 * Run: ddev drush php:script scripts/85-search.php
 */

use Drupal\search\Entity\SearchPage;
use Drupal\user\Entity\Role;

require __DIR__ . '/lib.php';

\Drupal::service('module_installer')->install(['search_node']);

// D6 permission names are Drupal 11's too; D6 role ids 1 and 2 are anonymous and authenticated.
$roles = ['anonymous user' => 'anonymous', 'authenticated user' => 'authenticated'];
$granted = 0;
foreach (d6()->query('SELECT r.name, p.perm FROM {role} r JOIN {permission} p ON p.rid = r.rid')->fetchAllKeyed() as $d6_role => $perms) {
  if (!isset($roles[$d6_role])) {
    continue;
  }
  $role = Role::load($roles[$d6_role]);
  foreach (array_intersect(array_map('trim', explode(',', $perms)), ['search content', 'use advanced search']) as $perm) {
    $role->grantPermission($perm);
    $granted++;
  }
  $role->save();
}

$limit = (int) unserialize(d6()->query("SELECT value FROM {variable} WHERE name = 'search_cron_limit'")->fetchField(), ['allowed_classes' => FALSE]);
\Drupal::configFactory()->getEditable('search.settings')->set('index.cron_limit', $limit ?: 100)->set('default_page', 'node_search')->save();

$plugin = SearchPage::load('node_search')->getPlugin();
$plugin->markForReindex();
do {
  $plugin->updateIndex();
  $status = $plugin->indexStatus();
} while ($status['remaining'] > 0);

printf("search pages %s, permissions granted %d, batch size %d, nodes indexed %d of %d (remaining %d)\n",
  implode(', ', array_keys(SearchPage::loadMultiple())), $granted, \Drupal::config('search.settings')->get('index.cron_limit'),
  $status['total'] - $status['remaining'], $status['total'], $status['remaining']);
