<?php

/**
 * @file
 * Listing pages, rebuilt from the original's definitions in the schema `d6`.
 *
 * - Every Drupal 6 view that lists nodes (not a table) is rebuilt with its path, title, header, content types,
 *   published filter, term filter, date range, sort, page size and row (teaser or full). That is the per-trip
 *   blogs, blog_in_order, podcast, aplv, espanol, gear and latest_stories. Note that most per-trip blogs have no
 *   published filter on the original, so they list unpublished entries too: reproduced.
 * - The blog module's /blog and /blog/UID (10 per page, teasers, sticky first, newest first, published only).
 * - Taxonomy term pages (Views' taxonomy_term, enabled on the original): sticky first, then OLDEST first.
 * Table views (triplog, wp, thanks-bigtrip, big_trip_mexico_route) come with the tripinfo step.
 *
 * Every listing uses the full pager with the original's wording. Idempotent.
 * Run: ddev drush php:script scripts/50-views.php
 */

use Drupal\Core\Database\Database;

Database::addConnectionInfo('d6', 'default', ['database' => 'd6'] + Database::getConnectionInfo('default')['default']);
$src = Database::getConnection('default', 'd6');
$cf = \Drupal::configFactory();
$log = fn($m) => print($m . "\n");

// Drupal 6 format id => text format (10-site-shell.php).
$formats = [0 => 'filtered_html', 1 => 'filtered_html', 2 => 'trip_tables', 3 => 'full_html_no_restrictions', 5 => 'html_with_line_breaks',
  6 => 'wiki', 7 => 'html_full_with_gmap', 8 => 'full_html_with_email_obfuscation', 9 => 'tripinfo_google_map',
  10 => 'flickr_full_html_linebreaks', 11 => 'flickr_full_html'];
$pager = fn(int $items) => ['type' => 'full', 'options' => ['items_per_page' => $items, 'offset' => 0, 'id' => 0, 'total_pages' => NULL, 'quantity' => 9,
  'pagination_heading_level' => 'h4', 'tags' => ['first' => '« first', 'previous' => '‹ previous', 'next' => 'next ›', 'last' => 'last »'],
  'expose' => ['items_per_page' => FALSE, 'offset' => FALSE]]];
$nodeField = fn(string $id, string $field, string $plugin, array $more = []) => ['id' => $id, 'table' => 'node_field_data', 'field' => $field,
  'entity_type' => 'node', 'entity_field' => $field, 'plugin_id' => $plugin, 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => ''] + $more;
$sort = fn(string $field, string $order) => $nodeField($field, $field, $field === 'created' ? 'date' : 'standard', ['order' => $order ?: 'ASC', 'exposed' => FALSE]
  + ($field === 'created' ? ['granularity' => 'second'] : []));

// The template every node listing starts from: the standard frontpage view.
$base = $cf->get('views.view.frontpage')->get();
unset($base['uuid'], $base['_core'], $base['dependencies']);
$feed = $base['display']['feed_1'];
$base['display'] = ['default' => $base['display']['default'], 'page_1' => $base['display']['page_1']];
$base['display']['default']['display_options']['filters'] = [];
$base['display']['default']['display_options']['sorts'] = [];
$base['display']['default']['display_options']['empty'] = [];
$base['display']['default']['display_options']['header'] = [];

/**
 * Saves a node listing. $o: path, title, types, published, tids, created [min, max], sorts [[field, order]], items,
 * view_mode, header [html, format], author argument, promoted.
 */
