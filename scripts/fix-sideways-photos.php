<?php

/**
 * @file
 * The 13 photos whose pixels are sideways, the right way up (owner's review, 2026-11-06).
 *
 * Found on contact sheets of the 543 photos the posts show; the owner checked the before/after sheet. For each:
 * - the file gets the EXIF orientation that turns it upright (6: turn right, 8: turn left). The pixels are not
 *   changed; browsers, the lightbox and the image styles (image_effects' auto orient) honor the flag. Undo: set it
 *   back to 1.
 * - the posts' [hobophoto:] tokens for it change from orientation=landscape to portrait, so the upright photo gets
 *   the 180x240 thumbnail instead of being cropped into 240x180: a new revision with a log message.
 * Rerunnable: a file already flagged and a token already portrait are left alone.
 * Run: ddev drush php:script scripts/fix-sideways-photos.php
 */

use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use lsolesen\pel\PelEntryShort;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelTiff;

const TURN_RIGHT = 6;
const TURN_LEFT = 8;

$photos = [
  '918234649_Clara_Ajsoc.jpg' => TURN_LEFT,
  '2423587173_Cartagena_bay.jpg' => TURN_LEFT,
  '2423589879_Beachcomber_near_Tolu_Colombia.jpg' => TURN_LEFT,
  '2424388596_Muddy_road_from_Panama_City_to_Carti_Panama_on_the_way_to_the_San_Blas_Islands.jpg' => TURN_RIGHT,
  '2668058859_Indigenous_boy_posing_for_us_near_Saraguro.jpg' => TURN_LEFT,
  '2669106412_In_Peru_young_friends_ran_to_catch_us_to_give_us_a_papaya.jpg' => TURN_LEFT,
  '2785008992_Lucho_posing_in_front_of_the_Casa_de_Ciclistas.jpg' => TURN_LEFT,
  '2822681018_Inca_maiden_at_Huayuculano.jpg' => TURN_LEFT,
  '2851505864_Maria_Galarza_Valenzuela.jpg' => TURN_LEFT,
  '2892750225_Climbing_Huayna_Picchu.jpg' => TURN_LEFT,
  '2892764531_Machu_Picchu.jpg' => TURN_LEFT,
  '2893455966_Closeup_of_old_man_at_Pisac.jpg' => TURN_LEFT,
  '2893577060_Nancy_and_Randy_at_Machu_Picchu.jpg' => TURN_RIGHT,
];

$fs = \Drupal::service('file_system');
$flagged = $already = 0;
foreach ($photos as $name => $orientation) {
  $path = $fs->realpath("public://$name");
  $jpeg = new PelJpeg($path);
  $exif = $jpeg->getExif();
  if (!$exif) {
    $exif = new PelExif();
    $jpeg->setExif($exif);
  }
  $tiff = $exif->getTiff();
  if (!$tiff) {
    $tiff = new PelTiff();
    $exif->setTiff($tiff);
  }
  $ifd = $tiff->getIfd();
  if (!$ifd) {
    $ifd = new PelIfd(PelIfd::IFD0);
    $tiff->setIfd($ifd);
  }
  $entry = $ifd->getEntry(PelTag::ORIENTATION);
  if ($entry && (int) $entry->getValue() === $orientation) {
    $already++;
    continue;
  }
  if ($entry) {
    $entry->setValue($orientation);
  }
  else {
    $ifd->addEntry(new PelEntryShort(PelTag::ORIENTATION, $orientation));
  }
  $jpeg->saveFile($path);
  // Read it back: still a JPEG of the same size, now with the flag.
  $check = new PelJpeg($path);
  if ((int) $check->getExif()?->getTiff()?->getIfd()?->getEntry(PelTag::ORIENTATION)?->getValue() !== $orientation || !getimagesize($path)) {
    throw new \RuntimeException("Orientation not written to $name");
  }
  image_path_flush("public://$name");
  $flagged++;
}

// Their photo tokens: portrait thumbnails.
$names = array_map(fn($n) => preg_quote($n, '/'), array_keys($photos));
$token = '/(\[hobophoto:[^\]]*(?:' . implode('|', $names) . ')[^\]]*orientation=)landscape/';
$tokens = $nodes = 0;
$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('body', '%hobophoto%', 'LIKE')->execute();
foreach (Node::loadMultiple($nids) as $node) {
  $body = $node->get('body')->getValue()[0] ?? NULL;
  if (!$body) {
    continue;
  }
  $value = preg_replace($token, '$1portrait', $body['value'], -1, $n);
  if ($n) {
    $body['value'] = $value;
    $node->set('body', $body);
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage("Sideways photos the right way up: $n [hobophoto:] " . ($n === 1 ? 'token' : 'tokens') . ' now portrait (their files carry the EXIF orientation that turns them upright).');
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->setRevisionUserId(1);
    $node->setSyncing(TRUE);
    $node->save();
    $tokens += $n;
    $nodes++;
  }
}
foreach (['240x180', '180x240'] as $style) {
  ImageStyle::load($style)?->flush();
}

printf("photos %d: orientation set %d, already set %d; photo tokens made portrait %d in %d posts (new revisions)\n",
  count($photos), $flagged, $already, $tokens, $nodes);
