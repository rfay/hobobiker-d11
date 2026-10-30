<?php

/**
 * @file
 * Step 10: the site's structure, from the Drupal 6 source's own definitions.
 *
 * Content types (the D6 machine names), body, comments, the 4 vocabularies and one term reference field, the
 * triplog/wp CCK fields, the blog image fields, one text format per D6 format, the 4 imagecache presets as
 * image styles. Rerunnable: everything is created if missing and updated if present.
 * Run: ddev drush php:script scripts/10-structure.php
 */

use Drupal\comment\Entity\CommentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

require __DIR__ . '/lib.php';

// comment, and the site's own module (its field formatter is used below).
\Drupal::service('module_installer')->install(['comment', 'hobobiker']);

// Content types. D6 has 11 (one more node has no type: step 40 makes it a page).
$types = [
  'acidfree' => 'Acidfree album',
  'audio' => 'Audio',
  'blog' => 'Blog entry',
  'book' => 'Book page',
  'forum' => 'Forum topic',
  'image' => 'Image',
  'page' => 'Page',
  'poll' => 'Poll',
  'story' => 'Story',
  'triplog' => 'triplog',
  'wp' => 'Wonderful People',
];
foreach ($types as $id => $label) {
  $type = NodeType::load($id) ?? NodeType::create(['type' => $id]);
  $type->set('name', $label)->save();
}

// Vocabularies, by the D6 names (the stored tier compares terms by vocabulary label).
$vocabularies = [
  'adventures' => 'Adventures',
  'image_galleries' => 'Image Galleries',
  'access_level' => 'Access Level',
  'topics' => 'Topics',
];
foreach ($vocabularies as $id => $label) {
  $vocabulary = Vocabulary::load($id) ?? Vocabulary::create(['vid' => $id]);
  $vocabulary->set('name', $label)->save();
}
// The standard profile's empty Tags vocabulary is not the original's.
Vocabulary::load('tags')?->delete();

/**
 * Creates or updates a field storage and its instances.
 */
function hobo_field(string $name, string $type, array $bundles, array $storage = [], array $settings = [], string $entity_type = 'node'): void {
  $field_storage = FieldStorageConfig::loadByName($entity_type, $name)
    ?? FieldStorageConfig::create(['field_name' => $name, 'entity_type' => $entity_type, 'type' => $type] + $storage);
  $field_storage->save();
  foreach ($bundles as $bundle => $label) {
    $field = FieldConfig::loadByName($entity_type, $bundle, $name)
      ?? FieldConfig::create(['field_storage' => $field_storage, 'bundle' => $bundle, 'label' => $label]);
    $field->set('label', $label ?: $field->getLabel())->set('settings', $settings + $field->getSettings())->save();
  }
}

$all = array_fill_keys(array_keys($types), '');
hobo_field('body', 'text_with_summary', array_map(fn() => 'Body', $all), ['persist_with_no_fields' => TRUE]);
// One reference field for every vocabulary: D6's term_node is not per vocabulary.
hobo_field('field_tags', 'entity_reference', array_map(fn() => 'Terms', $all), [
  'cardinality' => -1,
  'settings' => ['target_type' => 'taxonomy_term'],
], [
  'handler' => 'default:taxonomy_term',
  'handler_settings' => ['target_bundles' => array_combine(array_keys($vocabularies), array_keys($vocabularies))],
]);

// Comments: one comment type, a comment field on every node type (D6 allowed them per type).
if (!CommentType::load('comment')) {
  CommentType::create([
    'id' => 'comment',
    'label' => 'Default comments',
    'target_entity_type_id' => 'node',
  ])->save();
}
\Drupal::service('comment.manager')->addBodyField('comment');
// D6: threaded, expanded (mode 4), 50 per page, open (2) by default.
hobo_field('comment', 'comment', array_map(fn() => 'Comments', $all), ['settings' => ['comment_type' => 'comment']],
  ['default_mode' => 1, 'per_page' => 50]);
foreach (array_keys($types) as $bundle) {
  FieldConfig::loadByName('node', $bundle, 'comment')->setDefaultValue([['status' => 2]])->save();
}

