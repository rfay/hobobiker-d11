<?php

namespace Drupal\hobobiker\Plugin\views\argument;

use Drupal\hobobiker\TripInfo;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * A date range argument, "2003-01-15--2003-04-01": dates from the first to the last day, both included.
 *
 * The Drupal 6 date module's argument at day granularity, as the original's wp/ (Wonderful People) pages used it.
 */
#[ViewsArgument(
  id: 'hobobiker_date_range',
)]
class DateRange extends ArgumentPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    [$from, $to] = array_pad(explode('--', (string) $this->argument, 2), 2, NULL);
    $to ??= $from;
    $this->query->addWhere(0, "$this->tableAlias.$this->realField", [TripInfo::validateDate($from), TripInfo::validateDate($to)], 'BETWEEN');
  }

}