$make = function (string $id, string $label, array $o) use ($base, $feed, $cf, $pager, $nodeField, $sort): void {
  $v = $base;
  $v['id'] = $id;
  $v['label'] = $label;
  $d = &$v['display']['default']['display_options'];
  $d['title'] = $o['title'];
  $d['pager'] = $pager($o['items']);
  $d['row']['options']['view_mode'] = $o['view_mode'];
  if ($o['types'] ?? []) {
    $d['filters']['type'] = $nodeField('type', 'type', 'bundle', ['operator' => 'in', 'value' => array_combine($o['types'], $o['types']), 'group' => 1, 'expose' => ['operator' => '']]);
  }
  if ($o['published'] ?? FALSE) {
    $d['filters']['status'] = $nodeField('status', 'status', 'boolean', ['operator' => '=', 'value' => '1', 'group' => 1, 'expose' => ['operator' => '']]);
  }
  if ($o['promoted'] ?? FALSE) {
    $d['filters']['promote'] = $nodeField('promote', 'promote', 'boolean', ['operator' => '=', 'value' => '1', 'group' => 1, 'expose' => ['operator' => '']]);
  }
  if ($o['tids'] ?? []) {
    $d['filters']['tid'] = ['id' => 'tid', 'table' => 'taxonomy_index', 'field' => 'tid', 'plugin_id' => 'taxonomy_index_tid', 'relationship' => 'none',
      'group_type' => 'group', 'admin_label' => '', 'operator' => 'or', 'value' => array_combine($o['tids'], $o['tids']), 'group' => 1, 'exposed' => FALSE,
      'expose' => ['operator' => '', 'reduce' => FALSE], 'type' => 'select', 'limit' => FALSE, 'vid' => '', 'hierarchy' => FALSE, 'error_message' => TRUE];
  }
  if ($o['created'] ?? NULL) {
    // Read in the site's zone, America/Denver, as the original's Views read them (PHP's zone there; the fixed
    // UTC-6 of the original's date displays does not apply). Node 3802, created at midnight MST on 2006-11-15,
    // is inside "max 2006-11-15" only this way.
    $d['filters']['created'] = $nodeField('created', 'created', 'date', ['operator' => 'between', 'group' => 1, 'expose' => ['operator' => ''],
      'value' => ['type' => 'date', 'value' => '', 'min' => $o['created'][0], 'max' => $o['created'][1]]]);
  }
  foreach ($o['sorts'] as [$field, $order]) {
    $d['sorts'][$field] = $sort($field, $order);
  }
  if ($o['header'][0] ?? '') {
    $d['header']['area'] = ['id' => 'area', 'table' => 'views', 'field' => 'area', 'plugin_id' => 'text', 'relationship' => 'none', 'group_type' => 'group',
      'admin_label' => '', 'empty' => (bool) ($o['header'][2] ?? FALSE), 'tokenize' => FALSE, 'content' => ['value' => $o['header'][0], 'format' => $o['header'][1]]];
  }
  if ($o['author'] ?? FALSE) {
    $d['relationships'] = ['uid' => ['id' => 'uid', 'table' => 'node_field_data', 'field' => 'uid', 'relationship' => 'none', 'group_type' => 'group',
      'admin_label' => 'author', 'entity_type' => 'node', 'entity_field' => 'uid', 'plugin_id' => 'standard', 'required' => TRUE]];
    $d['arguments'] = ['uid' => ['id' => 'uid', 'table' => 'users_field_data', 'field' => 'uid', 'relationship' => 'uid', 'entity_type' => 'user', 'entity_field' => 'uid',
      'plugin_id' => 'user_uid', 'default_action' => 'not found', 'default_argument_type' => 'fixed', 'summary' => ['sort_order' => 'asc', 'number_of_records' => 0, 'format' => 'default_summary'],
      'summary_options' => [], 'specify_validation' => FALSE, 'validate' => ['type' => 'none', 'fail' => 'not found'], 'break_phrase' => FALSE, 'not' => FALSE]];
  }
  unset($d);
  $v['display']['page_1']['display_options'] = ['path' => $o['path'], 'menu' => ['type' => 'none'], 'display_extenders' => []];
  // A feed, attached to the page (its icon shows there), as the blog module had: 10 items (feed_default_items).
  if ($o['feed'] ?? NULL) {
    $v['display']['feed_1'] = $feed;
    $v['display']['feed_1']['display_options']['path'] = $o['feed'];
    $v['display']['feed_1']['display_options']['pager']['options']['items_per_page'] = 10;
    $v['display']['feed_1']['display_options']['sitename_title'] = FALSE;
  }
  $cf->getEditable("views.view.$id")->setData($v)->save();
};

