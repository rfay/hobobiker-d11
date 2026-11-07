<?php

/**
 * @file
 * No more hobobiker.com as a hostname in the content (owner's decision, 2026-11-07).
 *
 * The posts linked and loaded files by the old domain (http://www.hobobiker.com/...): on marlene.hobobiker.com
 * that sends readers to another site, and the trip maps fetched their KMZ files from it. Here every such address
 * becomes site-relative, a map token keeps only its file, text that shows the address ("www.hobobiker.com") says
 * "Hobobiker.com", and e-mail addresses at the domain become the contact page. A few links need more: listed in
 * $special. The name "Hobobiker.com" stays. Node 4111, an unpublished log of the site's maintenance, is left as
 * the record it is.
 *
 * Posts and summaries: new revisions with a log message; the one comment: saved. Rerunnable: content with nothing
 * left to change is skipped. --dry-run lists the changes and saves nothing.
 * Run: ddev drush php:script scripts/remove-hostname.php [--dry-run]
 */

use Drupal\comment\Entity\Comment;
use Drupal\node\Entity\Node;

$dry = in_array('--dry-run', $extra ?? [], TRUE);

// Links that need more than the general rules, by node.
$special = [
  // A page of the 2003 site that no longer exists.
  90 => [['~<a href="http://hobobiker\.com/mexico/photos\.php">(photos page)</a>~', '$1']],
  // Links to a Drupal 5 module's pages (unpublished post).
  1291 => [['~\[http://hobobiker\.com/taxonomy_menu/1/\d+ ([^\]]+)\]~', '$1']],
  // The e-mail list no longer exists.
  1306 => [['~ If you haven\'t already signed up for the email list, we invite you to sign up at <a href="http://hobobiker\.com/subscribe">hobobiker\.com/subscribe</a>\.~', '']],
  3997 => [['~<a href="/subscribe">(mailing list)</a>~', '$1']],
  3998 => [['~<a href="/subscribe_espanol">la lista de email\.\.\.</a>~', 'la lista de email']],
  // E-mail addresses at the domain: the contact page.
  2 => [['~, RandyAndNancy@hobobiker\.com:~', ':']],
  3977 => [['~please let us know at randyAndNancy@hobobiker\.com\.~i', 'please let us know through our <a href="/contact">contact page</a>.']],
  4349 => [['~You can contact us at RandyAndNancy \(at\) hobobiker\.com, thanks\.~', 'You can contact us through our <a href="/contact">contact page</a>, thanks.']],
  // A dead Google Translate link to node 4164; the site's Spanish page.
  4167 => [
    ['~<a href="http://64\.233\.179\.104/translate_c\?[^"]*node/4164">aqui</a>~', '<a href="/node/4164">aquí</a> (en inglés).'],
    ['~<a href="/espanol">http://hobobiker\.com/espanol</a>~', '<a href="/espanol">en español</a>'],
  ],
  // Comments are not shown on the site.
  4181 => [['~ or <a href="http://www\.hobobiker\.com/comment/reply/4181#comment-form">leave a comment here</a>~', '']],
  // Google Maps drew the whole trip from the KMZ on the old domain; the site's own map of it is node 4003.
  4339 => [['~href="http://maps\.google\.com/maps\?[^"]*hobobiker\.com[^"]*"~', 'href="/node/4003"']],
  4097 => [['~ title="www\.hobobiker\.com/aplv">www\.hobobiker\.com/aplv</a>~', '>our Agua Para La Vida page</a>']],
];

