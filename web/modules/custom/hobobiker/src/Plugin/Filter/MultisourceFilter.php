<?php

namespace Drupal\hobobiker\Plugin\Filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\hobobiker\TripInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders [multisource:URL,...] as a map of the first source (a KML or KMZ file), as the tripinfo filter did.
 */
#[Filter(
  id: 'hobobiker_multisource',
  title: new TranslatableMarkup('Trip maps: [multisource:files/gps/Peru.kmz]'),
  type: FilterInterface::TYPE_MARKUP_LANGUAGE,
)]
class MultisourceFilter extends FilterBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected TripInfo $tripInfo) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('hobobiker.tripinfo'));
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult();
    $text = preg_replace_callback('/\[multisource:(.+?)\]/', function (array $m) use ($result): string {
      // Only the first source is drawn; the others (flickr_georss feeds) are gone. The site's own absolute URLs
      // become site-relative, without the cache-busting query (http://www.hobobiker.com/files/gps/Peru.kmz?1).
      $file = trim(preg_replace('/,.*$/', '', $m[1]));
      if (preg_match('@^https?://(www\.)?hobobiker\.com/\s*([^?]*)@', $file, $mm)) {
        $file = $mm[2];
      }
      $result->addAttachments(['library' => ['hobobiker/tripinfo_map']]);
      return $this->tripInfo->map($file) . '<br/><br/>';
    }, $text);
    return $result->setProcessedText($text);
  }

}
