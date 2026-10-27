<?php

/**
 * @file
 * Is the imported data valid Drupal 11 data? (PART3-PLAN.md, D15.)
 *
 * `save()` never calls `validate()`, so an import script can store data that Drupal itself would refuse. Checks:
 *   1. every node, comment, term, user, file and alias through the entity's own validate(), as uid 1;
 *   2. references that point at nothing (SQL), and file records whose file is not on disk;
 *   3. Drupal's own health: pending entity or database updates, config differing from config/sync, requirement errors;
 *   4. the edit form of one node of each type builds.
 * Prints a count per finding and writes them all as JSON (default: tmp/integrity.json in the project).
 *
 * Run: ddev drush php:script scripts/90-check-integrity.php [-- <output.json>]
 */

use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Session\UserSession;

$out = $extra[0] ?? DRUPAL_ROOT . '/../tmp/integrity.json';
$etm = \Drupal::entityTypeManager();
$db = \Drupal::database();
$findings = [];
// One finding: a check, how many, and up to 5 examples.
$add = function (string $check, string $what, array $examples) use (&$findings) {
  $findings[] = ['check' => $check, 'what' => $what, 'count' => count($examples), 'examples' => array_slice($examples, 0, 5)];
};
\Drupal::service('account_switcher')->switchTo(new UserSession(['uid' => 1, 'roles' => ['authenticated', 'administrator']]));

// 1. Entity validation, grouped by entity type, field and message.
$validated = 0;
foreach (['node', 'comment', 'taxonomy_term', 'user', 'file', 'path_alias'] as $type) {
  $storage = $etm->getStorage($type);
  $byKind = [];
  foreach (array_chunk($storage->getQuery()->accessCheck(FALSE)->execute(), 100) as $ids) {
    foreach ($storage->loadMultiple($ids) as $id => $entity) {
      $validated++;
      foreach ($entity->validate() as $v) {
        $field = explode('.', $v->getPropertyPath())[0] ?: '(entity)';
        // Messages carry ids and values; strip digits so one kind of problem is one group.
        $msg = preg_replace('/\d+/', 'N', strip_tags((string) $v->getMessage()));
        $byKind["$field: $msg"][] = "$type/$id";
      }
    }
    $storage->resetCache();
  }
  foreach ($byKind as $kind => $ids) {
    $add('validate', "$type $kind", array_values(array_unique($ids)));
  }
}

