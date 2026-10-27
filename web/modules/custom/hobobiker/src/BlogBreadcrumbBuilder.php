<?php

namespace Drupal\hobobiker;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Blog entries: Home › Blogs › X's blog, as the Drupal 6 blog module set it.
 */
class BlogBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  public function __construct(protected AliasManagerInterface $aliasManager) {}

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
    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $owner = $node->getOwner();
    $breadcrumb = new Breadcrumb();
    $breadcrumb->setLinks([
      Link::createFromRoute($this->t('Home'), '<front>'),
      Link::fromTextAndUrl($this->t('Blogs'), Url::fromUserInput('/blog')),
      Link::fromTextAndUrl($this->t("@name's blog", ['@name' => $owner->getDisplayName()]), Url::fromUserInput($this->aliasManager->getAliasByPath('/blog/' . $owner->id()))),
    ]);
    return $breadcrumb->addCacheableDependency($node)->addCacheableDependency($owner)->addCacheContexts(['route']);
  }

}
