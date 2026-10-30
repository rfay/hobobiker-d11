<?php

namespace Drupal\hobobiker\PathProcessor;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Routing\RequestContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * D6's alias lookup cache: a page requested at one of its aliases links to itself by that alias.
 *
 * D6 linked a path by its newest alias, except on a page requested at another alias of the same path, where
 * drupal_lookup_path() had cached the requested one (/new_england_pics links its own menu item there, not
 * /new_england_2005). Runs after the alias processor.
 */
class RequestedAliasProcessor implements OutboundPathProcessorInterface {

  public function __construct(protected AliasManagerInterface $aliasManager, protected RequestContext $context) {}

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!empty($options['alias']) || !empty($options['external'])) {
      return $path;
    }
    $requested = rawurldecode($this->context->getPathInfo());
    if ($requested === '' || $requested === '/' || $requested === $path) {
      return $path;
    }
    $system = $this->aliasManager->getPathByAlias($requested);
    if ($system !== $requested && $this->aliasManager->getAliasByPath($system) === $path) {
      $bubbleable_metadata?->addCacheContexts(['url.path']);
      return $requested;
    }
    return $path;
  }

}
