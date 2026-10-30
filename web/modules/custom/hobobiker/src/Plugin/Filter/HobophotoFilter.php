<?php

namespace Drupal\hobobiker\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * The D6 hobobiker_filter: [hobophoto:path=...,orientation=portrait|landscape,caption=...].
 *
 * Renders the original's markup (the hobo_golden theme's hobobiker-filter-photo template): the 240x180 (or
 * 180x240 for portrait) image style of the photo, linked to the full-size file for the lightbox, with the caption
 * as alt and title and, when there is one, as a line under the photo.
 */
#[Filter(
  id: 'hobophoto',
  title: new \Drupal\Core\StringTranslation\TranslatableMarkup('Hobobiker photo macro [hobophoto:]'),
  type: FilterInterface::TYPE_MARKUP_LANGUAGE,
)]
class HobophotoFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult(preg_replace_callback('/\[hobophoto:(.+?)\]/', [static::class, 'photo'], $text));
    return $result->addCacheTags(['config:image.style.240x180', 'config:image.style.180x240']);
  }

  /**
   * D6 hobobiker_filter_split_config(): name=value pairs, values limited to the same characters.
   */
  public static function splitConfig(string $string): array {
    $config = ['path' => '', 'orientation' => 'landscape', 'caption' => ''];
    preg_match_all('/([a-zA-Z]+)=([-@0-9a-zA-Z:;\/_. ]+)/', str_replace(',', "\n", $string), $parts, PREG_SET_ORDER);
    foreach ($parts as $part) {
      $config[strtolower(trim($part[1]))] = trim($part[2]);
    }
    return $config;
  }

  /**
   * One photo, as the original's template printed it.
   */
  public static function photo(array $matches): string {
    $config = self::splitConfig($matches[1]);
    $path = $config['path'];
    $caption = Html::escape($config['caption']);
    $style = ImageStyle::load($config['orientation'] === 'portrait' ? '180x240' : '240x180');
    $src = Html::escape($style->buildUrl('public://' . preg_replace('@^sites/default/files/@', '', $path)));
    $rel = "lightbox[group1][$caption]";
    $img = "<div><img src=\"$src\" alt=\"$caption\" title=\"$caption\" rel=\"$rel\" />" . ($caption !== '' ? "<br />$caption<br />" : '') . '</div>';
    return "<div class=\"flickrfloat\"><div class=\"flickr-img-wrapper\">
        <a rel=\"$rel\" href=\"/" . Html::escape($path) . "\">
        $img        </a>
        </div>
</div>";
  }

}
