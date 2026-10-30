<?php

namespace Drupal\hobobiker\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\hobobiker\Audio;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The D6 audio module's pages: audio/by (browse by tag, tag values, one value's audio) and audio/download.
 */
class AudioController {

  /**
   * Published audio nodes with a tag set, as [nid => value], oldest first.
   */
  protected function tagged(string $tag): array {
    if (!isset(Audio::TAGS[$tag])) {
      return [];
    }
    return \Drupal::database()->query("SELECT n.nid, t.field_audio_{$tag}_value FROM {node_field_data} n
      JOIN {node__field_audio_$tag} t ON t.entity_id = n.nid WHERE n.status = 1 ORDER BY n.created ASC, n.nid ASC")->fetchAllKeyed();
  }

  /**
   * D6 theme('item_list').
   */
  protected function itemList(array $items): string {
    $list = '';
    foreach (array_values($items) as $i => $item) {
      $class = $i === 0 ? ' class="first"' : ($i === count($items) - 1 ? ' class="last"' : '');
      $list .= "<li$class>$item</li>\n";
    }
    return "<div class=\"item-list\"><ul>$list</ul></div>";
  }

  /**
   * Title: "Browse by...", "Audio by TAG" or "Audio by TAG VALUE" (the value as in the URL).
   */
  public function title(?string $tag = NULL, ?string $value = NULL): string {
    return $tag === NULL ? 'Browse by...' : trim("Audio by $tag $value");
  }

  /**
   * D6 audio_page_browse_by().
   */
  public function browse(?string $tag = NULL, ?string $value = NULL): array {
    $build = ['#cache' => ['tags' => ['node_list:audio']]];
    if ($tag === NULL) {
      // The browsable tags that published audio has, alphabetically.
      $tags = array_keys(array_filter(Audio::TAGS, fn($browsable, $t) => $browsable && $this->tagged($t), ARRAY_FILTER_USE_BOTH));
      sort($tags);
      $items = array_map(fn($t) => '<a href="' . Url::fromUserInput("/audio/by/$t")->toString() . "\">$t</a>", $tags);
      // D6 printed this as the page's help (hook_help), in the theme's div.help.
      $build['#markup'] = Markup::create("<div class=\"help\"><div class=\"help\">You can browse for audio by any of the following fields.\n</div></div>" . $this->itemList($items));
    }
    elseif ($value === NULL) {
      // Each value with its count, alphabetically.
      $counts = array_count_values($this->tagged($tag));
      uksort($counts, 'strcmp');
      $items = [];
      foreach ($counts as $v => $count) {
        $url = Url::fromUserInput("/audio/by/$tag/" . Audio::clean($v))->toString();
        $items[] = "<a href=\"$url\">" . Html::escape($v) . "</a> ($count)";
      }
      $build['#markup'] = Markup::create($this->itemList($items));
    }
    else {
      // The audio with that value (compared as slugs), oldest first, as teasers.
      $nids = array_keys(array_filter($this->tagged($tag), fn($v) => Audio::clean($v) === Audio::clean($value)));
      $build['nodes'] = \Drupal::entityTypeManager()->getViewBuilder('node')->viewMultiple(Node::loadMultiple($nids), 'teaser');
    }
    return $build;
  }

  /**
   * D6 audio_download(): the node's audio file, as an attachment. (D6 also counted the download; the counts are
   * the original's, kept as they were.)
   */
  public function download(NodeInterface $node): BinaryFileResponse {
    $file = $node->bundle() === 'audio' ? $node->get('field_audio')->entity : NULL;
    if (!$file || !file_exists($file->getFileUri())) {
      throw new NotFoundHttpException();
    }
    $response = new BinaryFileResponse($file->getFileUri(), 200, ['Content-Type' => $file->getMimeType()]);
    return $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getFilename(), 'audio.mp3');
  }

}