// What a node page shows: body and terms (the theme step arranges them like the original). No comments: the
// original had the comment module off, so its comments are kept but not shown.
$display_repository = \Drupal::service('entity_display.repository');
foreach (array_keys($types) as $bundle) {
  $display_repository->getViewDisplay('node', $bundle)
    ->setComponent('body', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0])
    ->setComponent('field_tags', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 10])
    ->removeComponent('comment')
    ->save();
  $display_repository->getViewDisplay('node', $bundle, 'teaser')
    ->setComponent('body', ['type' => 'text_summary_or_trimmed', 'label' => 'hidden', 'weight' => 0, 'settings' => ['trim_length' => 1000000]])
    ->save();
}
$display_repository->getViewDisplay('comment', 'comment')
  ->setComponent('comment_body', ['type' => 'text_default', 'label' => 'hidden'])
  ->save();

// triplog and wp CCK fields: the same names, values and types.
foreach (['field_start' => 'Start', 'field_destination' => 'Destination', 'field_camp_or_hotel' => 'Camp or hotel', 'field_trip' => 'Trip'] as $name => $label) {
  hobo_field($name, 'string', ['triplog' => $label]);
}
foreach (['field_miles' => 'Miles', 'field_elevation_gain' => 'Elevation gain', 'field_flat_tires' => 'Flat tires',
  'field_bad_dogs' => 'Bad dogs', 'field_miles_not_ridden' => 'Miles not ridden', 'field_vid' => 'Video'] as $name => $label) {
  hobo_field($name, 'integer', ['triplog' => $label]);
}
hobo_field('field_date', 'datetime', ['triplog' => 'Date'], ['settings' => ['datetime_type' => 'date']]);
hobo_field('field_date_0', 'datetime', ['wp' => 'Date'], ['settings' => ['datetime_type' => 'date']]);
hobo_field('field_trip_0', 'string', ['wp' => 'Trip']);
// Blog photos (D6 filefield with the imagefield widget, multiple, with a description).
foreach (['field_blog_image' => 'Image', 'field_blog_image_portrait' => 'Portrait image'] as $name => $label) {
  hobo_field($name, 'image', ['blog' => $label], ['cardinality' => -1], ['file_extensions' => 'png gif jpg jpeg', 'alt_field_required' => FALSE]);
}

// The audio module's file (one mp3 per audio node), its ID3 tags and file facts and its counters (D6 tables audio,
// audio_metadata). The hobobiker module shows them as the audio module did.
hobo_field('field_audio', 'file', ['audio' => 'Audio file'], [], ['file_extensions' => 'mp3']);
foreach (['artist', 'title', 'album', 'track', 'genre', 'year', 'format', 'channel_mode', 'bitrate_mode', 'playtime'] as $name) {
  hobo_field("field_audio_$name", 'string', ['audio' => 'Audio ' . str_replace('_', ' ', $name)]);
}
foreach (['sample_rate', 'download_count', 'play_count', 'filesize'] as $name) {
  hobo_field("field_audio_$name", 'integer', ['audio' => 'Audio ' . str_replace('_', ' ', $name)]);
}
hobo_field('field_audio_bitrate', 'float', ['audio' => 'Audio bitrate']);
// The poll module's choices and their votes (same deltas); the hobobiker module shows the results.
hobo_field('field_poll_choice', 'string', ['poll' => 'Choice'], ['cardinality' => -1]);
hobo_field('field_poll_votes', 'integer', ['poll' => 'Votes'], ['cardinality' => -1]);

