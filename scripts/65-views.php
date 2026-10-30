<?php

/**
 * @file
 * Step 65: the listing pages. The D6 Views (page displays) become Drupal 11 views, plus the D6 blog module's pages.
 *
 * Each D6 view page is translated from its stored definition (the page display over the default display): path,
 * title, header/empty text and format, pager, style (node rows or a table of fields), filters, sorts and arguments.
 * Handlers this site uses are translated; a view needing one that is not (the archive's year/month summary,
 * statistics counters) is skipped and named. The D6 blog module's pages (blog, blog/UID) and the taxonomy term page
 * (D6 view taxonomy_term, which replaces core's) are views too. Runs before step 70, so the aliases of these
 * paths (blog/rfay, podcasts, ...) exist. Each run deletes and recreates the views it makes (ids hobo_*).
 * Run: ddev drush php:script scripts/65-views.php
 */

use Drupal\views\Entity\View;

require __DIR__ . '/lib.php';

\Drupal::service('module_installer')->install(['views', 'hobobiker']);

// The timezone D6's date filters read dates in: the date module's site timezone, which is also the PHP timezone
// the original runs with (its "Posted on" dates use a fixed UTC-6 instead; see step 90).
define('HOBO_D6_TIMEZONE', unserialize(d6()->query("SELECT value FROM {variable} WHERE name = 'date_default_timezone_name'")->fetchField(), ['allowed_classes' => FALSE]));

// D6 pager labels.
const HOBO_PAGER_TAGS = ['first' => '« first', 'previous' => '‹ previous', 'next' => 'next ›', 'last' => 'last »'];

$skipped = [];

/**
 * A field on node_field_data, or a configurable field's table.
 */
function hobo_views_field(string $id, string $field, string $formatter, array $settings, string $label, bool $exclude = FALSE): array {
  $base = in_array($field, ['title', 'type', 'created', 'uid'], TRUE);
  return [
    'id' => $id,
    'table' => $base ? 'node_field_data' : "node__$field",
    'field' => $field,
    'plugin_id' => 'field',
    'entity_type' => 'node',
    'entity_field' => $field,
    'label' => $label,
    'exclude' => $exclude,
    'element_label_colon' => FALSE,
    'type' => $formatter,
    'settings' => $settings,
  ];
}

/**
 * D6 field handler => Drupal 11, or NULL.
 */
function hobo_views_d6_field(string $id, array $h): ?array {
  $label = $h['label'] ?? '';
  // A CCK field's label: its own (label_type "widget", Views 2's default) unless set to a custom one.
  if (str_starts_with($h['table'], 'node_data_field_') && ($h['label_type'] ?? 'widget') === 'widget') {
    $field_name = preg_replace('/_value$/', '', $h['field']);
    foreach (['triplog', 'wp'] as $bundle) {
      $label = \Drupal\field\Entity\FieldConfig::loadByName('node', $bundle, $field_name)?->getLabel() ?? $label;
    }
  }
  $exclude = !empty($h['exclude']);
  return match ($h['table'] . '.' . $h['field']) {
    'node.title' => hobo_views_field('title', 'title', 'string', ['link_to_entity' => !empty($h['link_to_node'])], $label, $exclude),
    'node_revisions.body' => hobo_views_field('body', 'body', 'text_default', [], $label, $exclude),
    'node_data_field_date.field_date_value' => hobo_views_field('field_date', 'field_date', 'datetime_custom', ['date_format' => 'j M Y'], $label, $exclude),
    'node_data_field_date_0.field_date_0_value' => hobo_views_field('field_date_0', 'field_date_0', 'datetime_custom', ['date_format' => 'j F Y'], $label, $exclude),
    'node_data_field_start.field_start_value' => hobo_views_field('field_start', 'field_start', 'string', [], $label, $exclude),
    'node_data_field_destination.field_destination_value' => hobo_views_field('field_destination', 'field_destination', 'string', [], $label, $exclude),
    'node_data_field_miles.field_miles_value' => hobo_views_field('field_miles', 'field_miles', 'number_integer', ['thousand_separator' => ''], $label, $exclude),
    'node.view_node' => ['id' => 'view_node', 'table' => 'node', 'field' => 'view_node', 'plugin_id' => 'entity_link', 'entity_type' => 'node',
      'label' => $label, 'text' => $h['text'] ?? 'view', 'exclude' => $exclude, 'element_label_colon' => FALSE],
    default => NULL,
  };
}