// 2. References to nothing.
$ids = fn(string $sql, array $args = []) => $db->query($sql, $args)->fetchCol();
$add('orphan', 'node owned by a missing user', $ids('SELECT n.nid FROM {node_field_data} n LEFT JOIN {users} u ON u.uid = n.uid WHERE u.uid IS NULL'));
$add('orphan', 'comment on a missing node', $ids("SELECT c.cid FROM {comment_field_data} c LEFT JOIN {node} n ON n.nid = c.entity_id WHERE c.entity_type = 'node' AND n.nid IS NULL"));
$add('orphan', 'comment reply to a missing comment', $ids('SELECT c.cid FROM {comment_field_data} c LEFT JOIN {comment} p ON p.cid = c.pid WHERE c.pid IS NOT NULL AND p.cid IS NULL'));
$add('orphan', 'term with a missing parent', $ids('SELECT t.entity_id FROM {taxonomy_term__parent} t LEFT JOIN {taxonomy_term_data} p ON p.tid = t.parent_target_id WHERE t.parent_target_id <> 0 AND p.tid IS NULL'));
foreach (\Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node') as $name => $def) {
  $target = $def->getSetting('target_type');
  // Configured fields only (base fields such as `type` point at config entities, which have no table).
  if (!$def instanceof \Drupal\field\Entity\FieldStorageConfig || !$target || !$etm->getDefinition($target)->getBaseTable()) {
    continue;
  }
  $t = $etm->getDefinition($target);
  $add('orphan', "node field $name points at a missing $target", $ids("SELECT f.entity_id FROM {node__$name} f
    LEFT JOIN {{$t->getBaseTable()}} x ON x.{$t->getKey('id')} = f.{$name}_target_id WHERE x.{$t->getKey('id')} IS NULL"));
}
$add('orphan', 'alias of a missing node', $ids("SELECT a.alias FROM {path_alias} a LEFT JOIN {node} n ON a.path = CONCAT('/node/', n.nid)
  WHERE a.path REGEXP :re AND n.nid IS NULL", [':re' => '^/node/[0-9]+$']));
$add('orphan', 'alias of a missing term', $ids("SELECT a.alias FROM {path_alias} a LEFT JOIN {taxonomy_term_data} t ON a.path = CONCAT('/taxonomy/term/', t.tid)
  WHERE a.path REGEXP :re AND t.tid IS NULL", [':re' => '^/taxonomy/term/[0-9]+$']));
$formats = array_keys($etm->getStorage('filter_format')->loadMultiple());
foreach (['node__body' => 'body_format', 'comment__comment_body' => 'comment_body_format', 'taxonomy_term__description' => 'description__format'] as $table => $col) {
  if ($db->schema()->tableExists($table) && $db->schema()->fieldExists($table, $col)) {
    $add('orphan', "$table uses a missing text format", $ids("SELECT entity_id FROM {{$table}} WHERE $col IS NOT NULL AND $col NOT IN ('" . implode("','", $formats) . "')"));
  }
}
$fs = \Drupal::service('file_system');
$missing = [];
foreach ($db->query('SELECT fid, uri FROM {file_managed}') as $f) {
  if (!is_file($fs->realpath($f->uri) ?: $f->uri)) {
    $missing[] = "file/$f->fid";
  }
}
$add('orphan', 'file record whose file is not on disk', $missing);

// 3. Drupal's own health.
$add('health', 'entity definition updates pending', array_keys(\Drupal::entityDefinitionUpdateManager()->getChangeSummary()));
\Drupal::moduleHandler()->loadInclude('install', 'inc', 'includes/install');
require_once DRUPAL_ROOT . '/core/includes/update.inc';
$add('health', 'database updates pending', array_keys(update_get_update_list()));
$add('health', 'post-updates pending', \Drupal::service('update.post_update_registry')->getPendingUpdateFunctions());
$comparer = new StorageComparer(\Drupal::service('config.storage.sync'), \Drupal::service('config.storage'));
$changed = $comparer->createChangelist()->hasChanges() ? array_merge(...array_values($comparer->getChangelist())) : [];
$add('health', 'active config differs from config/sync', $changed);
$errors = [];
foreach (\Drupal::service('system.manager')->listRequirements() as $key => $r) {
  if (($r['severity'] ?? 0) >= 2) {
    $errors[] = "$key: " . strip_tags((string) ($r['title'] ?? ''));
  }
}
$add('health', 'status report errors', $errors);

// 4. The edit form of the first node of each type builds.
$broken = [];
$types = $db->query('SELECT type, MIN(nid) nid FROM {node_field_data} GROUP BY type')->fetchAllKeyed();
foreach ($types as $type => $nid) {
  try {
    \Drupal::service('renderer')->renderInIsolation(\Drupal::service('entity.form_builder')->getForm($etm->getStorage('node')->load($nid), 'edit'));
  }
  catch (\Throwable $e) {
    $broken[] = "node/$nid ($type): " . substr($e->getMessage(), 0, 150);
  }
}
$add('edit', 'node edit form fails to build', $broken);

\Drupal::service('account_switcher')->switchBack();
$problems = array_values(array_filter($findings, fn($f) => $f['count'] > 0));
foreach ($problems as $f) {
  printf("%5d  %-8s %s\n", $f['count'], $f['check'], $f['what']);
}
printf("%d entities validated, %d edit forms built; %d kinds of problem, %d items\n", $validated, count($types) - count($broken),
  count($problems), array_sum(array_column($problems, 'count')));
@mkdir(dirname($out), 0777, TRUE);
file_put_contents($out, json_encode(['generatedAt' => date('c'), 'validated' => $validated, 'findings' => $findings], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
print "Wrote $out\n";
