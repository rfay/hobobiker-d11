<?php

namespace Drupal\hobobiker\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for hobobiker.
 */
class HobobikerHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'hobobiker_photo' => [
        'variables' => ['href' => NULL, 'src' => NULL, 'caption' => '', 'rel' => ''],
      ],
    ];
  }

  /**
   * Implements hook_node_view_alter().
   *
   * A teaser shorter than its body (it has a summary, the original's stored teaser) ends with the read-more link,
   * inside its last paragraph.
   */
  #[Hook('node_view_alter')]
  public function nodeViewAlter(array &$build, \Drupal\Core\Entity\EntityInterface $node, \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display): void {
    if ($build['#view_mode'] === 'teaser' && isset($build['body'][0]) && $node->hasField('body') && (string) $node->get('body')->summary !== '') {
      $build['body'][0]['#hobobiker_readmore'] = $node->toUrl()->toString();
      $build['body'][0]['#post_render'][] = [\Drupal\hobobiker\ReadMore::class, 'insert'];
    }
  }

  /**
   * Implements hook_views_query_alter().
   *
   * The original's tables sorted by date alone, and MySQL returned rows with the same date in table order (lower
   * id first), which decides who lands on which page. The same tie-break, after every other sort.
   */
  #[Hook('views_query_alter')]
  public function viewsQueryAlter(\Drupal\views\ViewExecutable $view, \Drupal\views\Plugin\views\query\QueryPluginBase $query): void {
    if (in_array($view->id(), ['d6_wp', 'd6_triplog'], TRUE)) {
      $query->addOrderBy('node_field_data', 'nid', 'ASC');
    }
  }

  /**
   * Implements hook_views_data_alter().
   *
   * The trip dates take a date range argument ("2003-01-15--2003-04-01"), as on the original.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    foreach (['field_date', 'field_date_0'] as $field) {
      if (isset($data["node__$field"]["{$field}_value"])) {
        $data["node__$field"]["{$field}_value"]['argument']['id'] = 'hobobiker_date_range';
      }
    }
  }

}
