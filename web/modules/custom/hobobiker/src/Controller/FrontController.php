<?php

namespace Drupal\hobobiker\Controller;

use Drupal\node\Entity\Node;

/**
 * The front page, trip_wrapped_up: the D6 Panel (layout threecol_25_50_25_stacked) and its 6 panes.
 *
 * Top: the search form. Left: node 4281 (Our Favorite Stories). Middle: nodes 4344 and 4018. Right: the trip
 * statistics (D6 block 14, titled "Statistics"). Nodes show as teasers under their title, as the Panel did.
 */
class FrontController {

  /**
   * A Panel pane: its title, then its content.
   */
  protected function pane(string $class, string $title, array $content): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['panel-pane', $class]],
      'title' => ['#markup' => '<h2 class="pane-title">' . htmlspecialchars($title) . '</h2>'],
      'content' => ['#type' => 'container', '#attributes' => ['class' => ['pane-content']], 'inner' => $content],
    ];
  }

  /**
   * A node pane: the node's teaser under its title.
   */
  protected function node(int $nid): array {
    $node = Node::load($nid);
    $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'teaser');
    return $this->pane('pane-node', $node->label(), $build);
  }

  /**
   * A block plugin's output.
   */
  protected function block(string $plugin_id): array {
    return \Drupal::service('plugin.manager.block')->createInstance($plugin_id, [])->build();
  }

  /**
   * The page.
   */
  public function page(): array {
    $column = fn(string $class, array $panes) => ['#type' => 'container', '#attributes' => ['class' => ['panel-panel', $class]], 'inside' => ['#type' => 'container', '#attributes' => ['class' => ['inside']]] + $panes];
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['panel-display', 'panel-3col-stacked', 'clear-block']],
      'top' => $column('panel-col-top', ['search' => $this->pane('pane-search-0', 'Search', $this->block('search_form_block'))]),
      'center' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['center-wrapper']],
        'first' => $column('panel-col-first', ['n4281' => $this->node(4281)]),
        'middle' => $column('panel-col', ['n4344' => $this->node(4344), 'n4018' => $this->node(4018)]),
        'last' => $column('panel-col-last', ['stats' => $this->pane('pane-block-14', 'Statistics', $this->block('hobobiker_trip_stats'))]),
      ],
    ];
  }

}
