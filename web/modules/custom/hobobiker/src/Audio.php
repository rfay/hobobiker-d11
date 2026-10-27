<?php

namespace Drupal\hobobiker;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * What the Drupal 6 audio module showed for an audio node, from the node's field_audio_* fields.
 */
class Audio {

  /**
   * The ID3 tags in the order the audio module showed them; all but track can be browsed (audio/by/TAG/VALUE).
   */
  const TAGS = ['artist' => TRUE, 'title' => TRUE, 'album' => TRUE, 'track' => FALSE, 'genre' => TRUE, 'year' => TRUE];

  /**
   * The audio module's audio_clean_tag(): anything but [-\w] becomes "_", trimmed, lower case. (Its accent table
   * is not needed: no tag value here has an accent.)
   */
  public static function clean(string $value): string {
    return strtolower(trim(preg_replace('/[^-\w]+/', '_', $value), '_'));
  }

  /**
   * "17:37 minutes (6.05 MB)", from the size the original recorded.
   */
  public static function length(NodeInterface $node): string {
    return $node->get('field_audio_length')->value . ' minutes (' . self::formatSize((int) $node->get('field_audio_size')->value) . ')';
  }

  /**
   * Drupal 6's format_size(): bytes, or KB and then MB, each rounded to two decimals.
   */
  public static function formatSize(int $size): string {
    if ($size < 1024) {
      return $size === 1 ? '1 byte' : "$size bytes";
    }
    $size = round($size / 1024, 2);
    $suffix = 'KB';
    if ($size >= 1024) {
      $size = round($size / 1024, 2);
      $suffix = 'MB';
    }
    return "$size $suffix";
  }

  /**
   * The full page's list: Artist, Title, ... Length, Format.
   */
  public static function info(NodeInterface $node): string {
    $items = [];
    foreach (self::TAGS as $tag => $browsable) {
      $value = $node->get("field_audio_$tag")->value;
      if ($value === NULL || $value === '') {
        continue;
      }
      $text = Html::escape($value);
      if ($browsable) {
        $text = '<a href="' . Url::fromUserInput("/audio/by/$tag/" . self::clean($value))->toString() . "\">$text</a>";
      }
      $items[] = '<strong>' . ucfirst($tag) . ":</strong> $text";
    }
    $items[] = '<strong>Length:</strong> ' . Html::escape(self::length($node));
    $items[] = '<strong>Format:</strong> ' . Html::escape((string) $node->get('field_audio_format')->value);
    return "<div class='audio-node block'><div class=\"item-list\"><ul class=\"audio-info\"><li>" . implode("</li>\n<li>", $items) . '</li></ul></div></div>';
  }

  /**
   * "Download audio file • N downloads • N plays".
   */
  public static function links(NodeInterface $node): string {
    $file = $node->get('field_audio_file')->entity;
    $links = [];
    if ($file) {
      $url = Url::fromRoute('hobobiker.audio_download', ['node' => $node->id(), 'filename' => $file->getFilename()])->toString();
      $links[] = "<span class=\"audio_download_link\"><a href=\"$url\">Download audio file</a></span>";
    }
    $links[] = '<span class="audio_download_count">' . (int) $node->get('field_audio_downloads')->value . ' downloads</span>';
    $links[] = '<span class="audio_play_count">' . (int) $node->get('field_audio_plays')->value . ' plays</span>';
    return '<div class="nodeLinks">' . implode("\n&nbsp;&bull; &nbsp;", $links) . '</div>';
  }

}
