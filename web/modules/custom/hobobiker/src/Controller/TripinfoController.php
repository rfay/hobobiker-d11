<?php

namespace Drupal\hobobiker\Controller;

use Drupal\Core\Render\Markup;
use Drupal\hobobiker\Tripinfo;

/**
 * The D6 tripinfo module's pages: tripinfo/START/END and tripinfo_item/DATE/START/END.
 */
class TripinfoController {

  /**
   * The trip table for a range of dates.
   */
  public function list(string $startdate, string $enddate): array {
    return [
      '#markup' => Markup::create(Tripinfo::listPage($startdate, $enddate)),
      '#cache' => ['tags' => ['node_list:triplog']],
    ];
  }

  /**
   * One day's ride: map, description, distances, downloads, elevation profile, previous/next.
   */
  public function item(string $date, string $startdate, string $enddate): array {
    [$markup, $map] = Tripinfo::item($date, $startdate, $enddate);
    $build = [
      '#markup' => Markup::create($markup),
      '#cache' => ['tags' => ['node_list:triplog']],
    ];
    if ($map) {
      $build['#attached']['library'][] = 'hobobiker/tripinfo_map';
    }
    return $build;
  }

}
