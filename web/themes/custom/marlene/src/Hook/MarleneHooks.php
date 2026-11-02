<?php

namespace Drupal\marlene\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\hobobiker\Audio;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for marlene.
 *
 * The site's content conventions from the original (which node types show a byline, the inline "read more" in a
 * teaser, the blog and audio links, the D6 pager URLs) in this theme's markup.
 */
class MarleneHooks {

  /**
   * The node types whose byline the original showed (its theme's toggle_node_info_TYPE settings).
   */
  const BYLINE_TYPES = ['audio', 'blog', 'forum', 'poll', 'story'];

  /**
   * The banner photo: Randy and Nancy with their loaded bikes before the Cordillera Blanca, Peru (the site's
   * 2821697987_Randy_and_Nancy_in_front_of_Cordillera_Blanca_peak.jpg), in the widths in images/banner/. The front
   * page shows the whole photo; other pages a band of its peaks, cropped from the right where no one stands.
   */
  const BANNERS = [
    'front' => ['name' => 'cordillera-blanca', 'webp' => [800, 1400, 2400], 'jpg' => [800, 1400], 'width' => 3264, 'height' => 2448],
    'band' => ['name' => 'cordillera-peaks', 'webp' => [800, 1400], 'jpg' => [800, 1400], 'width' => 1414, 'height' => 560],
  ];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected DateFormatterInterface $dateFormatter,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected ThemeExtensionList $themeList,
    protected PathMatcherInterface $pathMatcher,
    protected CurrentPathStack $currentPath,
    protected RouteMatchInterface $routeMatch,
    protected TitleResolverInterface $titleResolver,
    protected RequestStack $requestStack,
    protected RendererInterface $renderer,
  ) {}

  /**
   * Implements hook_preprocess_image_widget().
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];
    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

  /**
   * Implements hook_preprocess_html().
   *
   * The front page's head title, as the original's: "name | slogan" (its content has no title).
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    if ($this->pathMatcher->isFrontPage() && empty($variables['page']['#title'])) {
      $site = $this->configFactory->get('system.site');
      $variables['head_title'] = ['name' => $site->get('name'), 'slogan' => strip_tags((string) $site->get('slogan'))];
    }
  }

  /**
   * Implements hook_preprocess_page().
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    // The site name "Hobobiker.com: Randy and Nancy's Big Bike Trip": the wordmark before the colon, the tagline after.
    $site = $this->configFactory->get('system.site');
    [$brand, $tagline] = array_map('trim', explode(':', (string) $site->get('name'), 2)) + [1 => ''];
    $variables['brand'] = $brand;
    $variables['tagline'] = $tagline;
    $variables['site_slogan'] = strip_tags((string) $site->get('slogan'));

    // The page's final title (a view sets its own while it runs), else the route's.
    $title = $variables['page']['#title'] ?? '';
    if (is_string($title)) {
      // A view's title comes escaped: decode once, escape once.
      $title = Html::escape(Html::decodeEntities($title));
      $title = $title === '' ? '' : Markup::create($title);
    }
    if ($title === '' && !$variables['is_front'] && ($route = $this->routeMatch->getRouteObject())) {
      $title = $this->titleResolver->getTitle($this->requestStack->getCurrentRequest(), $route) ?? '';
    }
    $variables['page_title'] = $title;

    $banner = self::BANNERS[$variables['is_front'] ? 'front' : 'band'];
    $dir = $this->themeList->getPath('marlene') . '/images/banner/' . $banner['name'];
    $srcset = fn(string $ext) => implode(', ', array_map(
      fn(int $w) => $this->fileUrlGenerator->generateString("$dir-$w.$ext") . " {$w}w", $banner[$ext]));
    $variables['banner'] = [
      'webp' => $srcset('webp'),
      'jpg' => $srcset('jpg'),
      'fallback' => $this->fileUrlGenerator->generateString("$dir-1400.jpg"),
      'width' => $banner['width'],
      'height' => $banner['height'],
      'alt' => $variables['is_front'] ? t('Randy and Nancy with their loaded touring bikes on a high road in Peru, the snowy Cordillera Blanca behind them') : '',
    ];
  }

  /**
   * Implements hook_preprocess_node().
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $variables['node'];

    if (in_array($node->bundle(), self::BYLINE_TYPES, TRUE)) {
      $created = $node->getCreatedTime();
      $variables['byline'] = [
        'author' => $node->getOwner()->getDisplayName(),
        'date' => $this->dateFormatter->format($created, 'custom', 'F j, Y'),
        'datetime' => $this->dateFormatter->format($created, 'custom', 'c'),
      ];
    }

    // Tags in the original's order: term weight, then name (case-insensitive).
    if ($node->hasField('field_tags')) {
      $terms = $node->get('field_tags')->referencedEntities();
      usort($terms, fn($a, $b) => [$a->getWeight(), mb_strtolower($a->label())] <=> [$b->getWeight(), mb_strtolower($b->label())]);
      $variables['tags'] = array_map(fn($term) => [
        'title' => $term->label(),
        'url' => $term->toUrl()->toString(),
        'description' => trim(strip_tags((string) $term->getDescription())),
      ], $terms);
    }

    // The blog link, except on that author's own blog page.
    $links = [];
    if ($node->bundle() === 'blog' && $this->currentPath->getPath() !== '/blog/' . $node->getOwnerId()) {
      $name = $node->getOwner()->getAccountName();
      $links[] = ['text' => t("@name's blog", ['@name' => $name]), 'url' => Url::fromUserInput('/blog/' . $node->getOwnerId())->toString(), 'title' => t("Read @name's latest blog entries.", ['@name' => $name])];
    }
    if ($node->bundle() === 'audio' && ($download = Audio::downloadUrl($node))) {
      $links[] = ['text' => t('Download audio file'), 'url' => $download, 'title' => ''];
      $links[] = ['text' => t('@count downloads', ['@count' => $node->get('field_audio_download_count')->value])];
      $links[] = ['text' => t('@count plays', ['@count' => $node->get('field_audio_play_count')->value])];
    }
    $variables['node_links'] = $links;

    // The fields shown with a label (a trip log's date, start, destination...) become the facts grid; the tags are
    // already chips.
    $facts = [];
    foreach ($variables['content'] as $key => $element) {
      if ($key !== 'field_tags' && is_array($element) && ($element['#theme'] ?? '') === 'field' && ($element['#label_display'] ?? 'hidden') !== 'hidden') {
        $facts[$key] = $element;
        unset($variables['content'][$key]);
      }
    }
    $variables['facts'] = $facts;

    // The original's inline "read more": in a teaser that is not the whole body, inside the last paragraph.
    $body_item = $node->hasField('body') ? $node->get('body') : NULL;
    $more = $body_item && ((string) $body_item->summary !== '' || str_contains((string) $body_item->value, '<!--break-->'));
    if ($variables['view_mode'] === 'teaser' && $more && isset($variables['content']['body'][0])) {
      $body = (string) $this->renderer->renderInIsolation($variables['content']['body'][0]);
      $link = '<a class="read-more__link" href="' . $node->toUrl()->toString() . '" rel="nofollow">'
        . t('Read more') . ' <span lang="es">· ' . t('Lee más') . '</span> <span aria-hidden="true">→</span>'
        . '<span class="visually-hidden">: ' . Html::escape($node->label()) . '</span></a>';
      if (preg_match('!</?p[^>]*>$!i', $body, $match, PREG_OFFSET_CAPTURE)) {
        $body = substr_replace($body, ' <span class="read-more">' . $link . '</span>', $match[0][1], 0);
      }
      else {
        $body .= '<p class="read-more">' . $link . '</p>';
      }
      $variables['content']['body'][0] = ['#markup' => Markup::create($body)];
    }
  }

  /**
   * Implements hook_preprocess_pager().
   *
   * The original's pager URLs: the page's path by its alias, and no page parameter on the first page.
   */
  #[Hook('preprocess_pager')]
  public function preprocessPager(array &$variables): void {
    if (empty($variables['items'])) {
      return;
    }
    $path = Url::fromUserInput($this->currentPath->getPath())->toString();
    $href = function (string $href) use ($path): string {
      parse_str(ltrim((string) parse_url($href, PHP_URL_QUERY), '?'), $query);
      if (($query['page'] ?? '') === '0') {
        unset($query['page']);
      }
      $query = http_build_query($query);
      return $path . ($query === '' ? '' : '?' . str_replace('%2C', ',', $query));
    };
    foreach (['first', 'previous', 'next', 'last'] as $key) {
      if (isset($variables['items'][$key]['href'])) {
        $variables['items'][$key]['href'] = $href($variables['items'][$key]['href']);
      }
    }
    foreach ($variables['items']['pages'] ?? [] as $number => $page) {
      if (isset($page['href'])) {
        $variables['items']['pages'][$number]['href'] = $href($page['href']);
      }
    }
  }

}
