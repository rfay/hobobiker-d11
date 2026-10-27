<?php

namespace Drupal\hobobiker;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * The original's read-more link (ed_readmore, "inline"): inside the teaser's last paragraph.
 */
class ReadMore implements TrustedCallbackInterface {

  /**
   * The link's text: ed_readmore_text on the original.
   */
  const TEXT = '<strong>&nbsp;<i>read&nbsp;more&nbsp;here... lee mas aquí...</i>&nbsp;&raquo;</strong>';

  /**
   * #post_render callback for a teaser's body: the link before the last </p>, or after the text if the teaser does
   * not end with a paragraph.
   */
  public static function insert($markup, array $element) {
    $link = ' <a href="' . htmlspecialchars($element['#hobobiker_readmore'], ENT_QUOTES) . '" class="read-more">' . self::TEXT . '</a>';
    $markup = (string) $markup;
    if (preg_match('~</p>\s*$~i', $markup)) {
      return preg_replace('~</p>(\s*)$~i', $link . '</p>$1', $markup, 1);
    }
    return $markup . $link;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['insert'];
  }

}
