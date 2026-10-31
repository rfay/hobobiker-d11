<?php

namespace Drupal\hobobiker\Plugin\Filter;

use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * D6's HTML corrector (_filter_htmlcorrector()), in place of Drupal 11's, which rebuilds the HTML through a DOM.
 *
 * D6's closes unclosed tags with a tag stack and does nothing else; Drupal 11's fixes what D6's left as it was.
 * Visible case: "<br/>" (no space) was read as an element "br/" and closed with "</br/>" at the end of its block,
 * which browsers show as one more line break each (69 bodies).
 *
 * One deliberate change (the owner's decision, declared): a comment left open at the end of the text is closed.
 * D6 passed it through, so node 3953's teaser ("<!--" then "<h3>New Pictures") hid everything after it on the
 * listing pages, up to the page's next "-->"; here those posts show.
 */
#[Filter(
  id: 'hobobiker_htmlcorrector',
  title: new \Drupal\Core\StringTranslation\TranslatableMarkup('Correct faulty HTML, as Drupal 6 did'),
  type: FilterInterface::TYPE_HTML_RESTRICTOR,
)]
class HtmlCorrectorD6Filter extends FilterBase {

  // Tags which cannot be nested but are typically left unclosed.
  const NO_NESTING = ['li' => 'li', 'p' => 'p'];

  // Single use tags in HTML4.
  const SINGLE_USE = ['base', 'meta', 'link', 'hr', 'br', 'param', 'img', 'area', 'input', 'col', 'frame'];

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $text = self::correct($text);
    // The deliberate change: close a comment still open at the end.
    if (preg_match('/<!--(?!.*-->)/s', $text)) {
      $text .= '-->';
    }
    return new FilterProcessResult($text);
  }

  /**
   * D6 _filter_htmlcorrector(), line for line.
   */
  public static function correct(string $text): string {
    $single_use = array_combine(self::SINGLE_USE, self::SINGLE_USE);

    // Properly entify angles.
    $text = preg_replace('@<(?=[^a-zA-Z!/]|$)@', '&lt;', $text);

    // Split tags from text: alternating literals and tags, beginning and ending with a literal.
    $split = preg_split('/<(!--.*?--|[^>]+?)>/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

    $tag = FALSE;
    $stack = [];
    $output = '';
    foreach ($split as $value) {
      if ($tag) {
        // Passthrough comments.
        if (substr($value, 0, 3) == '!--') {
          $output .= '<' . $value . '>';
        }
        else {
          [$tagname] = preg_split('/\s/', strtolower($value), 2);
          // Closing tag.
          if ($tagname[0] == '/') {
            $tagname = substr($tagname, 1);
            // Discard XHTML closing tags for single use tags.
            if (!isset($single_use[$tagname])) {
              // See if we possibly have a matching opening tag on the stack.
              if (in_array($tagname, $stack)) {
                // Close other tags lingering first.
                do {
                  $output .= '</' . $stack[0] . '>';
                } while (array_shift($stack) != $tagname);
              }
              // Otherwise, discard it.
            }
          }
          // Opening tag.
          else {
            // See if we have an identical 'no nesting' tag already open and close it if found.
            if (count($stack) && ($stack[0] == $tagname) && isset(self::NO_NESTING[$stack[0]])) {
              $output .= '</' . array_shift($stack) . '>';
            }
            // Push non-single-use tags onto the stack.
            if (!isset($single_use[$tagname])) {
              array_unshift($stack, $tagname);
            }
            // Add trailing slash to single-use tags as per X(HT)ML.
            else {
              $value = rtrim($value, ' /') . ' /';
            }
            $output .= '<' . $value . '>';
          }
        }
      }
      else {
        // Passthrough all text.
        $output .= $value;
      }
      $tag = !$tag;
    }
    // Close remaining tags.
    while (count($stack) > 0) {
      $output .= '</' . array_shift($stack) . '>';
    }
    return $output;
  }

}
