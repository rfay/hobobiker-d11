<?php

namespace Drupal\hobobiker\Controller;

use Drupal\Component\Utility\Xss;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * D6 lightbox2_filter_xss(): the posted caption, filtered to the tags D6 allowed (lightbox2_filter_xss_allowed_tags).
 */
class Lightbox2Controller {

  const ALLOWED_TAGS = ['p', 'br', 'a', 'em', 'strong', 'cite', 'code', 'ul', 'ol', 'li', 'dl', 'dt', 'dd'];

  public function filterXss(Request $request): JsonResponse {
    return new JsonResponse(Xss::filter((string) $request->request->get('string', ''), self::ALLOWED_TAGS));
  }

}