// 1. The Drupal 6 views that list nodes.
$disabled = array_keys(array_filter(unserialize($src->query("SELECT value FROM variable WHERE name = 'views_defaults'")->fetchField() ?: 'a:0:{}'), fn($x) => $x === TRUE));
$displays = [];
foreach ($src->query('SELECT v.name, d.id, d.display_options FROM views_display d JOIN views_view v ON v.vid = d.vid') as $r) {
  $displays[$r->name][$r->id] = unserialize($r->display_options);
}
$made = [];
foreach ($displays as $name => $ds) {
  $page = $ds['page_1'] ?? $ds['page'] ?? NULL;
  $def = $ds['default'];
  $g = fn($k) => $page[$k] ?? $def[$k] ?? NULL;
  if (!$page || $g('row_plugin') !== 'node' || in_array($name, $disabled) || in_array($name, ['test_junk', 'taxonomy_term'])) {
    continue;
  }
  // Optional arguments (blog_in_order's date range, archive's year and month) are not reproduced: the page without
  // an argument, which lists everything, is.
  if ($g('arguments')) {
    $log("$name: argument(s) " . implode(', ', array_keys($g('arguments'))) . ' not reproduced');
  }
  $o = ['path' => $g('path'), 'title' => $g('title') ?: $name, 'items' => (int) $g('items_per_page'),
    'view_mode' => !empty($g('row_options')['teaser']) ? 'teaser' : 'full', 'sorts' => [], 'types' => [], 'tids' => []];
  foreach ((array) $g('filters') as $f) {
    $key = $f['table'] . '.' . $f['field'];
    match (TRUE) {
      $key === 'node.type' => $o['types'] = array_values(array_filter((array) $f['value'])),
      $key === 'node.status' => $o['published'] = TRUE,
      $key === 'node.promote' => $o['promoted'] = TRUE,
      $key === 'term_node.tid' => $o['tids'] = array_values(array_map('strval', (array) $f['value'])),
      in_array($key, ['node.created', 'node.date_filter']) => $o['created'] = [$f['value']['min'], $f['value']['max']],
      default => throw new \RuntimeException("$name: filter $key not handled"),
    };
  }
  foreach ((array) $g('sorts') as $s) {
    $o['sorts'][] = [$s['field'], strtoupper($s['order'] ?? '')];
  }
  if ($g('header')) {
    $o['header'] = [$g('header'), $formats[(int) $g('header_format')] ?? 'filtered_html', $g('header_empty')];
  }
  $id = 'd6_' . strtolower(preg_replace('/\W+/', '_', $name));
  $make($id, $name, $o);
  $made[] = '/' . $o['path'];
}
$log(count($made) . ' Drupal 6 views: ' . implode(' ', $made));

// 2. The blog module's listings: the site's "posts per page" (default_nodes_main), with their feeds.
$perPage = (int) unserialize($src->query("SELECT value FROM variable WHERE name = 'default_nodes_main'")->fetchField() ?: 'i:10;');
$blog = ['types' => ['blog'], 'published' => TRUE, 'items' => $perPage, 'view_mode' => 'teaser', 'sorts' => [['sticky', 'DESC'], ['created', 'DESC']]];
$make('blog', 'Blogs', ['path' => 'blog', 'title' => 'Blogs', 'feed' => 'blog/feed'] + $blog);
$make('blog_author', "Author's blog", ['path' => 'blog/%', 'title' => "{{ arguments.uid }}'s blog", 'author' => TRUE, 'feed' => 'blog/%/feed'] + $blog);
$log("2 blog listings: /blog /blog/UID, $perPage per page, with feeds");

// The audio module's default view: /audio, published audio, 10 teasers per page, oldest first.
$make('d6_audio', 'audio', ['path' => 'audio', 'title' => 'Audio', 'types' => ['audio'], 'published' => TRUE, 'items' => 10, 'view_mode' => 'teaser',
  'sorts' => [['created', 'ASC']], 'feed' => 'audio/feed']);
$log('audio listing: /audio');

// 3. Taxonomy term pages.
$tt = $cf->getEditable('views.view.taxonomy_term');
$tt->set('display.default.display_options.pager', $pager(10))
  ->set('display.default.display_options.sorts.created.order', 'ASC')
  ->set('display.feed_1.display_options.path', 'taxonomy/term/%/all/feed')
  ->save();
