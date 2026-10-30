<?php

/**
 * @file
 * Step 70: the Drupal 6 URL aliases, with their ids, for every path that exists on Drupal 11.
 *
 * An alias is created only when its system path is a route here (Drupal's path validator, without access
 * checks, so unpublished nodes keep their aliases and answer 403). The rest are counted by reason: aliases of
 * deleted nodes and terms (dropped, owner's decision), and paths Drupal 11 has no route for (node and term
 * feeds, gsitemap, pathauto admin paths). Paths made by later steps (Views pages such as blog/1 and podcast)
 * get their aliases when the views step runs before this one.
 * D6 picked the newest alias (highest pid) for a path's links; keeping pid as the alias id keeps that choice.
 * Updates aliases that exist, creates the rest, deletes aliases D6 does not have.
 * Run: ddev drush php:script scripts/70-aliases.php
 */

use Drupal\path_alias\Entity\PathAlias;

require __DIR__ . '/lib.php';

$validator = \Drupal::service('path.validator');
$rows = d6()->query('SELECT pid, src, dst, language FROM {url_alias} ORDER BY pid')->fetchAll();
$nids = array_flip(d6()->query('SELECT nid FROM {node}')->fetchCol());
$tids = array_flip(d6()->query('SELECT tid FROM {term_data}')->fetchCol());

$kept = [];
$created = 0;
$skipped = ['deleted node' => 0, 'deleted term' => 0, 'no route' => 0];
$no_route = [];
foreach ($rows as $row) {
  if (preg_match('@^node/(\d+)(/|$)@', $row->src, $m) && !isset($nids[$m[1]])) {
    $skipped['deleted node']++;
    continue;
  }
  if (preg_match('@^taxonomy/term/(\d+)(/|$)@', $row->src, $m) && !isset($tids[$m[1]])) {
    $skipped['deleted term']++;
    continue;
  }
  if (!$validator->getUrlIfValidWithoutAccessCheck($row->src)) {
    $skipped['no route']++;
    $kind = preg_replace(['@\d+@', '@/[a-z_]+$@'], ['N', '/X'], $row->src);
    $no_route[$kind] = ($no_route[$kind] ?? 0) + 1;
    continue;
  }
  $alias = PathAlias::load($row->pid);
  if (!$alias) {
    $alias = PathAlias::create(['id' => $row->pid])->enforceIsNew();
    $created++;
  }
  $alias->setPath('/' . $row->src);
  $alias->setAlias('/' . $row->dst);
  $alias->set('langcode', $row->language ?: 'und');
  $alias->save();
  $kept[] = $row->pid;
}

$storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$extra = array_diff(\Drupal::entityQuery('path_alias')->accessCheck(FALSE)->execute(), $kept);
$storage->delete($storage->loadMultiple($extra));

$by_kind = [];
foreach ($storage->loadMultiple($kept) as $alias) {
  $kind = explode('/', ltrim($alias->getPath(), '/'))[0];
  $by_kind[$kind] = ($by_kind[$kind] ?? 0) + 1;
}
ksort($no_route);
printf("aliases from D6 %d: created %d, on site %d (%s); skipped: %s; extra deleted %d\n", count($rows), $created,
  count($kept), implode(', ', array_map(fn($k, $n) => "$k $n", array_keys($by_kind), $by_kind)),
  implode(', ', array_map(fn($k, $n) => "$k $n", array_keys($skipped), $skipped)), count($extra));
echo 'no route here: ' . implode(', ', array_map(fn($k, $n) => "$k $n", array_keys($no_route), $no_route)) . "\n";
