<?php

/**
 * @file
 * The links to Babelfish (AltaVista's machine translation, long gone) taken out of the posts (owner's approval,
 * 2026-11-07).
 *
 * Nine links in seven posts offered a bad translation of the site (or, in one, of a French site). Only the
 * Babelfish part goes; where a link pointed readers at a story, it now points at that story (in English). Each
 * edit must match exactly once; each changed post is a new revision with a log message. A post with no Babelfish
 * left is skipped, so this is rerunnable. (The two view headers that linked to it were edited as configuration.)
 * Run: ddev drush php:script scripts/remove-babelfish.php
 */

use Drupal\node\Entity\Node;

$fish = 'http://(?:babelfish\.(?:altavista|av)\.com|66\.163\.168\.225)/babelfish/[^"]*';
$edits = [
  3890 => [
    ['~\s*<li><a href="' . $fish . '">Click here for a bad translation to English</a></li>~', ''],
  ],
  3978 => [
    ['~También puedes leer en español <a href="' . $fish . '">una traducción mala de las historias que hemos escrito</a> solamente en inglés:<br />\s*<br />\s*~u', ''],
  ],
  4098 => [
    ['~, pero hay malas traducciones <a href="' . $fish . 'hobobiker\.com">aquí</a>\.~u', '.'],
    ['~Hay historias \(también mal traducidas\) de~u', 'Hay historias de'],
    ['~<a href="' . $fish . 'hobobiker\.com/aplv">aquí</a>~u', '<a href="/aplv">aquí</a>'],
  ],
  4167 => [
    ['~, y puedes leer una mala traducción de todas las historias en <a href="' . $fish . '">este enlace</a>\.~u', '.'],
  ],
  4212 => [
    ['~\(<a href="' . $fish . 'node%2f4199" target="_blank">Aquí hay una traducción mala de la historia de Nancy sobre este tema</a>\.\)~u',
      '(<a href="/node/4199">Aquí está la historia de Nancy sobre este tema</a>, en inglés.)'],
    ['~ - <a href="' . $fish . 'node%2f4198" target="_blank">hay una traducción mala aquí</a>\.\)~u', ' (en inglés).'],
    ['~, con malas traducciones en <a href="' . $fish . '" target="_blank">este enlace</a>\.~u', '.'],
  ],
  4254 => [
    ['~\s*Hay una mala \(por m&aacute;quina\) traducci&oacute;n al castellano&nbsp;en este enlace:&nbsp;<a href="' . $fish . '" id="e9xu">[^<]*</a>~', ''],
  ],
];

$posts = $total = 0;
foreach ($edits as $nid => $replacements) {
  $node = Node::load($nid);
  $body = $node->get('body')->getValue()[0];
  if (stripos($body['value'], 'babelfish') === FALSE) {
    continue;
  }
  $value = $body['value'];
  foreach ($replacements as [$pattern, $with]) {
    $value = preg_replace($pattern, $with, $value, -1, $n);
    if ($n !== 1) {
      throw new \RuntimeException("node $nid: expected one match for $pattern, found $n");
    }
  }
  if (stripos($value, 'babelfish') !== FALSE) {
    throw new \RuntimeException("node $nid: a Babelfish link is left");
  }
  $body['value'] = $value;
  $node->set('body', $body);
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Removed ' . count($replacements) . ' mention(s) of Babelfish, the machine translation service that no longer exists.');
  $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
  $node->setRevisionUserId(1);
  $node->setSyncing(TRUE);
  $node->save();
  $posts++;
  $total += count($replacements);
}
$left = \Drupal::database()->query('SELECT COUNT(*) FROM {node__body} WHERE body_value LIKE :p', [':p' => '%babelfish%'])->fetchField();
printf("posts changed %d (new revisions), edits %d; posts still mentioning Babelfish %d\n", $posts, $total, $left);