/**
 * D6 filter handler => Drupal 11, or NULL.
 */
function hobo_views_d6_filter(string $id, array $h): ?array {
  $boolean = fn(string $field) => ['id' => $field, 'table' => 'node_field_data', 'field' => $field, 'plugin_id' => 'boolean',
    'entity_type' => 'node', 'entity_field' => $field, 'operator' => '=', 'value' => (string) (int) $h['value']];
  switch ($h['table'] . '.' . $h['field']) {
    case 'node.type':
      return ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'plugin_id' => 'bundle', 'entity_type' => 'node',
        'entity_field' => 'type', 'operator' => 'in', 'value' => array_combine(array_keys($h['value']), array_keys($h['value']))];
    case 'node.status':
    case 'node.promote':
      return $boolean($h['field']);
    case 'node.status_extra':
      return ['id' => 'status_extra', 'table' => 'node_field_data', 'field' => 'status_extra', 'plugin_id' => 'node_status', 'entity_type' => 'node'];
    case 'term_node.tid':
      // The filter names a vocabulary (for its config dependency): its terms' (all of one vocabulary here).
      $tids = array_values(array_filter($h['value']));
      $vids = array_unique(array_map(fn($tid) => \Drupal\taxonomy\Entity\Term::load($tid)?->bundle(), $tids));
      if (count($vids) !== 1 || !reset($vids)) {
        return NULL;
      }
      return ['id' => 'tid', 'table' => 'taxonomy_index', 'field' => 'tid', 'plugin_id' => 'taxonomy_index_tid', 'operator' => 'or',
        'value' => array_combine($tids, $tids), 'type' => 'textfield', 'limit' => TRUE, 'vid' => reset($vids), 'reduce_duplicates' => TRUE];
    case 'node.created':
      // views' date filter: between two dates (midnight at each end), read in the original's PHP timezone.
      return ['id' => 'created', 'table' => 'node_field_data', 'field' => 'created', 'plugin_id' => 'date', 'entity_type' => 'node',
        'entity_field' => 'created', 'operator' => 'between', 'value' => ['min' => $h['value']['min'] . ' ' . HOBO_D6_TIMEZONE,
          'max' => $h['value']['max'] . ' ' . HOBO_D6_TIMEZONE, 'value' => '', 'type' => 'date']];
    case 'node.date_filter':
      // The date module's filter on node.created at day granularity: whole days, both ends included.
      if (($h['date_fields'] ?? []) !== ['node.created' => 'node.created'] || ($h['operator'] ?? '') !== 'between') {
        return NULL;
      }
      return ['id' => 'created', 'table' => 'node_field_data', 'field' => 'created', 'plugin_id' => 'date', 'entity_type' => 'node',
        'entity_field' => 'created', 'operator' => 'between', 'value' => ['min' => substr($h['value']['min'], 0, 10) . ' 00:00:00 ' . HOBO_D6_TIMEZONE,
          'max' => substr($h['value']['max'], 0, 10) . ' 23:59:59 ' . HOBO_D6_TIMEZONE, 'value' => '', 'type' => 'date']];
  }
  return NULL;
}

/**
 * D6 sort handler => Drupal 11, or NULL.
 */