// CCK fields as the original showed them: D6's labels, label above, in D6's order (weight, then field name), with
// D6's date formats (field_date_long: j M Y; field_date_0_default: j F Y) and no thousands separator. The blog's
// portrait photos show at 180x240 linked to 600x800 for the lightbox (the landscape field was excluded from display).
$cck = d6()->query('SELECT type_name, field_name, label, weight FROM {content_node_field_instance}
  WHERE type_name IN (:types[]) ORDER BY type_name, weight, field_name', [':types[]' => ['triplog', 'wp']])->fetchAll();
foreach ($cck as $i => $row) {
  $field = FieldConfig::loadByName('node', $row->type_name, $row->field_name);
  $field->set('label', $row->label)->save();
  $type = $field->getType();
  $component = match ($type) {
    'datetime' => ['type' => 'datetime_custom', 'settings' => ['date_format' => $row->field_name === 'field_date' ? 'j M Y' : 'j F Y']],
    'integer' => ['type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => FALSE]],
    default => ['type' => 'string', 'settings' => ['link_to_entity' => FALSE]],
  };
  foreach (['default', 'teaser'] as $mode) {
    \Drupal::service('entity_display.repository')->getViewDisplay('node', $row->type_name, $mode)
      ->setComponent($row->field_name, $component + ['label' => 'above', 'weight' => -50 + $i])->save();
  }
}
foreach (['default', 'teaser'] as $mode) {
  \Drupal::service('entity_display.repository')->getViewDisplay('node', 'blog', $mode)
    ->setComponent('field_blog_image_portrait', ['type' => 'hobobiker_lightbox_image', 'label' => 'hidden', 'weight' => 31,
      'settings' => ['image_style' => '180x240', 'link_style' => '600x800']])
    ->removeComponent('field_blog_image')
    ->save();
}

// Text formats: one per D6 format, same filters where Drupal 11 core has them. The site's own macros
// (hobophoto, tripinfo_table, multisource) are added by a later step, with the module that renders them.
$d6_allowed = '<a href title rel name target> <em> <strong> <cite> <code> <ul> <ol> <li> <dl> <dt> <dd> <div class style> <img src alt title width height class style>';
$formats = [
  // D6 id => [D11 id, name, filters]
  1 => ['filtered_html', 'Filtered HTML', [
    'filter_html' => ['weight' => 0, 'settings' => ['allowed_html' => $d6_allowed, 'filter_html_help' => TRUE, 'filter_html_nofollow' => TRUE]],
    'filter_autop' => ['weight' => 1],
    'filter_htmlcorrector' => ['weight' => 10],
  ]],
  // Owner's rule: no stored PHP runs. Its one use, tripinfo_table(), becomes a macro (step 40).
  2 => ['php_code', 'PHP code (rewritten; no PHP runs)', []],
  3 => ['full_html_no_restrictions', 'Full HTML-No Restrictions', ['filter_htmlcorrector' => ['weight' => 10]]],
  5 => ['html_with_line_breaks', 'HTML with line breaks', ['filter_autop' => ['weight' => 10], 'filter_htmlcorrector' => ['weight' => 11]]],
  6 => ['wiki', 'Wiki', []],
  7 => ['html_full_with_gmap', 'HTML (full) with Gmap', ['filter_htmlcorrector' => ['weight' => 11]]],
  8 => ['full_html_with_email_obfuscation', 'Full html with email obfuscation', ['filter_htmlcorrector' => ['weight' => 11]]],
  9 => ['tripinfo_google_map', 'Tripinfo Google Map', []],
  10 => ['flickr_full_html_linebreaks', 'Flickr with full html and linebreaker', ['filter_autop' => ['weight' => 10], 'filter_htmlcorrector' => ['weight' => 11]]],
  11 => ['flickr_full_html', 'Flickr with full html', ['filter_htmlcorrector' => ['weight' => 11]]],
];
foreach ($formats as [$id, $name, $filters]) {
  $format = FilterFormat::load($id) ?? FilterFormat::create(['format' => $id]);
  $format->set('name', $name);
  foreach (array_keys($format->get('filters') ?? []) as $existing) {
    if (!isset($filters[$existing]) && !in_array($existing, ['hobophoto', 'tripinfo_table', 'multisource'], TRUE)) {
      $format->removeFilter($existing);
    }
  }
  foreach ($filters as $filter_id => $config) {
    $format->setFilterConfig($filter_id, ['status' => TRUE] + $config);
  }
  $format->save();
}
// Everyone may view; only Filtered HTML is for visitors to write in, as on D6. Comments: none to see (module off).
foreach (['anonymous', 'authenticated'] as $rid) {
  user_role_grant_permissions($rid, ['access content', 'use text format filtered_html']);
  user_role_revoke_permissions($rid, ['access comments']);
}

// The imagecache presets, same names (D6: scale and crop).
foreach ([[240, 180], [180, 240], [800, 600], [600, 800]] as [$w, $h]) {
  $id = "{$w}x{$h}";
  $style = ImageStyle::load($id) ?? ImageStyle::create(['name' => $id, 'label' => $id]);
  foreach ($style->getEffects() as $effect) {
    $style->deleteImageEffect($effect);
  }
  $style->addImageEffect(['id' => 'image_scale_and_crop', 'weight' => 0, 'data' => ['width' => $w, 'height' => $h, 'anchor' => 'center-center']]);
  $style->save();
}

// Show what exists now.
$count = fn($type) => count(\Drupal::entityTypeManager()->getStorage($type)->loadMultiple());
$fields = \Drupal::entityQuery('field_config')->condition('entity_type', 'node')->count()->execute();
printf("content types %d, node field instances %d, vocabularies %d, text formats %d (from D6: %d), image styles %d\n",
  $count('node_type'), $fields, $count('taxonomy_vocabulary'), $count('filter_format'), count($formats), $count('image_style'));
