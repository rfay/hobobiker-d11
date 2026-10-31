<?php

namespace Drupal\hobobiker\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\system\PathBasedBreadcrumbBuilder;

/**
 * D6's breadcrumb (menu_get_active_breadcrumb()) everywhere the blog and audio builders are not.
 *
 * D6 built it from the router's path hierarchy and the navigation menu: a page with a navigation link (the
 * original's were hidden, in the d6-navigation menu here) shows that link's parents; other pages show their
 * parent router paths. An alias segment was not a router path (inuvik_to_denver/faq: Home only), the tripinfo
 * pages were callbacks with no trail, and term pages, served by Views, had none from the term's parents (Drupal
 * 11's taxonomy breadcrumb).
 */
class D6BreadcrumbBuilder extends PathBasedBreadcrumbBuilder {

  const NO_TRAIL_ROUTES = ['hobobiker.tripinfo', 'hobobiker.tripinfo_item'];

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    if (!$this->pathMatcher->isFrontPage() && ($trail = $this->navigationTrail($route_match))) {
      $breadcrumb = (new Breadcrumb())->addCacheContexts(['route'])->addCacheTags(['config:system.menu.d6-navigation']);
      return $breadcrumb->setLinks([Link::createFromRoute($this->t('Home'), '<front>'), ...$trail]);
    }
    return parent::build($route_match);
  }

  /**
   * The parents of the page's d6-navigation link, root first, or [] if it has none.
   */
  protected function navigationTrail(RouteMatchInterface $route_match): array {
    $manager = \Drupal::service('plugin.manager.menu.link');
    $links = $route_match->getRouteName() ? $manager->loadLinksByRoute($route_match->getRouteName(), $route_match->getRawParameters()->all(), 'd6-navigation') : [];
    $link = reset($links);
    $trail = [];
    while ($link && ($parent = $link->getParent()) && $manager->hasDefinition($parent)) {
      $link = $manager->createInstance($parent);
      array_unshift($trail, Link::fromTextAndUrl($link->getTitle(), $link->getUrlObject()));
    }
    return $trail;
  }

  /**
   * {@inheritdoc}
   *
   * Only router paths: not an alias, not a tripinfo callback.
   */
  protected function getRequestForPath($path, array $exclude) {
    $request = parent::getRequestForPath($path, $exclude);
    if (!$request || in_array($request->attributes->get('_route'), self::NO_TRAIL_ROUTES, TRUE)) {
      return NULL;
    }
    return \Drupal::service('path_alias.manager')->getPathByAlias($path) === $path ? $request : NULL;
  }

}
