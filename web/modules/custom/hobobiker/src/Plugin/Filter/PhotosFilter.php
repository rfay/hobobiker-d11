<?php

namespace Drupal\hobobiker\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * The posts' photos and embeds, made local, enlargeable and accessible as they are displayed.
 *
 * The stored bodies are not changed. Using public://flickr/manifest.json (scripts/flickr-fetch.php: per Flickr
 * photo id, its title, page and the largest local copy):
 * - a small Flickr photo (ID_SECRET_m.jpg and the like) links to its large local copy for the lightbox, with its
 *   Flickr title as caption; a link around it to the Flickr photo page now opens the local copy;
 * - a text link to a Flickr photo page opens the local copy in the lightbox;
 * - every image gets alt text: its own, else the Flickr title, else words from its file name, else empty;
 * - an iframe gets a title naming what it embeds; a Flash YouTube player becomes YouTube's iframe player; other
 *   Flash embeds, which no browser plays any more, become a link (or a note, where the service is gone);
 * - links and images on http(s)://(www.)hobobiker.com become site-relative.
 */
#[Filter(
  id: 'hobobiker_photos',
  title: new \Drupal\Core\StringTranslation\TranslatableMarkup('Hobobiker photos and embeds: local, enlargeable, accessible'),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
  weight: 20,
)]
class PhotosFilter extends FilterBase {

