<?php

/**
 * @file
 * Builds the content model of the hobobiker.com rebuild: modules, text formats, image styles, content types,
 * fields, vocabularies, comment type, site settings.
 *
 * Reads the model from the Drupal 6 copy in the schema `d6` (types, vocabularies, which types use which
 * vocabulary), so nothing is typed in twice. Idempotent: run again to repair.
 * Run: ddev drush php:script scripts/10-site-shell.php
 * The result is exported to config/sync (drush cex), which is the real definition.
 */

use Drupal\comment\Entity\CommentType;
use Drupal\Core\Database\Database;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

Database::addConnectionInfo('d6', 'default', ['database' => 'd6'] + Database::getConnectionInfo('default')['default']);
$src = Database::getConnection('default', 'd6');
$q = fn(string $sql) => $src->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
$log = fn($m) => print($m . "\n");

\Drupal::service('module_installer')->install(['comment', 'datetime', 'image', 'taxonomy', 'path', 'search', 'hobobiker']);

// Image styles of the original (imagecache presets, all scale and crop). The [hobophoto:] filter uses two.
foreach ([[240, 180], [180, 240], [800, 600], [600, 800]] as [$w, $h]) {
  $id = "{$w}x{$h}";
  if (!ImageStyle::load($id)) {
    $style = ImageStyle::create(['name' => $id, 'label' => "$id (scale and crop)"]);
    $style->addImageEffect(['id' => 'image_scale_and_crop', 'weight' => 0, 'data' => ['width' => $w, 'height' => $h, 'anchor' => 'center-center']]);
    $style->save();
  }
}

// Text formats: one per Drupal 6 format in use, same names, filters in the original's order. No editor
// (CKEditor would rewrite legacy markup on edit). Drupal 6 filter deltas: 0 HTML, 1 line breaks, 3 HTML corrector.
// Drupal 6's HTML filter kept every attribute but style and on*; Drupal 11 needs them listed.
$allowed1 = '<a href hreflang title name id rel target class> <em> <strong> <cite> <code> <ul type class> <ol start type class> <li class> <dl> <dt> <dd> <div class id align> <img src alt title width height class id align border>';
$on = fn($w, $s = []) => ['status' => TRUE, 'weight' => $w, 'settings' => $s];
$html1 = ['allowed_html' => $allowed1, 'filter_html_help' => TRUE, 'filter_html_nofollow' => TRUE];
// Filters with no Drupal 11 port yet are listed in the comments; they are handled in later steps.
$formats = [
  // d6 format id => [machine name, label, filters]
  1 => ['filtered_html', 'Filtered HTML', ['filter_html' => $on(0, $html1), 'filter_autop' => $on(1), 'filter_htmlcorrector' => $on(10)]],
  // PHP: Drupal 11 has no PHP filter, and running stored PHP is not wanted (owner, D6). Every use was one call,
  // tripinfo_table('START','END'), which the import rewrites to [tripinfo:START,END].
  2 => ['trip_tables', 'Trip tables (was PHP code)', ['hobobiker_tripinfo_table' => $on(0)]],
  3 => ['full_html_no_restrictions', 'Full HTML-No Restrictions', ['filter_htmlcorrector' => $on(10)]],
  5 => ['html_with_line_breaks', 'HTML with line breaks', ['filter_autop' => $on(0), 'filter_htmlcorrector' => $on(10)]],
  // liquid_filters (wiki markup): not ported yet.
  6 => ['wiki', 'Wiki', ['filter_htmlcorrector' => $on(10)]],
  // gmap macro: not ported yet.
  7 => ['html_full_with_gmap', 'HTML (full) with Gmap', ['filter_htmlcorrector' => $on(10)]],
  // gtspam (email obfuscation): not ported yet.
  8 => ['full_html_with_email_obfuscation', 'Full html with email obfuscation', ['filter_htmlcorrector' => $on(10)]],
  // tripinfo ([multisource:] maps) + php (the tripinfo_table() calls, rewritten by the import).
  9 => ['tripinfo_google_map', 'Tripinfo Google Map', ['hobobiker_multisource' => $on(-10), 'hobobiker_tripinfo_table' => $on(-9)]],
  // flickr: no node uses a flickr macro, so only line breaks and [hobophoto:] matter.
  10 => ['flickr_full_html_linebreaks', 'Flickr with full html and linebreaker', ['filter_autop' => $on(0), 'hobobiker_hobophoto' => $on(1), 'filter_htmlcorrector' => $on(10)]],
  11 => ['flickr_full_html', 'Flickr with full html', ['hobobiker_hobophoto' => $on(1), 'filter_htmlcorrector' => $on(10)]],
];
$w = -10;
FilterFormat::load('php_code')?->delete();
foreach ($formats as [$id, $label, $filters]) {
  $f = FilterFormat::load($id) ?? FilterFormat::create(['format' => $id]);
  $f->set('name', $label)->set('weight', $w++)->set('filters', $filters)->save();
}
$log(count($formats) . ' text formats');

