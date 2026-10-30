<?php

/**
 * @file
 * Step 30: the 82 Drupal 6 terms, with their tids, vocabularies, names, descriptions, weights and parents.
 *
 * Updates terms that exist and creates the rest; a term on the site that D6 does not have is deleted.
 * Parents are set in a second pass, since a parent can have a higher tid than its child.
 * Run: ddev drush php:script scripts/30-terms.php
 */

use Drupal\taxonomy\Entity\Term;

require __DIR__ . '/lib.php';

const HOBO_VOCABULARIES = [1 => 'adventures', 2 => 'image_galleries', 3 => 'access_level', 5 => 'topics'];

$rows = d6()->query('SELECT tid, vid, name, description, weight FROM {term_data} ORDER BY tid')->fetchAllAssoc('tid');
$created = 0;
foreach ($rows as $row) {
  $term = Term::load($row->tid);
  if (!$term) {
    $term = Term::create(['tid' => $row->tid, 'vid' => HOBO_VOCABULARIES[$row->vid]])->enforceIsNew();
    $created++;
  }
  $term->set('vid', HOBO_VOCABULARIES[$row->vid]);
  $term->setName($row->name);
  // setDescription() takes no format: set both.
  $term->set('description', ['value' => $row->description, 'format' => 'full_html_no_restrictions']);
  $term->setWeight($row->weight);
  $term->set('parent', [0]);
  $term->save();
}

$parented = 0;
foreach (d6()->query('SELECT tid, parent FROM {term_hierarchy} WHERE parent <> 0 ORDER BY tid, parent') as $edge) {
  $term = Term::load($edge->tid);
  $parents = array_filter(array_column($term->get('parent')->getValue(), 'target_id'));
  $term->set('parent', array_values(array_unique([...$parents, $edge->parent])))->save();
  $parented++;
}

$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$extra = array_diff(\Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)->execute(), array_keys($rows));
$storage->delete($storage->loadMultiple($extra));

$per_vocabulary = [];
foreach (HOBO_VOCABULARIES as $vid) {
  $per_vocabulary[] = "$vid " . \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)->condition('vid', $vid)->count()->execute();
}
printf("terms from D6 %d (created %d), with a parent %d, extra deleted %d; per vocabulary: %s\n",
  count($rows), $created, $parented, count($extra), implode(', ', $per_vocabulary));