  /**
   * Alt and title text that says nothing about the photo (Flickr's embed boilerplate).
   */
  const GENERIC = ['hobobiker photo', 'photo sharing', 'photo', 'image', 'picture'];

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if (!preg_match('/<(img|a|iframe|object|embed)\b/i', $text)) {
      return new FilterProcessResult($text);
    }
    $dom = Html::load($text);
    $this->siteRelative($dom);
    $this->photos($dom);
    $this->flickrTextLinks($dom);
    $this->embeds($dom);
    return new FilterProcessResult(Html::serialize($dom));
  }

  /**
   * The Flickr manifest, keyed by photo id.
   */
  protected static function manifest(): array {
    static $manifest;
    if (!isset($manifest)) {
      $path = \Drupal::service('file_system')->realpath('public://flickr/manifest.json');
      $manifest = $path && is_file($path) ? (json_decode(file_get_contents($path), TRUE) ?: []) : [];
    }
    return $manifest;
  }

  /**
   * A Flickr title as a caption: without a leading camera file number ("DSC02058 Old bridge" is "Old bridge");
   * nothing when that is all it is.
   */
  public static function caption(string $title): string {
    $title = trim(preg_replace('/^(DSCF?N?|IMG|PICT|CIMG|P|100_)[-_]?\d+\s*/i', '', trim($title)));
    return preg_match('/\p{L}{3,}/u', $title) && !in_array(mb_strtolower($title), self::GENERIC, TRUE) ? $title : '';
  }

  /**
   * Words from a file name ("2893577060_Nancy_and_Randy_at_Machu_Picchu.jpg" is "Nancy and Randy at Machu
   * Picchu"); nothing when it has none (a number, a camera file name, Flickr's id and secret).
   */
  public static function fileText(string $src): string {
    $name = rawurldecode(pathinfo((string) parse_url($src, PHP_URL_PATH), PATHINFO_FILENAME));
    $name = preg_replace(['/^\d{6,}_/', '/^[0-9a-f]{10}(_[a-z])?$/', '/_[a-z]$/', '/_\d+$/'], '', $name);
    // "PacificCoastComplete" is "Pacific Coast Complete".
    $name = preg_replace('/(?<=\p{Ll})(?=\p{Lu})/u', ' ', $name);
    return self::caption(trim(preg_replace('/[_\s]+/', ' ', $name)));
  }

  /**
   * A description of a photo from its file or URL: its Flickr title, else words from its file name.
   */
  public static function describe(string $url): string {
    $id = self::flickrId($url) ?? (preg_match('/^(\d{6,})_/', basename((string) parse_url($url, PHP_URL_PATH)), $m) ? $m[1] : NULL);
    return self::caption(self::manifest()[$id]['title'] ?? '') ?: self::fileText($url);
  }

  /**
   * The Flickr photo id of a local file name or a Flickr photo page URL.
   */
  public static function flickrId(string $url): ?string {
    if (preg_match('~flickr\.com/photos/[^/]+/(\d{6,})~i', $url, $m)) {
      return $m[1];
    }
    $path = (string) parse_url($url, PHP_URL_PATH);
    if (preg_match('~(^|/)(sites/default/)?files/~', ltrim($path, '/')) && preg_match('/^(\d{6,})_/', basename($path), $m)) {
      return $m[1];
    }
    return NULL;
  }

  /**
   * The URL of a manifest entry's local copy, when there is one.
   */
  protected static function largeUrl(?string $id): ?string {
    $entry = $id ? (self::manifest()[$id] ?? NULL) : NULL;
    if (!$entry || ($entry['file'] ?? '') === '') {
      return NULL;
    }
    return '/sites/default/files/' . implode('/', array_map('rawurlencode', explode('/', $entry['file'])));
  }

  /**
   * Absolute links to the old domain, made site-relative.
   */
  protected function siteRelative(\DOMDocument $dom): void {
    foreach (['a' => 'href', 'img' => 'src'] as $tag => $attribute) {
      foreach ($dom->getElementsByTagName($tag) as $element) {
        $value = $element->getAttribute($attribute);
        if (preg_match('~^https?://(www\.)?hobobiker\.com(?=/|$)~i', $value)) {
          $element->setAttribute($attribute, preg_replace('~^https?://(www\.)?hobobiker\.com~i', '', $value) ?: '/');
        }
      }
    }
  }

  /**
   * Images: alt text, and small Flickr photos linked to their large local copy.
   */
  protected function photos(\DOMDocument $dom): void {
    foreach (iterator_to_array($dom->getElementsByTagName('img')) as $img) {
      $src = $img->getAttribute('src');
      $id = self::flickrId($src);
      $entry = $id ? (self::manifest()[$id] ?? NULL) : NULL;
      $own = trim($img->getAttribute('alt'));
      $own = in_array(mb_strtolower($own), self::GENERIC, TRUE) ? '' : $own;
      $caption = $own !== '' ? $own : (self::caption($entry['title'] ?? '') ?: self::fileText($src));
      $img->setAttribute('alt', $caption);

      $link = $img->parentNode;
      while ($link && $link->nodeName !== 'a' && $link->nodeName !== 'body') {
        $link = $link->parentNode;
      }
      $link = $link && $link->nodeName === 'a' ? $link : NULL;
      $large = self::largeUrl($id);
      if ($link) {
        // A link to a Flickr photo page: open that photo's local copy instead. Lightbox links (hobophoto) stay.
        $href = $link->getAttribute('href');
        $target = preg_match('~flickr\.com/photos/~i', $href) ? (self::largeUrl(self::flickrId($href)) ?? $large) : NULL;
        if ($target) {
          $this->lightboxLink($link, $target, $caption);
        }
      }
      elseif ($large && ($entry['width'] ?? 0) > ((int) $img->getAttribute('width') ?: 240)) {
        $link = $dom->createElement('a');
        $img->parentNode->replaceChild($link, $img);
        $link->appendChild($img);
        $this->lightboxLink($link, $large, $caption);
        $link->setAttribute('class', 'photo-link');
      }
      // A link whose only content is the image is named by its alt.
      if ($link && $caption === '' && trim($link->textContent) === '') {
        $img->setAttribute('alt', trim($link->getAttribute('title')) ?: (string) $this->t('Enlarge photo'));
      }
    }
  }

  /**
   * Text links to a Flickr photo page: the local copy, in the lightbox.
   */
  protected function flickrTextLinks(\DOMDocument $dom): void {
    foreach (iterator_to_array($dom->getElementsByTagName('a')) as $link) {
      $href = $link->getAttribute('href');
      if (!preg_match('~flickr\.com/photos/[^/]+/\d{6,}~i', $href) || $link->getElementsByTagName('img')->length) {
        continue;
      }
      $id = self::flickrId($href);
      if ($large = self::largeUrl($id)) {
        $caption = self::caption(self::manifest()[$id]['title'] ?? '') ?: self::caption(trim($link->textContent));
        $this->lightboxLink($link, $large, $caption);
      }
    }
  }

  /**
   * Points a link at a local photo for the lightbox (the D6 Lightbox2 markup: rel="lightbox[group1][caption]").
   */
  protected function lightboxLink(\DOMElement $link, string $href, string $caption): void {
    $link->setAttribute('href', $href);
    $link->setAttribute('rel', 'lightbox[group1][' . str_replace([']', '['], ['', ''], $caption) . ']');
    $link->removeAttribute('target');
  }

  /**
   * Iframes get a title; Flash embeds become YouTube's player, a link, or a note.
   */
  protected function embeds(\DOMDocument $dom): void {
    foreach ($dom->getElementsByTagName('iframe') as $iframe) {
      if (trim($iframe->getAttribute('title')) === '') {
        $iframe->setAttribute('title', self::embedTitle($iframe->getAttribute('src')));
      }
    }
    // Flash: an <object> (with its <embed>), or an <embed> on its own.
    $flash = [];
    foreach (iterator_to_array($dom->getElementsByTagName('object')) as $object) {
      $flash[] = $object;
    }
    foreach (iterator_to_array($dom->getElementsByTagName('embed')) as $embed) {
      $inside = FALSE;
      for ($p = $embed->parentNode; $p; $p = $p->parentNode) {
        $inside = $inside || $p->nodeName === 'object';
      }
      if (!$inside) {
        $flash[] = $embed;
      }
    }
    foreach ($flash as $element) {
      $element->parentNode?->replaceChild(self::flashReplacement($dom, $element), $element);
    }
  }

  /**
   * A title for an embedded page, from its address.
   */
  public static function embedTitle(string $src): string {
    $host = strtolower((string) parse_url(str_starts_with($src, '//') ? "https:$src" : $src, PHP_URL_HOST));
    return match (TRUE) {
      str_contains($host, 'youtube') => (string) t('Embedded video (YouTube)'),
      str_contains($host, 'google') && str_contains($src, 'map') => (string) t('Embedded map (Google Maps)'),
      str_contains($host, 'flickr') => (string) t('Embedded photos (Flickr)'),
      str_contains($host, 'cnn.com') => (string) t('Embedded video (CNN)'),
      str_contains($host, 'mapufacture') => (string) t('Embedded map (Mapufacture)'),
      default => (string) t('Embedded content (@host)', ['@host' => $host ?: 'this site']),
    };
  }

  /**
   * What a Flash embed becomes.
   */
  protected static function flashReplacement(\DOMDocument $dom, \DOMElement $element): \DOMElement {
    $url = $element->getAttribute('src') ?: $element->getAttribute('data');
    $flashvars = '';
    foreach ($element->getElementsByTagName('param') as $param) {
      $name = strtolower($param->getAttribute('name'));
      $url = $name === 'movie' ? $param->getAttribute('value') : $url;
      $flashvars = $name === 'flashvars' ? $param->getAttribute('value') : $flashvars;
    }
    foreach ($element->getElementsByTagName('embed') as $embed) {
      $url = $url ?: $embed->getAttribute('src');
    }
    // YouTube's Flash player: its iframe player, at the same size.
    if (preg_match('~youtube\.com/v/([\w-]{6,})~i', $url, $m)) {
      $iframe = $dom->createElement('iframe');
      foreach (['src' => "https://www.youtube-nocookie.com/embed/$m[1]", 'title' => (string) t('Embedded video (YouTube)'),
        'width' => $element->getAttribute('width') ?: '425', 'height' => $element->getAttribute('height') ?: '350',
        'allow' => 'encrypted-media; picture-in-picture; fullscreen', 'loading' => 'lazy'] as $name => $value) {
        $iframe->setAttribute($name, $value);
      }
      return $iframe;
    }
    $note = $dom->createElement('p');
    $note->setAttribute('class', 'legacy-embed');
    // A Flickr slideshow of an album: a link to the album.
    if (preg_match('~page_show_url=([^&]+)~', $flashvars, $m)) {
      $album = 'https://www.flickr.com' . preg_replace('~show/?$~', '', rawurldecode($m[1]));
      $link = $dom->createElement('a', (string) t('See these photos on Flickr'));
      $link->setAttribute('href', $album);
      $note->appendChild($link);
      return $note;
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $note->appendChild($dom->createTextNode((string) t('A Flash player from @host was here; that service no longer exists.', ['@host' => $host ?: 'another site'])));
    return $note;
  }

}