// Content types: every type the original defines or still has nodes of (acidfree, image and forum lost their
// modules but kept their nodes). The one node with no type becomes a page (owner's decision, D5).
$types = [];
foreach ($q('SELECT type, name FROM node_type') as $t) {
  $types[$t['type']] = $t['name'];
}
foreach ($q("SELECT DISTINCT type FROM node WHERE type <> ''") as $t) {
  $types[$t['type']] ??= ['acidfree' => 'Acidfree album', 'image' => 'Image', 'forum' => 'Forum topic'][$t['type']] ?? ucfirst($t['type']);
}
ksort($types);
// "By X - Posted on <date>" per type, as the theme settings had it (toggle_node_info_TYPE). A type Drupal 6 no
// longer knew (its module gone: acidfree, image) had no setting and showed none.
$themeSettings = unserialize($q("SELECT value FROM variable WHERE name = 'theme_settings'")[0]['value']);
$known = array_column($q('SELECT type FROM node_type'), 'type');
foreach ($types as $id => $label) {
  $t = NodeType::load($id) ?? NodeType::create(['type' => $id]);
  $submitted = (bool) ($themeSettings["toggle_node_info_$id"] ?? in_array($id, $known));
  $t->set('name', $label)->set('new_revision', FALSE)->set('display_submitted', $submitted)->save();
}
$log(count($types) . ' content types: ' . implode(', ', array_keys($types)));

// Vocabularies, same names (the stored tier compares terms by vocabulary name).
$vocabs = [];
foreach ($q('SELECT vid, name, hierarchy, multiple FROM vocabulary ORDER BY vid') as $v) {
  $id = strtolower(preg_replace('/\W+/', '_', $v['name']));
  $vocabs[$v['vid']] = ['id' => $id, 'field' => 'field_' . $id, 'multiple' => (bool) $v['multiple']];
  $voc = Vocabulary::load($id) ?? Vocabulary::create(['vid' => $id]);
  $voc->set('name', $v['name'])->save();
}
$log(count($vocabs) . ' vocabularies');

// Comment type. The original's comment module is off: comments are kept, never shown.
if (!CommentType::load('comment')) {
  CommentType::create(['id' => 'comment', 'label' => 'Comment', 'target_entity_type_id' => 'node'])->save();
  \Drupal::service('comment.manager')->addBodyField('comment');
}

// Field storages.
$storages = [
  'body' => ['text_with_summary', 1, []],
  'comment' => ['comment', 1, ['comment_type' => 'comment']],
  // CCK fields of triplog, wp and blog, same names.
  'field_date' => ['datetime', 1, ['datetime_type' => 'date']],
  'field_date_0' => ['datetime', 1, ['datetime_type' => 'date']],
  'field_start' => ['string_long', 1, []],
  'field_destination' => ['string_long', 1, []],
  'field_camp_or_hotel' => ['string_long', 1, []],
  'field_trip' => ['string_long', 1, []],
  'field_trip_0' => ['string_long', 1, []],
  'field_miles' => ['integer', 1, []],
  'field_miles_not_ridden' => ['integer', 1, []],
  'field_elevation_gain' => ['integer', 1, []],
  'field_flat_tires' => ['integer', 1, []],
  'field_bad_dogs' => ['integer', 1, []],
  'field_vid' => ['integer', 1, []],
  'field_blog_image' => ['image', -1, ['uri_scheme' => 'public']],
  'field_blog_image_portrait' => ['image', -1, ['uri_scheme' => 'public']],
];
foreach ($vocabs as $v) {
  $storages[$v['field']] = ['entity_reference', $v['multiple'] ? -1 : 1, ['target_type' => 'taxonomy_term']];
}
foreach ($storages as $name => [$type, $card, $settings]) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => $name, 'type' => $type, 'cardinality' => $card, 'settings' => $settings])->save();
  }
}

