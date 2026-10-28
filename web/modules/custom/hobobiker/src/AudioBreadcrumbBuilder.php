<?php

namespace Drupal\hobobiker;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * The audio browse pages: Audio › Browse by › TAG, as the audio module set it.
 */
class AudioBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, $cacheability = NULL) {
    return $route_match->getRouteName() === 'hobobiker.audio_browse';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $tag = $route_match->getParameter('tag');
    $value = $route_match->getParameter('value');
    // The audio module replaced the whole breadcrumb: it starts at Audio, with no Home.
    $links = [Link::fromTextAndUrl($this->t('Audio'), Url::fromUserInput('/audio'))];
    if ($tag !== NULL) {
      $links[] = Link::fromTextAndUrl($this->t('Browse by'), Url::fromUserInput('/audio/by'));
      if ($value !== NULL) {
        $links[] = Link::fromTextAndUrl($tag, Url::fromUserInput("/audio/by/$tag"));
      }
    }
    return (new Breadcrumb())->setLinks($links)->addCacheContexts(['url.path']);
  }

}
