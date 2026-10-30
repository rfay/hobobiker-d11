<?php

namespace Drupal\hobobiker\Plugin\Filter;

use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\hobobiker\Tripinfo;

/**
 * [tripinfo_table:START,END]: the trip table, in place of the stored PHP print tripinfo_table('START','END').
 *
 * The owner's rule is that no stored PHP runs; scripts/40-nodes.php rewrote the 17 PHP blocks to this macro.
 */
#[Filter(
  id: 'tripinfo_table',
  title: new \Drupal\Core\StringTranslation\TranslatableMarkup('Trip table macro [tripinfo_table:START,END]'),
  type: FilterInterface::TYPE_MARKUP_LANGUAGE,
)]
class TripinfoTableFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $text = preg_replace_callback('/\[tripinfo_table:([0-9-]+),([0-9-]+)\]/', fn($m) => Tripinfo::table($m[1], $m[2]), $text);
    return (new FilterProcessResult($text))->addCacheTags(['node_list:triplog']);
  }

}
