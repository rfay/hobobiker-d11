<?php

/**
 * @file
 * Step 30 with the Migrate API: the same 82 terms, by the migration hobo_d6_taxonomy_term (in the hobobiker
 * module's migrations/), for comparing the two approaches. Not part of run-all.sh.
 *
 * Deletes the terms step 30 made, imports them with Migrate (same tids), and prints the counts and what Migrate
 * tracked. "rollback" as an argument rolls the migration back instead. Deleting terms also deletes their URL
 * aliases: afterwards rerun steps 30 and 70 (or scripts/run-all.sh).
 * Run: ddev drush php:script scripts/alt/30-terms-migrate.php [rollback]
 */

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;

require dirname(__DIR__) . '/lib.php';

// migrate_drupal (the D6/D7 source plugins) is deprecated in Drupal 11.
\Drupal::service('module_installer')->install(['migrate', 'migrate_drupal']);
d6();

$migration = \Drupal::service('plugin.manager.migration')->createInstance('hobo_d6_taxonomy_term');
$executable = new MigrateExecutable($migration, new MigrateMessage());
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$count = fn() => \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)->count()->execute();

if (in_array('rollback', $extra ?? [], TRUE)) {
  $executable->rollback();
  printf("rolled back: terms on site %d, map rows %d\n", $count(), $migration->getIdMap()->processedCount());
  return;
}

// Start from no terms, as a migration into a fresh site would.
$storage->delete($storage->loadMultiple());
$migration->getIdMap()->prepareUpdate();
$start = microtime(TRUE);
$result = $executable->import();
$map = $migration->getIdMap();
$per_vocabulary = [];
foreach (['adventures', 'image_galleries', 'access_level', 'topics'] as $vid) {
  $per_vocabulary[] = "$vid " . \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)->condition('vid', $vid)->count()->execute();
}
printf("migrate import: result %d (1 = completed), %.1fs; source %d, imported %d, failed %d, map rows %d; terms on site %d (%s); with a parent %d\n",
  $result, microtime(TRUE) - $start, $migration->getSourcePlugin()->count(), $map->importedCount(), $map->errorCount(),
  $map->processedCount(), $count(), implode(', ', $per_vocabulary),
  \Drupal::database()->query('SELECT COUNT(*) FROM {taxonomy_term__parent} WHERE parent_target_id <> 0')->fetchField());
