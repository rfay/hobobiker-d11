<?php

namespace Drupal\hobobiker\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\hobobiker\Audio;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The Drupal 6 audio module's pages: browse by tag, and the download link.
 */
class AudioController extends ControllerBase {

  /**
   * Published audio nodes, oldest first.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  protected function nodes(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'audio')->condition('status', 1)->sort('created')->sort('nid')->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * /audio/by, /audio/by/TAG, /audio/by/TAG/VALUE.
   */
  public function browse(?string $tag = NULL, ?string $value = NULL): array {
    $build = ['#cache' => ['tags' => ['node_list:audio']]];
    $list = fn(array $items) => '<div class="item-list"><ul><li>' . implode('</li> <li>', $items) . '</li></ul></div>';
    if ($tag === NULL) {
      // Every browsable tag some published node has, alphabetically.
      $tags = [];
      foreach ($this->nodes() as $node) {
        foreach (Audio::TAGS as $t => $browsable) {
          if ($browsable && (string) $node->get("field_audio_$t")->value !== '') {
            $tags[$t] = '<a href="' . Url::fromUserInput("/audio/by/$t")->toString() . "\">$t</a>";
          }
        }
      }
      ksort($tags);
      $build['#markup'] = Markup::create('<div>You can browse for audio by any of the following fields. </div>' . $list($tags));
      return $build;
    }
    if (!isset(Audio::TAGS[$tag])) {
      throw new NotFoundHttpException();
    }
    if ($value === NULL) {
      // Each value with its number of nodes, sorted by value (as MySQL did, ignoring case).
      $counts = [];
      foreach ($this->nodes() as $node) {
        $v = (string) $node->get("field_audio_$tag")->value;
        if ($v !== '') {
          $counts[$v] = ($counts[$v] ?? 0) + 1;
        }
      }
      uksort($counts, 'strcasecmp');
      $items = [];
      foreach ($counts as $v => $n) {
        $items[] = '<a href="' . Url::fromUserInput("/audio/by/$tag/" . Audio::clean($v))->toString() . '">' . Html::escape($v) . "</a> ($n)";
      }
      $build['#markup'] = Markup::create($list($items));
      return $build;
    }
    // The nodes whose tag cleans to $value, as teasers.
    $nodes = array_filter($this->nodes(), fn(NodeInterface $n) => Audio::clean((string) $n->get("field_audio_$tag")->value) === Audio::clean($value));
    $build['nodes'] = $this->entityTypeManager()->getViewBuilder('node')->viewMultiple($nodes, 'teaser');
    return $build;
  }

  /**
   * Page titles as the original's: "Browse by...", "Audio by artist", "Audio by artist hobobiker_com".
   */
  public function browseTitle(?string $tag = NULL, ?string $value = NULL): string {
    return $tag === NULL ? 'Browse by...' : trim("Audio by $tag " . ($value ?? ''));
  }

  /**
   * /audio/download/NID/FILENAME: the node's audio file as a download. The original also counted the download;
   * the site is frozen, so the count stays as it was.
   */
  public function download(NodeInterface $node, string $filename): BinaryFileResponse {
    $file = $node->hasField('field_audio_file') ? $node->get('field_audio_file')->entity : NULL;
    if (!$file || !$node->access('view')) {
      throw new NotFoundHttpException();
    }
    $response = new BinaryFileResponse($file->getFileUri(), 200, ['Content-Type' => $file->getMimeType()]);
    return $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getFilename(), 'audio.mp3');
  }

}
