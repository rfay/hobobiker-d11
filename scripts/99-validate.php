<?php

/**
 * @file
 * Step 99: shows the site's data is valid Drupal 11 data. Changes nothing.
 *
 * Every content entity (nodes, comments, terms, users, files, aliases, menu links) is loaded and validated with
 * Drupal's own constraints ($entity->validate(): required values, field types and lengths, references, text
 * formats, unique paths ...), as an administrator, so access checks do not count. Then referential checks the
 * constraints do not make: every comment's node and parent, every term and file reference, every alias's path,
 * every menu link's parent. Prints counts per entity type and the number of violations, which should be 0.
 * Run: ddev drush php:script scripts/99-validate.php
 */

use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));

$types = ['node', 'comment', 'taxonomy_term', 'user', 'file', 'path_alias', 'menu_link_content'];
$violations = [];
$constraint_violations = 0;
$counts = [];
foreach ($types as $type) {
  $storage = \Drupal::entityTypeManager()->getStorage($type);
  $ids = \Drupal::entityQuery($type)->accessCheck(FALSE)->execute();
  if ($type === 'user') {
    unset($ids[0]);
  }
  $counts[$type] = count($ids);
  foreach (array_chunk($ids, 200) as $chunk) {
    foreach ($storage->loadMultiple($chunk) as $entity) {
      foreach ($entity->validate() as $violation) {
        $violations[] = "$type {$entity->id()} {$violation->getPropertyPath()}: " . strip_tags((string) $violation->getMessage());
        $constraint_violations++;
      }
    }
    $storage->resetCache($chunk);
  }
}

// References the entity constraints leave alone (dangling ids in the field tables).
$db = \Drupal::database();
$dangling = [
  'comment without its node' => 'SELECT COUNT(*) FROM {comment_field_data} c LEFT JOIN {node} n ON n.nid = c.entity_id WHERE n.nid IS NULL',
  'comment without its parent' => 'SELECT COUNT(*) FROM {comment_field_data} c LEFT JOIN {comment} p ON p.cid = c.pid WHERE c.pid IS NOT NULL AND p.cid IS NULL',
  'node with a missing term' => 'SELECT COUNT(*) FROM {node__field_tags} t LEFT JOIN {taxonomy_term_data} d ON d.tid = t.field_tags_target_id WHERE d.tid IS NULL',
  'term with a missing parent' => 'SELECT COUNT(*) FROM {taxonomy_term__parent} p LEFT JOIN {taxonomy_term_data} d ON d.tid = p.parent_target_id WHERE p.parent_target_id <> 0 AND d.tid IS NULL',
  'node with a missing author' => 'SELECT COUNT(*) FROM {node_field_data} n LEFT JOIN {users} u ON u.uid = n.uid WHERE u.uid IS NULL',
  'blog photo with a missing file' => 'SELECT COUNT(*) FROM {node__field_blog_image_portrait} i LEFT JOIN {file_managed} f ON f.fid = i.field_blog_image_portrait_target_id WHERE f.fid IS NULL',
  'audio with a missing file' => 'SELECT COUNT(*) FROM {node__field_audio} a LEFT JOIN {file_managed} f ON f.fid = a.field_audio_target_id WHERE f.fid IS NULL',
  'node body in a missing format' => 'SELECT COUNT(*) FROM {node__body} b LEFT JOIN {config} c ON c.name = CONCAT(:p, b.body_format) WHERE c.name IS NULL',
  // Formatted text with no format at all passes validate(); D6 always had one.
  'term description with no format' => "SELECT COUNT(*) FROM {taxonomy_term_field_data} WHERE description__value <> '' AND description__format IS NULL",
  'comment with no format' => "SELECT COUNT(*) FROM {comment__comment_body} WHERE comment_body_format IS NULL",
];
$dangling_total = 0;
foreach ($dangling as $label => $sql) {
  $n = (int) $db->query($sql, str_contains($sql, ':p') ? [':p' => 'filter.format.'] : [])->fetchField();
  $dangling_total += $n;
  if ($n) {
    $violations[] = "$label: $n";
  }
}
// Every alias points at a path Drupal routes, and every menu link's parent exists (validate() covers the rest).
$validator = \Drupal::service('path.validator');
$unrouted = 0;
foreach (\Drupal::entityTypeManager()->getStorage('path_alias')->loadMultiple() as $alias) {
  if (!$validator->getUrlIfValidWithoutAccessCheck(ltrim($alias->getPath(), '/'))) {
    $unrouted++;
    $violations[] = "path_alias {$alias->id()}: {$alias->getPath()} has no route";
  }
}
$menu_tree = \Drupal::service('plugin.manager.menu.link');
$orphans = 0;
foreach (\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadMultiple() as $link) {
  if ($link->getParentId() && !$menu_tree->hasDefinition($link->getParentId())) {
    $orphans++;
    $violations[] = "menu_link_content {$link->id()}: parent missing";
  }
}

\Drupal::service('account_switcher')->switchBack();

echo 'validated: ' . implode(', ', array_map(fn($t, $n) => "$t $n", array_keys($counts), $counts)) . "\n";
printf("entity constraint violations %d; dangling references %d; aliases without a route %d; menu links without a parent %d; total %d\n",
  $constraint_violations, $dangling_total, $unrouted, $orphans, $constraint_violations + $dangling_total + $unrouted + $orphans);
foreach (array_slice($violations, 0, 40) as $line) {
  echo "  $line\n";
}
