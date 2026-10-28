<?php

namespace Drupal\hobobiker\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * The home page: the Drupal 6 Panels page "trip_wrapped_up", in core's three-column layout.
 *
 * The panes (config hobobiker.front_page, written by scripts/60-front-page.php from the original's panels_pane)
 * are node teasers, the search form and custom blocks, each in its region.
 */
class FrontPageController extends ControllerBase {

  public function page(): array {
    $regions = [];
    $config = $this->config('hobobiker.front_page');
    foreach ($config->get('panes') ?? [] as $pane) {
      $content = match ($pane['type']) {
        // Panels moved a node's title into the pane title and left the node's own title link empty.
        'node' => ($node = $this->entityTypeManager()->getStorage('node')->load($pane['id']))
          ? ['#hobobiker_pane' => TRUE] + $this->entityTypeManager()->getViewBuilder('node')->view($node, 'teaser') : NULL,
        'search' => $this->formBuilder()->getForm('Drupal\search\Form\SearchBlockForm'),
        'block_content' => ($blocks = $this->entityTypeManager()->getStorage('block_content')->loadByProperties(['info' => $pane['id']]))
          ? $this->entityTypeManager()->getViewBuilder('block_content')->view(reset($blocks)) : NULL,
        default => NULL,
      };
      if ($content && $pane['type'] === 'node') {
        $pane['title'] = $node->label();
      }
      if ($content) {
        $regions[$pane['region']][] = ['#type' => 'container', '#attributes' => ['class' => ['panel-pane']],
          'title' => $pane['title'] ? ['#markup' => '<h2 class="pane-title">' . htmlspecialchars($pane['title']) . '</h2>'] : [],
          'content' => $content];
      }
    }
    $layout = \Drupal::service('plugin.manager.core.layout')->createInstance('layout_threecol_25_50_25');
    $build = $layout->build($regions);
    $build['#cache']['tags'] = array_merge($config->getCacheTags(), ['node_list', 'block_content_list']);
    return $build;
  }

}
