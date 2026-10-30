<?php

namespace Drupal\hobobiker\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * The D6 blog module's breadcrumb on a blog entry: Home » Blogs » NAME's blog.
 */
class BlogBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, $cacheability = NULL) {
    $node = $route_match->getParameter('node');
    return $route_match->getRouteName() === 'entity.node.canonical' && $node instanceof NodeInterface && $node->bundle() === 'blog';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    $owner = $node->getOwner();
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute('Home', '<front>'));
    $breadcrumb->addLink(Link::fromTextAndUrl('Blogs', Url::fromUserInput('/blog')));
    // The account name, as D6 printed it ("'s blog" for the anonymous user, whose name is empty).
    $breadcrumb->addLink(Link::fromTextAndUrl($owner->getAccountName() . "'s blog", Url::fromUserInput('/blog/' . $owner->id())));
    $breadcrumb->addCacheableDependency($node);
    $breadcrumb->addCacheableDependency($owner);
    return $breadcrumb;
  }

}
