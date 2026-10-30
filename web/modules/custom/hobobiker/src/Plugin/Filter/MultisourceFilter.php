<?php

namespace Drupal\hobobiker\Plugin\Filter;

use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\hobobiker\Tripinfo;

/**
 * The D6 tripinfo filter, [multisource:URL, ...]: a Leaflet map of the first KML/KMZ source.
 *
 * D6 emitted PHP for the PHP filter to add the map's JavaScript; here the library is attached instead.
 */
#[Filter(
  id: 'multisource',
  title: new \Drupal\Core\StringTranslation\TranslatableMarkup('Trip map macro [multisource:]'),
  type: FilterInterface::TYPE_MARKUP_LANGUAGE,
)]
class MultisourceFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $count = 0;
    $text = preg_replace_callback('/\[multisource:(.+?)\]/', fn($m) => Tripinfo::multisource($m[1]), $text, -1, $count);
    $result = new FilterProcessResult($text);
    if ($count) {
      $result->addAttachments(['library' => ['hobobiker/tripinfo_map']]);
    }
    return $result;
  }

}
