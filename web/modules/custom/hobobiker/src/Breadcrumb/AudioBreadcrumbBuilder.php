<?php

namespace Drupal\hobobiker\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * D6 audio_page_browse_by()'s breadcrumb, which it set whole: Audio » Browse by » TAG.
 */
class AudioBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, $cacheability = NULL) {
    return $route_match->getRouteName() === 'hobobiker.audio_by';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $tag = $route_match->getParameter('tag');
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::fromTextAndUrl('Audio', Url::fromUserInput('/audio')));
    if ($tag !== NULL) {
      $breadcrumb->addLink(Link::fromTextAndUrl('Browse by', Url::fromUserInput('/audio/by')));
      if ($route_match->getParameter('value') !== NULL) {
        $breadcrumb->addLink(Link::fromTextAndUrl($tag, Url::fromUserInput('/audio/by/' . $tag)));
      }
    }
    $breadcrumb->addCacheContexts(['url.path']);
    return $breadcrumb;
  }

}
