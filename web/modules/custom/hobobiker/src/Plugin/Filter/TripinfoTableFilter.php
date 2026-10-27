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
 * Renders [tripinfo:START,END] as the trip's table of days.
 *
 * Replaces the PHP the original ran for this (`<?php print tripinfo_table('START','END'); ?>`): the import rewrites
 * each call to this macro, so no PHP is executed.
 */
#[Filter(
  id: 'hobobiker_tripinfo_table',
  title: new TranslatableMarkup('Trip tables: [tripinfo:2006-05-01,2006-08-24]'),
  type: FilterInterface::TYPE_MARKUP_LANGUAGE,
)]
class TripinfoTableFilter extends FilterBase implements ContainerFactoryPluginInterface {

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
    $text = preg_replace_callback('/\[tripinfo:(\d{4}-\d\d-\d\d),(\d{4}-\d\d-\d\d)\]/', fn($m) => $this->tripInfo->table($m[1], $m[2]), $text);
    return (new FilterProcessResult($text))->addCacheTags(['node_list:triplog']);
  }

}
