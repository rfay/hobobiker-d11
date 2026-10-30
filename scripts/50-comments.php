<?php

/**
 * @file
 * Step 50: the Drupal 6 comments, with their cids, parents, threads, authors, subjects, dates and bodies.
 *
 * D6 status 0 means published, so it is inverted. The comment on a deleted node (cid 27 on node 1198) is
 * dropped (owner's decision). No commenter's email address or IP address is copied (personal data, never
 * shown on the site). Updates comments that exist, creates the rest, deletes comments D6 does not have.
 * Statistics (count, last comment) are kept by Drupal as each comment is saved.
 * Run: ddev drush php:script scripts/50-comments.php
 */

use Drupal\comment\Entity\Comment;

require __DIR__ . '/lib.php';

const HOBO_COMMENT_FORMATS = [1 => 'filtered_html', 3 => 'full_html_no_restrictions'];

// Parents always have lower cids (checked), so cid order creates each parent before its replies.
$rows = d6()->query('SELECT c.* FROM {comments} c JOIN {node} n ON n.nid = c.nid ORDER BY c.cid')->fetchAllAssoc('cid');
$dropped = d6()->query('SELECT COUNT(*) FROM {comments} c LEFT JOIN {node} n ON n.nid = c.nid WHERE n.nid IS NULL')->fetchField();

$created = 0;
foreach ($rows as $row) {
  $comment = Comment::load($row->cid);
  if (!$comment) {
    $comment = Comment::create([
      'cid' => $row->cid,
      'comment_type' => 'comment',
      'entity_type' => 'node',
      'field_name' => 'comment',
      'entity_id' => $row->nid,
      // D6's own vancode, same format as Drupal 11's: kept, so the order and nesting are the original's.
      'thread' => $row->thread,
    ])->enforceIsNew();
    $created++;
  }
  $comment->setSyncing(TRUE);
  $comment->set('entity_id', $row->nid);
  $comment->set('pid', $row->pid ?: NULL);
  $comment->set('thread', $row->thread);
  $comment->set('uid', $row->uid);
  $comment->set('name', $row->name);
  $comment->set('homepage', $row->homepage);
  $comment->setSubject($row->subject);
  $comment->set('comment_body', ['value' => $row->comment, 'format' => HOBO_COMMENT_FORMATS[$row->format]]);
  $comment->setCreatedTime($row->timestamp);
  $comment->setChangedTime($row->timestamp);
  $comment->set('status', $row->status == 0 ? 1 : 0);
  $comment->save();
}

$storage = \Drupal::entityTypeManager()->getStorage('comment');
$extra = array_diff(\Drupal::entityQuery('comment')->accessCheck(FALSE)->execute(), array_keys($rows));
$storage->delete($storage->loadMultiple($extra));

$query = fn() => \Drupal::entityQuery('comment')->accessCheck(FALSE);
$replies = $query()->exists('pid')->count()->execute();
$counted = \Drupal::database()->query('SELECT SUM(comment_count) FROM {comment_entity_statistics}')->fetchField();
printf("comments from D6 %d (created %d), on site %d, published %d, replies %d; dropped (deleted node) %d, extra deleted %d; statistics count %d\n",
  count($rows), $created, $query()->count()->execute(), $query()->condition('status', 1)->count()->execute(),
  $replies, $dropped, count($extra), $counted);
