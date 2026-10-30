<?php

/**
 * @file
 * Step 40: the 1296 Drupal 6 nodes, with their nids and vids, at their current revision.
 *
 * Title, status, author, dates, promote/sticky, comment setting, body (and the teaser as summary where it is not
 * the body's start), text format, terms, the triplog and wp fields. The node with no type becomes a page (owner's
 * decision). Stored PHP is rewritten (owner's rule): its one use, print tripinfo_table('A','B'), becomes the macro
 * [tripinfo_table:A,B]. Updates nodes that exist, creates the rest, deletes nodes D6 does not have.
 * Run: ddev drush php:script scripts/40-nodes.php
 */

use Drupal\node\Entity\Node;

require __DIR__ . '/lib.php';

const HOBO_PHP = "/<\\?php\\s*print\\s+tripinfo_table\\('([0-9-]+)',\\s*'([0-9-]+)'\\);\\s*\\?>/";

$rows = d6()->query("
  SELECT n.nid, n.vid, n.type, n.title, n.uid, n.status, n.created, n.changed, n.comment, n.promote, n.sticky,
    r.body, r.teaser, r.format, r.uid AS revision_uid, r.timestamp, r.log
  FROM {node} n JOIN {node_revisions} r ON r.vid = n.vid ORDER BY n.nid")->fetchAllAssoc('nid');
$terms = [];
foreach (d6()->query('SELECT tn.nid, tn.tid FROM {term_node} tn JOIN {node} n ON n.vid = tn.vid ORDER BY tn.nid, tn.tid') as $tn) {
  $terms[$tn->nid][] = $tn->tid;
}
$triplog = d6()->query('SELECT t.* FROM {content_type_triplog} t JOIN {node} n ON n.vid = t.vid')->fetchAllAssoc('nid');
$wp = d6()->query('SELECT w.* FROM {content_type_wp} w JOIN {node} n ON n.vid = w.vid')->fetchAllAssoc('nid');
$poll = [];
foreach (d6()->query('SELECT nid, chtext, chvotes FROM {poll_choices} ORDER BY nid, chorder') as $choice) {
  $poll[$choice->nid][] = $choice;
}

$created = $rewritten = $summaries = $retyped = $untitled = 0;
$per_type = [];
foreach ($rows as $row) {
  $type = $row->type;
  if ($type === '') {
    // Owner's decision: the one node with no content type moves to page.
    $type = 'page';
    $retyped++;
  }
  $untitled += $row->title === '' ? 1 : 0;
  $node = Node::load($row->nid);
  if ($node && $node->bundle() !== $type) {
    $node->delete();
    $node = NULL;
  }
  if (!$node) {
    $node = Node::create(['nid' => $row->nid, 'vid' => $row->vid, 'type' => $type])->enforceIsNew();
    $created++;
  }
  $node->setSyncing(TRUE);

  $body = preg_replace(HOBO_PHP, '[tripinfo_table:$1,$2]', $row->body, -1, $replaced);
  $rewritten += $replaced ? 1 : 0;
  // D6 kept the teaser separately: the summary, whenever it is not the whole body (the theme's read-more link).
  $summary = trim($row->teaser) !== trim($row->body) ? preg_replace(HOBO_PHP, '[tripinfo_table:$1,$2]', $row->teaser) : '';
  $summaries += $summary !== '' ? 1 : 0;

  // Node 2307 is empty in D6 (no type, title, body or date); a Drupal 11 node must have a title.
  $node->setTitle($row->title !== '' ? $row->title : '(no title)');
  $node->setOwnerId($row->uid);
  $node->set('status', (int) $row->status);
  $node->setCreatedTime($row->created);
  $node->setChangedTime($row->changed);
  $node->setPromoted((bool) $row->promote);
  $node->setSticky((bool) $row->sticky);
  $node->setRevisionUserId($row->revision_uid);
  $node->setRevisionCreationTime($row->timestamp);
  $node->setRevisionLogMessage($row->log);
  $node->set('body', ['value' => $body, 'summary' => $summary, 'format' => HOBO_FORMATS[$row->format]]);
  $node->set('comment', ['status' => (int) $row->comment]);
  $node->set('field_tags', $terms[$row->nid] ?? []);

  if ($t = $triplog[$row->nid] ?? NULL) {
    $node->set('field_date', $t->field_date_value ? substr($t->field_date_value, 0, 10) : NULL);
    foreach (['start', 'destination', 'camp_or_hotel', 'trip', 'miles', 'elevation_gain', 'flat_tires', 'bad_dogs', 'miles_not_ridden', 'vid'] as $f) {
      $node->set("field_$f", $t->{"field_{$f}_value"});
    }
  }
  if ($w = $wp[$row->nid] ?? NULL) {
    $node->set('field_date_0', $w->field_date_0_value ? substr($w->field_date_0_value, 0, 10) : NULL);
    $node->set('field_trip_0', $w->field_trip_0_value);
  }
  if ($choices = $poll[$row->nid] ?? NULL) {
    $node->set('field_poll_choice', array_column($choices, 'chtext'));
    $node->set('field_poll_votes', array_map('intval', array_column($choices, 'chvotes')));
  }
  $node->save();
  $per_type[$type] = ($per_type[$type] ?? 0) + 1;
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
$extra = array_diff(\Drupal::entityQuery('node')->accessCheck(FALSE)->execute(), array_keys($rows));
$storage->delete($storage->loadMultiple($extra));

$query = fn() => \Drupal::entityQuery('node')->accessCheck(FALSE);
$php_left = \Drupal::database()->query("SELECT COUNT(*) FROM {node__body} WHERE body_value LIKE '%<?php%' OR body_summary LIKE '%<?php%'")->fetchField();
ksort($per_type);
printf("nodes from D6 %d (created %d), on site %d, published %d; retyped %d, untitled %d, PHP rewritten %d, PHP left %d, summaries %d, extra deleted %d\n",
  count($rows), $created, $query()->count()->execute(), $query()->condition('status', 1)->count()->execute(),
  $retyped, $untitled, $rewritten, $php_left, $summaries, count($extra));
echo 'per type: ' . implode(', ', array_map(fn($t, $n) => "$t $n", array_keys($per_type), $per_type)) . "\n";