function hobo_views_d6_sort(string $id, array $h): ?array {
  $order = strtoupper($h['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
  return match ($h['table'] . '.' . $h['field']) {
    'node.created' => ['id' => 'created', 'table' => 'node_field_data', 'field' => 'created', 'plugin_id' => 'date', 'entity_type' => 'node',
      'entity_field' => 'created', 'order' => $order, 'granularity' => 'second'],
    'node.sticky' => ['id' => 'sticky', 'table' => 'node_field_data', 'field' => 'sticky', 'plugin_id' => 'standard', 'entity_type' => 'node',
      'entity_field' => 'sticky', 'order' => $order],
    'node_data_field_date.field_date_value' => ['id' => 'field_date_value', 'table' => 'node__field_date', 'field' => 'field_date_value',
      'plugin_id' => 'datetime', 'order' => $order, 'granularity' => 'second'],
    'node_data_field_date_0.field_date_0_value' => ['id' => 'field_date_0_value', 'table' => 'node__field_date_0', 'field' => 'field_date_0_value',
      'plugin_id' => 'datetime', 'order' => $order, 'granularity' => 'second'],
    default => NULL,
  };
}

/**
 * D6 argument handler => Drupal 11 ([] for one to drop), or NULL.
 */
function hobo_views_d6_argument(string $id, array $h): ?array {
  if ($h['field'] === 'term_node_tid_depth_modifier') {
    return [];
  }
  if ($h['field'] === 'term_node_tid_depth') {
    return ['id' => 'term_node_tid_depth', 'table' => 'node_field_data', 'field' => 'term_node_tid_depth', 'plugin_id' => 'taxonomy_index_tid_depth',
      'entity_type' => 'node', 'depth' => (int) $h['depth'], 'break_phrase' => !empty($h['break_phrase']), 'default_action' => 'not found',
      'title_enable' => TRUE, 'title' => '{{ arguments.term_node_tid_depth }}', 'specify_validation' => TRUE,
      'validate' => ['type' => 'entity:taxonomy_term', 'fail' => 'not found'],
      'validate_options' => ['bundles' => [], 'access' => TRUE, 'operation' => 'view', 'multiple' => 0]];
  }
  if ($h['field'] === 'date_argument') {
    $table = match (array_key_first($h['date_fields'] ?? [])) {
      'node.created' => ['node_field_data', 'created_range'],
      'node_data_field_date.field_date_value' => ['node__field_date', 'field_date_range'],
      'node_data_field_date_0.field_date_0_value' => ['node__field_date_0', 'field_date_0_range'],
      default => NULL,
    };
    if (!$table || ($h['granularity'] ?? 'day') !== 'day') {
      return NULL;
    }
    return ['id' => $table[1], 'table' => $table[0], 'field' => $table[1], 'plugin_id' => 'hobobiker_date_range',
      'default_action' => ($h['default_action'] ?? 'ignore') === 'ignore' ? 'ignore' : 'not found',
      'year_range' => $h['year_range'] ?? '-3:+3', 'timezone' => HOBO_D6_TIMEZONE];
  }
  return NULL;
}

/**
 * A text area (header, footer, empty text) in a Drupal 11 format.
 */
function hobo_views_area(string $id, string $text, int $format, bool $empty): array {
  return [$id => ['id' => $id, 'table' => 'views', 'field' => 'area', 'plugin_id' => 'text', 'empty' => $empty,
    'content' => ['value' => $text, 'format' => HOBO_FORMATS[$format] ?? 'full_html_no_restrictions']]];
}

/**
 * Creates (or recreates) a page view.
 */
function hobo_views_save(string $id, string $label, string $path, array $options, ?array $feed = NULL): void {
  View::load($id)?->delete();
  $options += [
    'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
    'cache' => ['type' => 'none', 'options' => []],
    'query' => ['type' => 'views_query', 'options' => ['disable_sql_rewrite' => FALSE, 'distinct' => FALSE]],
    'exposed_form' => ['type' => 'basic', 'options' => []],
  ];
  View::create([
    'id' => $id,
    'label' => $label,
    'module' => 'views',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => ['id' => 'default', 'display_plugin' => 'default', 'display_title' => 'Default', 'position' => 0, 'display_options' => $options],
      'page_1' => ['id' => 'page_1', 'display_plugin' => 'page', 'display_title' => 'Page', 'position' => 1, 'display_options' => ['path' => $path]],
    ] + ($feed ? ['feed_1' => ['id' => 'feed_1', 'display_plugin' => 'feed', 'display_title' => 'Feed', 'position' => 2, 'display_options' => $feed + [
      'displays' => ['page_1' => 'page_1'],
      'style' => ['type' => 'rss', 'options' => []],
      'row' => ['type' => 'node_rss', 'options' => ['view_mode' => 'teaser']],
      'pager' => ['type' => 'some', 'options' => ['items_per_page' => 10, 'offset' => 0]],
    ]]] : []),
  ])->save();
}

/**
 * The pager D6 used: full (9 links) with D6's labels, or a fixed number of items.
 */
function hobo_views_pager($use_pager, int $per_page): array {
  $options = ['items_per_page' => $per_page, 'offset' => 0];
  if ($use_pager && $use_pager !== '0') {
    return ['type' => $use_pager === 'mini' ? 'mini' : 'full', 'options' => $options + ['id' => 0, 'quantity' => 9, 'tags' => HOBO_PAGER_TAGS]];
  }
  return ['type' => $per_page ? 'some' : 'none', 'options' => $options];
}

// D6 views listing whole nodes showed them without comments (the comment module was off): a view mode for that,
// the full display without its comments (whose pager would also share the page's pager query).
if (!\Drupal\Core\Entity\Entity\EntityViewMode::load('node.hobo_list_full')) {
  \Drupal\Core\Entity\Entity\EntityViewMode::create(['id' => 'node.hobo_list_full', 'label' => 'Full content in lists', 'targetEntityType' => 'node'])->save();
}
$display_repository = \Drupal::service('entity_display.repository');
foreach (array_keys(\Drupal\node\Entity\NodeType::loadMultiple()) as $bundle) {
  $list = $display_repository->getViewDisplay('node', $bundle, 'hobo_list_full');
  foreach ($display_repository->getViewDisplay('node', $bundle)->getComponents() as $name => $component) {
    $list->setComponent($name, $component);
  }
  $list->removeComponent('comment')->setStatus(TRUE)->save();
}

// The D6 Views.
$made = [];
$d6_views = d6()->query('SELECT vid, name FROM {views_view} ORDER BY name')->fetchAllKeyed();
foreach ($d6_views as $vid => $name) {
  $displays = d6()->query('SELECT id, display_plugin, display_options FROM {views_display} WHERE vid = :vid ORDER BY position', [':vid' => $vid])->fetchAll();
  $default = unserialize($displays[0]->display_options, ['allowed_classes' => FALSE]);
  foreach ($displays as $display) {
    if ($display->display_plugin !== 'page') {
      continue;
    }
    $o = unserialize($display->display_options, ['allowed_classes' => FALSE]) + $default;
    $unsupported = [];
    $options = ['title' => $o['title'] ?? ''];

    foreach (['filters' => 'hobo_views_d6_filter', 'sorts' => 'hobo_views_d6_sort', 'arguments' => 'hobo_views_d6_argument'] as $key => $translate) {
      $options[$key] = [];
      foreach ((array) ($o[$key] ?? []) as $handler_id => $handler) {
        $d11 = $translate($handler_id, $handler);
        if ($d11 === NULL) {
          $unsupported[] = "$key:{$handler['table']}.{$handler['field']}";
        }
        elseif ($d11) {
          $options[$key][$d11['id']] = $d11;
        }
      }
    }

    $style = $o['style_plugin'] ?? 'default';
    if ($style === 'table') {
      $options['fields'] = [];
      foreach ((array) ($o['fields'] ?? []) as $handler_id => $handler) {
        $d11 = hobo_views_d6_field($handler_id, $handler);
        if ($d11 === NULL) {
          $unsupported[] = "fields:{$handler['table']}.{$handler['field']}";
          continue;
        }
        $options['fields'][$d11['id']] = $d11;
      }
      $columns = array_combine(array_keys($options['fields']), array_keys($options['fields']));
      $sortable = [];
      foreach ((array) ($o['style_options']['info'] ?? []) as $d6_field => $info) {
        $d11 = hobo_views_d6_field($d6_field, $o['fields'][$d6_field] ?? ['table' => '', 'field' => '']);
        if ($d11 && !empty($info['sortable'])) {
          $sortable[$d11['id']] = ['sortable' => TRUE, 'default_sort_order' => 'asc'];
        }
      }
      $default_column = $o['style_options']['default'] ?? '';
      $default_d11 = $default_column ? hobo_views_d6_field($default_column, $o['fields'][$default_column] ?? ['table' => '', 'field' => ''])['id'] ?? '' : '';
      $options['style'] = ['type' => 'table', 'options' => ['columns' => $columns, 'info' => $sortable, 'default' => $default_d11 ?: '-1',
        'order' => $o['style_options']['order'] ?? 'asc', 'override' => !empty($o['style_options']['override']), 'sticky' => FALSE, 'empty_table' => FALSE]];
      // Rows tied on the table's sort (two Wonderful People on one day) came in node order on D6, where MySQL read
      // them by primary key; Drupal 11's query has no such order, so it is made explicit.
      $options['sorts']['nid'] = ['id' => 'nid', 'table' => 'node_field_data', 'field' => 'nid', 'plugin_id' => 'standard',
        'entity_type' => 'node', 'entity_field' => 'nid', 'order' => 'ASC'];
      $options['row'] = ['type' => 'fields', 'options' => []];
    }
    elseif (in_array($style, ['default', 'list'], TRUE) && ($o['row_plugin'] ?? 'node') === 'node') {
      $options['style'] = ['type' => $style === 'list' ? 'html_list' : 'default', 'options' => []];
      $options['row'] = ['type' => 'entity:node', 'options' => ['view_mode' => !empty($o['row_options']['teaser']) ? 'teaser' : 'hobo_list_full']];
      $options['fields'] = [];
    }
    else {
      $unsupported[] = "style:$style/" . ($o['row_plugin'] ?? '');
    }

    $options['pager'] = hobo_views_pager($o['use_pager'] ?? FALSE, (int) ($o['items_per_page'] ?? 10));
    $options['header'] = !empty($o['header']) ? hobo_views_area('area', $o['header'], (int) ($o['header_format'] ?? 3), !empty($o['header_empty'])) : [];
    $options['footer'] = !empty($o['footer']) ? hobo_views_area('area', $o['footer'], (int) ($o['footer_format'] ?? 3), !empty($o['footer_empty'])) : [];
    $options['empty'] = !empty($o['empty']) ? hobo_views_area('area', $o['empty'], (int) ($o['empty_format'] ?? 3), TRUE) : [];

    $id = 'hobo_' . substr(trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_'), 0, 27);
    if ($unsupported) {
      View::load($id)?->delete();
      $skipped[] = "$name (" . implode(', ', array_unique($unsupported)) . ')';
      continue;
    }
    $feed = NULL;
    if ($name === 'taxonomy_term') {
      // D6's feed display: taxonomy/term/TID/DEPTH/feed, linked from the page as DEPTH "all".
      $feed = ['path' => 'taxonomy/term/%/%/feed', 'defaults' => ['arguments' => FALSE], 'arguments' => $options['arguments'] + [
        'term_node_tid_depth_modifier' => ['id' => 'term_node_tid_depth_modifier', 'table' => 'node_field_data', 'field' => 'term_node_tid_depth_modifier',
          'plugin_id' => 'taxonomy_index_tid_depth_modifier', 'entity_type' => 'node', 'default_action' => 'ignore', 'exception' => ['value' => 'all']],
      ]];
    }
    hobo_views_save($id, "D6: $name", $o['path'], $options, $feed);
    $made[] = $o['path'];
  }
}

// The D6 blog module's pages: all blog entries, and one author's (title "NAME's blog"), newest first, 10 a page.
$blog = [
  'filters' => [
    'type' => hobo_views_d6_filter('type', ['table' => 'node', 'field' => 'type', 'value' => ['blog' => 'blog']]),
    'status' => hobo_views_d6_filter('status', ['table' => 'node', 'field' => 'status', 'value' => 1]),
  ],
  'sorts' => [
    'sticky' => hobo_views_d6_sort('sticky', ['table' => 'node', 'field' => 'sticky', 'order' => 'DESC']),
    'created' => hobo_views_d6_sort('created', ['table' => 'node', 'field' => 'created', 'order' => 'DESC']),
  ],
  'style' => ['type' => 'default', 'options' => []],
  'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser']],
  'fields' => [],
  // The blog module paged by default_nodes_main.
  'pager' => hobo_views_pager('1', (int) unserialize(d6()->query("SELECT value FROM {variable} WHERE name = 'default_nodes_main'")->fetchField(), ['allowed_classes' => FALSE])),
];
hobo_views_save('hobo_blog', 'D6: blog module, all blogs', 'blog', ['title' => 'Blogs', 'arguments' => []] + $blog, ['path' => 'blog/feed', 'title' => 'RSS - blogs']);
hobo_views_save('hobo_blog_user', "D6: blog module, one author's blog", 'blog/%', ['title' => '', 'arguments' => [
  'uid' => ['id' => 'uid', 'table' => 'node_field_data', 'field' => 'uid', 'plugin_id' => 'user_uid', 'entity_type' => 'node',
    'entity_field' => 'uid', 'default_action' => 'not found', 'title_enable' => TRUE, 'title' => "{{ arguments.uid }}'s blog",
    'specify_validation' => TRUE, 'validate' => ['type' => 'entity:user', 'fail' => 'not found'],
    'validate_options' => ['access' => FALSE, 'operation' => 'view', 'multiple' => 0, 'restrict_roles' => FALSE, 'roles' => []]],
]] + $blog, ['path' => 'blog/%/feed', 'defaults' => ['title' => FALSE], 'title' => "RSS - {{ arguments.uid }}'s blog"]);
array_push($made, 'blog', 'blog/%');

