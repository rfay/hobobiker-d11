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
   * inside its last paragraph. Audio nodes get the audio module's lines, the poll its results.
   */
  #[Hook('node_view_alter')]
  public function nodeViewAlter(array &$build, \Drupal\Core\Entity\EntityInterface $node, \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display): void {
    if ($build['#view_mode'] === 'teaser' && isset($build['body'][0]) && $node->hasField('body') && (string) $node->get('body')->summary !== '') {
      $build['body'][0]['#hobobiker_readmore'] = $node->toUrl()->toString();
      $build['body'][0]['#post_render'][] = [\Drupal\hobobiker\ReadMore::class, 'insert'];
    }
    // Audio nodes, as the audio module showed them: the tag list on the page, the length in a teaser, then the
    // download link and counts.
    if ($node->bundle() === 'audio' && $node->hasField('field_audio_file')) {
      $info = $build['#view_mode'] === 'full' ? \Drupal\hobobiker\Audio::info($node) : '<div class="audio-node teaser">' . htmlspecialchars(\Drupal\hobobiker\Audio::length($node)) . '</div>';
      $build['hobobiker_audio'] = ['#markup' => \Drupal\Core\Render\Markup::create($info . \Drupal\hobobiker\Audio::links($node)), '#weight' => 50];
    }
    // The poll (closed on the original): its results, as the poll module showed them.
    if ($node->bundle() === 'poll' && $build['#view_mode'] === 'full' && $node->hasField('field_poll_choices')) {
      $votes = array_map('intval', array_column($node->get('field_poll_votes')->getValue(), 'value'));
      $total = array_sum($votes);
      $html = '<div class="poll">';
      foreach ($node->get('field_poll_choices')->getValue() as $i => $choice) {
        $n = $votes[$i] ?? 0;
        $percent = $total ? round($n * 100 / $total) : 0;
        $html .= '<div class="text">' . \Drupal\Component\Utility\Html::escape($choice['value']) . "</div>\n<div class=\"bar\"><div style=\"width: $percent%;\" class=\"foreground\"></div></div>\n"
          . "<div class=\"percent\">$percent% (" . ($n === 1 ? '1 vote' : "$n votes") . ")</div>\n";
      }
      $build['hobobiker_poll'] = ['#markup' => \Drupal\Core\Render\Markup::create($html . "<div class=\"total\">Total votes: $total</div></div>"), '#weight' => 50];
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
