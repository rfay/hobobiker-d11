<?php

/**
 * @file
 * Captions for the last photos without one (owner's approval, 2026-11-07).
 *
 * Every other photo gets its caption from its own alt, its Flickr title or its file name (hobobiker_photos filter);
 * these 8 have only a camera number for a title, so their captions were written from the photo and its post. They
 * are stored in the posts, as content: the caption of their [hobophoto:] token, or the alt of their <img>. One
 * token also pointed at a file that does not exist (297234846.jpg, a digit short; broken on the original too): it
 * now names 2972348461_Vicuas.jpg. Each changed post is a new revision with a log message. Rerunnable.
 * Run: ddev drush php:script scripts/caption-photos.php
 */

use Drupal\node\Entity\Node;

// Token captions: no commas (they separate the token's settings).
$tokens = [
  4053 => ['2231359427.jpg' => 'Meeting a horseman on the road in Nicaragua'],
  4054 => ['2236756073.jpg' => 'The valley near San Marcos outside Estelí', '2240154985.jpg' => 'At the village well near San Marcos'],
  4055 => ['2240146103.jpg' => 'The church at the center of San Marcos'],
  4300 => ['2972348461_Vicuas.jpg' => 'Vicuñas'],
];
$paths = [4300 => ['sites/default/files/297234846.jpg' => 'sites/default/files/2972348461_Vicuas.jpg']];
$alts = [
  223 => ['52413561_8260cb58b1_m.jpg' => 'Ready to ride at Alternative Bike in Manchester'],
  1244 => ['258208138_17c3e2b073_m.jpg' => 'Our new little computer'],
  3852 => ['449570377_9ed9b09642_m.jpg' => 'Folk dancers'],
];

$changed = $edits = 0;
foreach (array_unique(array_merge(array_keys($tokens), array_keys($alts))) as $nid) {
  $node = Node::load($nid);
  $body = $node->get('body')->getValue()[0];
  $value = $body['value'];
  $n = 0;
  foreach ($paths[$nid] ?? [] as $from => $to) {
    $value = str_replace("path=$from,", "path=$to,", $value, $count);
    $n += $count;
  }
  foreach ($tokens[$nid] ?? [] as $file => $caption) {
    $value = preg_replace_callback('/\[hobophoto:[^\]]*\]/', function ($m) use ($file, $caption, &$n) {
      if (!str_contains($m[0], "/$file,") || !preg_match('/caption=\s*(,|\])/', $m[0])) {
        return $m[0];
      }
      $n++;
      return preg_replace('/caption=\s*(?=,|\])/', 'caption=' . $caption, $m[0]);
    }, $value);
  }
  foreach ($alts[$nid] ?? [] as $file => $alt) {
    $value = preg_replace_callback('~<img\b[^>]*' . preg_quote($file, '~') . '[^>]*>~', function ($m) use ($alt, &$n) {
      $escaped = htmlspecialchars($alt, ENT_QUOTES);
      if (str_contains($m[0], "alt=\"$escaped\"")) {
        return $m[0];
      }
      $n++;
      $tag = preg_replace('/\salt="[^"]*"/', '', $m[0]);
      return preg_replace('~^<img\b~', "<img alt=\"$escaped\"", $tag);
    }, $value);
  }
  if ($n) {
    $body['value'] = $value;
    $node->set('body', $body);
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage("Photo captions written from the photos and the post ($n " . ($n === 1 ? 'edit' : 'edits') . '); their Flickr titles were only camera numbers.');
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->setRevisionUserId(1);
    $node->setSyncing(TRUE);
    $node->save();
    $changed++;
    $edits += $n;
  }
}
printf("posts changed %d (new revisions), edits %d (captions, alt texts and the vicuñas path)\n", $changed, $edits);
