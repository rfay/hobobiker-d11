<?php

/**
 * @file
 * Step 60: the 44 Drupal 6 managed files, with their fids, and what references them.
 *
 * The files themselves are already in web/sites/default/files (a copy of the original's). D6 stored three kinds
 * of path: sites/default/files/X, files/X (the original's docroot has a symlink files -> sites/default/files)
 * and images/X (the image module's); each becomes public://X. The original's /files symlink is reproduced too,
 * since old content links to /files/... . References: the blog image fields and the audio nodes' mp3s.
 * Updates files that exist, creates the rest, deletes files D6 does not have.
 * Run: ddev drush php:script scripts/60-files.php
 */

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

require __DIR__ . '/lib.php';

// The original's docroot symlink: /files/... is /sites/default/files/... .
$link = DRUPAL_ROOT . '/files';
if (!is_link($link)) {
  symlink('sites/default/files', $link);
}

$rows = d6()->query('SELECT * FROM {files} ORDER BY fid')->fetchAllAssoc('fid');
$created = $missing = 0;
foreach ($rows as $row) {
  $relative = preg_replace('@^(sites/default/files|files)/@', '', $row->filepath);
  $uri = "public://$relative";
  $file = File::load($row->fid);
  if (!$file) {
    $file = File::create(['fid' => $row->fid])->enforceIsNew();
    $created++;
  }
  $file->setSyncing(TRUE);
  $file->setOwnerId($row->uid);
  // D6 named image-module derivatives by their role (_original, thumbnail, preview): use the file's own name.
  $file->setFilename(in_array($row->filename, ['_original', 'thumbnail', 'preview'], TRUE) ? basename($relative) : $row->filename);
  $file->setFileUri($uri);
  $file->setMimeType($row->filemime);
  $file->setSize($row->filesize);
  $file->set('status', $row->status);
  $file->set('created', $row->timestamp);
  $file->setChangedTime($row->timestamp);
  $file->save();
  // Reproduced as is: the image module's 24 files are missing on the original's disk too.
  $missing += file_exists($uri) ? 0 : 1;
}
$storage = \Drupal::entityTypeManager()->getStorage('file');
$extra = array_diff(\Drupal::entityQuery('file')->accessCheck(FALSE)->execute(), array_keys($rows));
$storage->delete($storage->loadMultiple($extra));

// Blog photos, current revision only, in delta order, with the alt/title D6 kept in the item's data.
$references = 0;
foreach (['field_blog_image', 'field_blog_image_portrait'] as $field) {
  $items = [];
  $query = d6()->query("SELECT b.nid, b.{$field}_fid AS fid, b.{$field}_data AS data FROM {content_$field} b
    JOIN {node} n ON n.vid = b.vid WHERE b.{$field}_fid IS NOT NULL ORDER BY b.nid, b.delta");
  foreach ($query as $row) {
    $data = unserialize((string) $row->data, ['allowed_classes' => FALSE]) ?: [];
    // The lightbox formatter showed the description (as alt and caption) when there was no alt.
    $items[$row->nid][] = ['target_id' => $row->fid, 'alt' => ($data['alt'] ?? '') !== '' ? $data['alt'] : ($data['description'] ?? ''), 'title' => $data['title'] ?? ''];
  }
  foreach (\Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'blog')->execute() as $nid) {
    $node = Node::load($nid);
    if ($node->get($field)->getValue() != ($items[$nid] ?? [])) {
      $node->setSyncing(TRUE);
      $node->set($field, $items[$nid] ?? [])->save();
    }
    $references += count($items[$nid] ?? []);
  }
}

// Audio: each audio node's mp3, file facts and counters (D6 audio) and ID3 tags (D6 audio_metadata), current
// revision.
$audio = $tags = 0;
foreach (d6()->query('SELECT a.* FROM {audio} a JOIN {node} n ON n.vid = a.vid ORDER BY a.nid') as $row) {
  $node = Node::load($row->nid);
  $node->setSyncing(TRUE);
  $node->set('field_audio', ['target_id' => $row->fid, 'display' => 1]);
  foreach (['format', 'channel_mode', 'bitrate_mode', 'playtime', 'sample_rate', 'download_count', 'play_count', 'bitrate'] as $name) {
    $node->set("field_audio_$name", $row->$name);
  }
  // D6's recorded file size (shown in "Length"); the file entity keeps the real one.
  $node->set('field_audio_filesize', d6()->query('SELECT filesize FROM {files} WHERE fid = :fid', [':fid' => $row->fid])->fetchField());
  foreach (['artist', 'title', 'album', 'track', 'genre', 'year'] as $tag) {
    $node->set("field_audio_$tag", NULL);
  }
  foreach (d6()->query('SELECT tag, value FROM {audio_metadata} WHERE vid = :vid', [':vid' => $row->vid]) as $meta) {
    $node->set("field_audio_{$meta->tag}", $meta->value);
    $tags++;
  }
  $node->save();
  $audio++;
}

// Record the usage Drupal keeps for file fields, from scratch, so reruns do not inflate it.
\Drupal::database()->delete('file_usage')->execute();
$usage = \Drupal::service('file.usage');
foreach (['field_blog_image', 'field_blog_image_portrait', 'field_audio'] as $field) {
  foreach (\Drupal::database()->query("SELECT entity_id, {$field}_target_id AS fid FROM {node__$field}") as $row) {
    $usage->add(File::load($row->fid), 'file', 'node', $row->entity_id);
  }
}

printf("files from D6 %d (created %d), on site %d, missing on disk %d (as on the original); blog image references %d, audio %d (tags %d), file usage rows %d, /files symlink %s\n",
  count($rows), $created, \Drupal::entityQuery('file')->accessCheck(FALSE)->count()->execute(), $missing, $references, $audio, $tags,
  \Drupal::database()->query('SELECT COUNT(*) FROM {file_usage}')->fetchField(), is_link($link) ? 'yes' : 'no');