// Field instances: body and comments on every type; CCK fields where the original had them (labels from
// content_node_field_instance); a term field wherever a vocabulary is configured or actually used.
$instances = [];
foreach (array_keys($types) as $b) {
  $instances[] = [$b, 'body', 'Body', []];
  $instances[] = [$b, 'comment', 'Comments', ['default_mode' => 1, 'per_page' => 50, 'anonymous' => 1, 'form_location' => 0, 'preview' => 0]];
}
foreach ($q('SELECT field_name, type_name, label FROM content_node_field_instance') as $i) {
  $instances[] = [$i['type_name'], $i['field_name'], $i['label'], []];
}
$vocabTypes = $q("SELECT vid, type FROM vocabulary_node_types
  UNION SELECT DISTINCT td.vid, IF(n.type = '', 'page', n.type) FROM term_node tn JOIN node n ON n.vid = tn.vid JOIN term_data td ON td.tid = tn.tid");
foreach ($vocabTypes as $vt) {
  if (isset($types[$vt['type']])) {
    $v = $vocabs[$vt['vid']];
    $instances[] = [$vt['type'], $v['field'], $q("SELECT name FROM vocabulary WHERE vid = {$vt['vid']}")[0]['name'],
      ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => [$v['id'] => $v['id']], 'auto_create' => FALSE]]];
  }
}
$n = 0;
foreach ($instances as [$bundle, $name, $label, $settings]) {
  if (!FieldConfig::loadByName('node', $bundle, $name)) {
    $cfg = ['field_name' => $name, 'entity_type' => 'node', 'bundle' => $bundle, 'label' => $label, 'settings' => $settings];
    if ($name === 'comment') {
      $cfg['default_value'] = [['status' => 0]];
    }
    FieldConfig::create($cfg)->save();
  }
  $n++;
}
$log("$n field instances");

// Displays: every field shown on the full display, except comments (hidden, as on the original).
// Layout and labels are matched in the theming step.
$repo = \Drupal::service('entity_display.repository');
foreach (array_keys($types) as $b) {
  $view = $repo->getViewDisplay('node', $b);
  $form = $repo->getFormDisplay('node', $b);
  $weight = 0;
  foreach (\Drupal::service('entity_field.manager')->getFieldDefinitions('node', $b) as $name => $def) {
    if (!$def instanceof FieldConfig) {
      continue;
    }
    // Comments are hidden, as on the original; terms show in the theme's "Tagged:" line instead.
    if ($name === 'comment' || $def->getSetting('target_type') === 'taxonomy_term') {
      $view->removeComponent($name);
      $form->setComponent($name, ['weight' => $weight]);
      continue;
    }
    // Blog images as the original showed them: the landscape field not at all (excluded in every display), the
    // portrait one as a linked 180x240 image with its caption.
    if ($name === 'field_blog_image') {
      $view->removeComponent($name);
      $form->setComponent($name, ['weight' => $weight]);
      continue;
    }
    $component = ['label' => $name === 'body' || str_starts_with($name, 'field_blog_image') ? 'hidden' : 'above', 'weight' => $weight++];
    if ($name === 'field_blog_image_portrait') {
      $component += ['type' => 'hobobiker_lightbox_image', 'settings' => ['image_style' => '180x240', 'link_style' => '600x800']];
    }
    // The original's date formats: triplog "30 Dec 2008", Wonderful People "15 February 2003".
    $dateFormat = ['field_date' => 'j M Y', 'field_date_0' => 'j F Y'][$name] ?? NULL;
    if ($dateFormat) {
      $component += ['type' => 'datetime_custom', 'settings' => ['date_format' => $dateFormat]];
    }
    $view->setComponent($name, $component);
    $form->setComponent($name, ['weight' => $weight]);
  }
  $view->save();
  $form->save();
  // Teasers (listings): the stored Drupal 6 teaser (the summary), or the whole body when it was short enough.
  $teaser = $repo->getViewDisplay('node', $b, 'teaser');
  foreach (array_keys($teaser->getComponents()) as $c) {
    $teaser->removeComponent($c);
  }
  $teaser->setComponent('body', ['type' => 'text_summary_or_trimmed', 'label' => 'hidden', 'weight' => 0, 'settings' => ['trim_length' => 1000000]]);
  if ($b === 'blog') {
    $teaser->setComponent('field_blog_image_portrait', ['type' => 'hobobiker_lightbox_image', 'label' => 'hidden', 'weight' => 5,
      'settings' => ['image_style' => '180x240', 'link_style' => '600x800']]);
  }
  $teaser->setStatus(TRUE)->save();
}

// Site settings as on the original. The front page is set by 60-front-page.php.
\Drupal::configFactory()->getEditable('system.site')
  ->set('name', "Hobobiker.com: Randy and Nancy's Big Bike Trip")
  ->set('slogan', "Randy and Nancy's Bike Touring Adventure from the top of the Americas to the Bottom of South America")
  ->save();
\Drupal::configFactory()->getEditable('system.date')->set('timezone.default', 'America/Denver')->save();
\Drupal::configFactory()->getEditable('user.settings')->set('anonymous', 'Anonymous')->save();

// Anonymous visitors read published content; unpublished nodes answer 403.
user_role_grant_permissions('anonymous', ['access content']);
user_role_grant_permissions('authenticated', ['access content']);
$log('Shell built.');