// The audio module's default view "audio" (in its code, not the D6 database): published audio, oldest first,
// 10 a page as teasers, and its feed.
hobo_views_save('hobo_audio', 'D6: audio module, audio', 'audio', [
  'title' => 'Audio',
  'arguments' => [],
  'filters' => [
    'type' => hobo_views_d6_filter('type', ['table' => 'node', 'field' => 'type', 'value' => ['audio' => 'audio']]),
    'status' => hobo_views_d6_filter('status', ['table' => 'node', 'field' => 'status', 'value' => 1]),
  ],
  'sorts' => ['created' => hobo_views_d6_sort('created', ['table' => 'node', 'field' => 'created', 'order' => 'ASC'])],
  'style' => ['type' => 'default', 'options' => []],
  'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser']],
  'fields' => [],
  'pager' => hobo_views_pager('1', 10),
], ['path' => 'audio/feed', 'title' => 'Recent audio']);
$made[] = 'audio';

// Ours answers taxonomy/term/%, as the D6 view did; core's would compete for the path.
$core_term = View::load('taxonomy_term');
$core_term?->setStatus(FALSE)->save();

\Drupal::service('router.builder')->rebuild();
printf("D6 view pages %d: made %d, skipped %d; blog and audio module pages 3; views on site %d (hobo_*), core taxonomy_term view %s\n",
  count($made) - 3 + count($skipped), count($made) - 3, count($skipped),
  count(array_filter(array_keys(View::loadMultiple()), fn($v) => str_starts_with($v, 'hobo_'))), $core_term && !$core_term->status() ? 'disabled' : 'on');
echo 'paths: ' . implode(', ', $made) . "\n";
echo 'skipped: ' . ($skipped ? implode('; ', $skipped) : 'none') . "\n";