$log('taxonomy term pages: 10 per page, sticky first then oldest first; feed at taxonomy/term/%/all/feed');

// 4. The original's table views. Columns as the original rendered them (the baseline), with its field ids, so the
// sort links keep their URLs (triplog?order=field_date_value&sort=desc).
$field = fn(string $id, string $table, string $f, string $label, string $type, array $settings = [], bool $sortable = FALSE) => ['id' => $id,
  'table' => $table, 'field' => $f, 'entity_type' => 'node', 'entity_field' => $f, 'plugin_id' => 'field', 'relationship' => 'none',
  'group_type' => 'group', 'admin_label' => '', 'label' => $label, 'exclude' => FALSE, 'element_label_colon' => FALSE,
  'click_sort_column' => 'value', 'type' => $type, 'settings' => $settings];
$title = $field('title', 'node_field_data', 'title', 'Title', 'string', ['link_to_entity' => TRUE]);
$table = function (string $id, string $label, string $path, string $viewTitle, array $fields, string $default, array $o) use ($cf, $pager, $nodeField, $sort, $displays, $formats): void {
  $d6 = $displays[$o['d6']]['default'];
  $display = ['title' => $viewTitle, 'fields' => $fields, 'pager' => $o['items'] ? $pager($o['items']) : ['type' => 'none', 'options' => ['offset' => 0]],
    'style' => ['type' => 'table', 'options' => ['columns' => array_combine(array_keys($fields), array_keys($fields)), 'default' => $default,
      'info' => $default ? [$default => ['sortable' => TRUE, 'default_sort_order' => 'asc']] : [], 'order' => 'asc', 'sticky' => FALSE, 'empty_table' => FALSE]],
    'row' => ['type' => 'fields'], 'query' => ['type' => 'views_query', 'options' => []], 'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
    'cache' => ['type' => 'tag', 'options' => []], 'filters' => [], 'sorts' => [], 'arguments' => [], 'header' => [], 'empty' => []];
  $display['filters']['type'] = $nodeField('type', 'type', 'bundle', ['operator' => 'in', 'value' => array_combine($o['types'], $o['types']), 'group' => 1, 'expose' => ['operator' => '']]);
  if ($o['published'] ?? FALSE) {
    $display['filters']['status'] = $nodeField('status', 'status', 'boolean', ['operator' => '=', 'value' => '1', 'group' => 1, 'expose' => ['operator' => '']]);
  }
  if ($o['tids'] ?? []) {
    $display['filters']['tid'] = ['id' => 'tid', 'table' => 'taxonomy_index', 'field' => 'tid', 'plugin_id' => 'taxonomy_index_tid', 'relationship' => 'none',
      'group_type' => 'group', 'admin_label' => '', 'operator' => 'or', 'value' => array_combine($o['tids'], $o['tids']), 'group' => 1, 'exposed' => FALSE,
      'expose' => ['operator' => '', 'reduce' => FALSE], 'type' => 'select', 'limit' => FALSE, 'vid' => '', 'hierarchy' => FALSE, 'error_message' => TRUE];
  }
  foreach ($o['sorts'] ?? [] as [$f, $order]) {
    $display['sorts'][$f] = $sort($f, $order);
  }
  $text = fn($area, $html, $format) => ['id' => $area, 'table' => 'views', 'field' => 'area', 'plugin_id' => 'text', 'relationship' => 'none',
    'group_type' => 'group', 'admin_label' => '', 'empty' => TRUE, 'tokenize' => FALSE, 'content' => ['value' => $html, 'format' => $format]];
  if (!empty($d6['header'])) {
    $display['header']['area'] = $text('area', $d6['header'], $formats[(int) ($d6['header_format'] ?? 1)] ?? 'filtered_html');
  }
  if (!empty($d6['empty'])) {
    $display['empty']['area'] = $text('area', $d6['empty'], $formats[(int) ($d6['empty_format'] ?? 1)] ?? 'filtered_html');
  }
  if ($o['argument'] ?? NULL) {
    $display['arguments'][$o['argument']['id']] = $o['argument'];
  }
  $cf->getEditable("views.view.$id")->setData([
    'langcode' => 'en', 'status' => TRUE, 'dependencies' => ['module' => ['node', 'user', 'hobobiker']], 'id' => $id, 'label' => $label,
    'module' => 'views', 'description' => '', 'tag' => '', 'base_table' => 'node_field_data', 'base_field' => 'nid',
    'display' => [
      'default' => ['id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0, 'display_options' => $display],
      'page_1' => ['id' => 'page_1', 'display_title' => 'Page', 'display_plugin' => 'page', 'position' => 1, 'display_options' => ['path' => $path, 'display_extenders' => []]],
    ],
  ])->save();
};
$argBase = ['relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'default_action' => 'ignore', 'exception' => ['value' => 'all'],
  'title_enable' => FALSE, 'default_argument_type' => 'fixed', 'summary' => ['sort_order' => 'asc', 'number_of_records' => 0, 'format' => 'default_summary'],
  'specify_validation' => FALSE, 'validate' => ['type' => 'none', 'fail' => 'not found'], 'break_phrase' => FALSE];

// triplog: every day, 200 per page. With a date range (triplog/2003-01-15--2003-04-01) the original found nothing
// ("Nothing found..."): its argument never matched. Reproduced: any argument fails, and the empty text shows.
$table('d6_triplog', 'triplog', 'triplog', 'triplog', [
  'field_date_value' => $field('field_date_value', 'node__field_date', 'field_date', 'Date', 'datetime_custom', ['date_format' => 'j M Y']),
  'field_start' => $field('field_start', 'node__field_start', 'field_start', 'Start', 'basic_string'),
  'field_destination' => $field('field_destination', 'node__field_destination', 'field_destination', 'Destination', 'basic_string'),
  'field_miles' => $field('field_miles', 'node__field_miles', 'field_miles', 'Miles', 'number_integer', ['thousand_separator' => '', 'prefix_suffix' => FALSE]),
  'view_node' => ['id' => 'view_node', 'table' => 'node', 'field' => 'view_node', 'entity_type' => 'node', 'plugin_id' => 'entity_link', 'relationship' => 'none',
    'group_type' => 'group', 'admin_label' => '', 'label' => 'Details', 'exclude' => FALSE, 'text' => 'view', 'output_url_as_text' => FALSE, 'absolute' => FALSE],
], 'field_date_value', ['d6' => 'triplog', 'items' => 200, 'types' => ['triplog'], 'argument' => ['id' => 'null', 'table' => 'views', 'field' => 'null',
  'plugin_id' => 'null', 'must_not_be' => TRUE, 'specify_validation' => TRUE, 'validate' => ['type' => 'none', 'fail' => 'empty']] + $argBase]);

// wp (Wonderful People): 30 per page; a date range limits it (wp/2003-01-15--2003-04-01).
$table('d6_wp', 'wp', 'wp', 'Wonderful People', [
  'title' => ['label' => 'Name'] + $title,
  'field_date_0_value' => $field('field_date_0_value', 'node__field_date_0', 'field_date_0', 'WP Date', 'datetime_custom', ['date_format' => 'j F Y']),
  'body' => $field('body', 'node__body', 'body', 'Body', 'text_default'),
], 'field_date_0_value', ['d6' => 'wp', 'items' => 30, 'types' => ['wp'], 'argument' => ['id' => 'field_date_0_value', 'table' => 'node__field_date_0',
  'field' => 'field_date_0_value', 'plugin_id' => 'hobobiker_date_range'] + $argBase]);

// The Mexico route maps: the route nodes tagged 31, oldest first, no pager.
$table('d6_mexico_route', 'mexico_route', 'big_trip_mexico_route', 'Big trip Mexico Route Maps and Information', ['title' => $title], '',
  ['d6' => 'mexico_route', 'items' => 0, 'types' => array_keys(\Drupal\node\Entity\NodeType::loadMultiple()), 'published' => TRUE, 'tids' => ['31'], 'sorts' => [['created', 'ASC']]]);
$log('3 table views: /triplog /wp /big_trip_mexico_route');

\Drupal::service('router.builder')->rebuild();
