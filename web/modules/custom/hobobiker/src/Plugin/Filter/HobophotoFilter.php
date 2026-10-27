<?php

namespace Drupal\hobobiker\Plugin\Filter;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders [hobophoto:path=...,orientation=portrait|landscape,caption=...] macros.
 *
 * Port of the Drupal 6 hobobiker_filter module: the macro stays in the stored body, as it was on the original,
 * and is turned into a linked image style derivative when the text is rendered.
 */
#[Filter(
  id: 'hobobiker_hobophoto',
  title: new TranslatableMarkup('Hobobiker photos: [hobophoto:path=sites/default/files/somepic.jpg,orientation=portrait,caption=The caption]'),
  type: FilterInterface::TYPE_MARKUP_LANGUAGE,
)]
class HobophotoFilter extends FilterBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected RendererInterface $renderer, protected FileUrlGeneratorInterface $fileUrlGenerator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('renderer'), $container->get('file_url_generator'));
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult();
    $text = preg_replace_callback('/\[hobophoto:(.+?)\]/', function (array $m) use ($result): string {
      $config = $this->splitConfig($m[1]);
      // As on the original: 240x180, or 180x240 for portrait (scale and crop).
      $style = ImageStyle::load($config['orientation'] === 'portrait' ? '180x240' : '240x180');
      // The path is relative to the Drupal root; the files directory prefix becomes the public scheme.
      $uri = 'public://' . preg_replace('~^(sites/default/files|files)/~', '', $config['path']);
      $rel = 'lightbox[group1][' . $config['caption'] . ']';
      $build = [
        '#theme' => 'hobobiker_photo',
        '#href' => $this->fileUrlGenerator->generateString($uri),
        '#src' => $style ? $style->buildUrl($uri) : $this->fileUrlGenerator->generateString($uri),
        '#caption' => $config['caption'],
        '#rel' => $rel,
      ];
      if ($style) {
        $result->addCacheableDependency($style);
      }
      $markup = (string) $this->renderer->renderInIsolation($build);
      $result->addCacheableDependency(BubbleableMetadata::createFromRenderArray($build));
      return $markup;
    }, $text);
    return $result->setProcessedText($text);
  }

  /**
   * Parses "path=x,orientation=portrait,caption=y" exactly as the Drupal 6 filter did.
   */
  protected function splitConfig(string $string): array {
    $config = ['path' => '', 'orientation' => 'landscape', 'caption' => ''];
    preg_match_all('/([a-zA-Z]+)=([-@0-9a-zA-Z:;\/_. ]+)/', str_replace(',', "\n", $string), $parts, PREG_SET_ORDER);
    foreach ($parts as $part) {
      $config[strtolower(trim($part[1]))] = trim($part[2]);
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    $tip = $this->t('Insert Hobobiker images: [hobophoto:path=sites/default/files/somepic.jpg,orientation=portrait,caption=This is the caption]');
    return $long ? $tip . ' ' . $this->t('The orientation can be landscape (default) or portrait. The path has no leading slash.') : $tip;
  }

}