// The general rules, in order.
$general = [
  // Code commented out long ago that still names the old domain (Google Maps embeds that loaded a KMZ from it, map
  // tokens): it never shows, so it goes. A comment cannot reach past its own end, so <!--break--> stays.
  ['~<!--(?:(?!-->).)*hobobiker\.com(?:(?!-->).)*-->\s*~is', ''],
  // [multisource:http://www.hobobiker.com/files/X.kmz?123, http://hobobiker.com/flickr_georss/...]: the file only
  // (the flickr_georss sources were a module long gone); site-relative, as Tripinfo::multisource() expects.
  ['~\[multisource:\s*https?://(?:www\.)?hobobiker\.com/([^?,\]\s]+)[^\]]*\]~i', '[multisource:$1]'],
  // href and src: site-relative, without the old /en/ or /es/ language prefix.
  ['~((?:href|src)\s*=\s*)(["\'])https?://(?:www\.)?hobobiker\.com(?:/(?:en|es)(?=/))?(/[^"\']*)?\2~i', 'site-relative'],
  ['~href="//"~', 'href="/"'],
  // A title attribute that is only the address.
  ['~\s+title="(?:https?://)?(?:www\.)?hobobiker\.com/?"~i', ''],
  // Text showing the address: the name.
  ['~(?<!%2F)(?:https?://)?www\.hobobiker\.com/?(?=[^/\w%]|$)~i', 'Hobobiker.com'],
  ['~(?<![@/.\w])hobobiker\.com(?![/\w])~', 'Hobobiker.com'],
];

$apply = function (string $text, array $rules, int &$n): string {
  foreach ($rules as [$pattern, $with]) {
    $text = $with === 'site-relative'
      ? preg_replace_callback($pattern, fn($m) => $m[1] . $m[2] . (($m[3] ?? '') !== '' ? $m[3] : '/') . $m[2], $text, -1, $count)
      : preg_replace($pattern, $with, $text, -1, $count);
    $n += $count;
  }
  return $text;
};
$left = fn(string $text) => preg_match_all('~//(?:www\.)?hobobiker\.com|www\.hobobiker\.com|@hobobiker\.com|\(at\) hobobiker\.com|%2F%2Fwww\.hobobiker\.com~i', $text);

$posts = $edits = 0;
$report = [];
$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('nid', 4111, '<>')->execute();
foreach (Node::loadMultiple($nids) as $node) {
  if (!$node->hasField('body') || $node->get('body')->isEmpty()) {
    continue;
  }
  $body = $node->get('body')->getValue()[0];
  $before = $body;
  $n = 0;
  foreach (['value', 'summary'] as $part) {
    $text = (string) ($body[$part] ?? '');
    if ($text === '') {
      continue;
    }
    $text = $apply($text, $special[(int) $node->id()] ?? [], $n);
    $body[$part] = $apply($text, $general, $n);
  }
  if ($body === $before) {
    continue;
  }
  $posts++;
  $edits += $n;
  $report[] = sprintf('node %d: %d change(s), hostname left %d', $node->id(), $n, $left($body['value'] . $body['summary']));
  if ($dry) {
    @mkdir('/tmp/hostname-dry');
    file_put_contents('/tmp/hostname-dry/' . $node->id() . '.before', $before['value'] . "\n--summary--\n" . $before['summary']);
    file_put_contents('/tmp/hostname-dry/' . $node->id() . '.after', $body['value'] . "\n--summary--\n" . $body['summary']);
  }
  if (!$dry) {
    $node->set('body', $body);
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage("No more hobobiker.com as a hostname: $n link(s), map token(s) or address(es) made site-relative or reworded.");
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->setRevisionUserId(1);
    $node->setSyncing(TRUE);
    $node->save();
  }
}

$comments = 0;
foreach (Comment::loadMultiple(\Drupal::entityQuery('comment')->accessCheck(FALSE)->condition('comment_body', '%hobobiker.com%', 'LIKE')->execute()) as $comment) {
  $value = $comment->get('comment_body')->getValue()[0];
  $n = 0;
  $new = $apply($value['value'], $general, $n);
  if ($new !== $value['value']) {
    $comments++;
    $report[] = sprintf('comment %d: %d change(s)', $comment->id(), $n);
    if (!$dry) {
      $value['value'] = $new;
      $comment->set('comment_body', $value)->save();
    }
  }
}

print implode("\n", $report) . "\n";
$total = \Drupal::database()->query("SELECT COUNT(*) FROM {node__body} WHERE entity_id <> 4111 AND (body_value REGEXP :p OR body_summary REGEXP :p)", [':p' => '//(www\\.)?hobobiker\\.com|www\\.hobobiker\\.com|@hobobiker\\.com|%2F%2Fwww\\.hobobiker\\.com'])->fetchField();
printf("%sposts changed %d (new revisions), comments %d, changes %d; posts still with the hostname %d\n", $dry ? '[dry run] ' : '', $posts, $comments, $edits, $total);
