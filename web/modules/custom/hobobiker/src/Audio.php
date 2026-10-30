<?php

namespace Drupal\hobobiker;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * The D6 audio module's output, ported: the audio-info list, the length and format strings, the tag slugs.
 */
class Audio {

  /**
   * D6 audio_get_tag_settings() defaults: tag => browsable, in display order (weight, then this order).
   */
  const TAGS = ['artist' => TRUE, 'title' => TRUE, 'album' => TRUE, 'track' => FALSE, 'genre' => TRUE, 'year' => TRUE];

  /**
   * D6 audio_clean_tag(): the slug in audio/by/TAG/SLUG.
   */
  public static function clean(string $string): string {
    if (preg_match('/[^-\w]/', $string)) {
      $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string) ?: $string;
      $string = preg_replace('/[^-\w]+/', '_', $string);
    }
    return strtolower(trim($string, '_'));
  }

  /**
   * D6 format_size(): bytes, KB or MB (two decimals at most).
   */
  public static function size(int $bytes): string {
    if ($bytes < 1024) {
      return "$bytes bytes";
    }
    $kb = $bytes / 1024;
    return $kb < 1024 ? round($kb, 2) . ' KB' : round($kb / 1024, 2) . ' MB';
  }

  /**
   * D6 theme_audio_format_filelength(): "17:37 minutes (6.05 MB)".
   */
  public static function length(NodeInterface $node): string {
    // D6's recorded size (its getID3 capped some at 8 MB); Drupal 11 keeps the file's real size.
    $size = self::size((int) $node->get('field_audio_filesize')->value);
    $playtime = (string) $node->get('field_audio_playtime')->value;
    return $playtime === '' ? $size : "$playtime minutes ($size)";
  }

  /**
   * D6 theme_audio_format_fileformat(): "MP3 Mono 32kHz 48Kbps (CBR)".
   */
  public static function format(NodeInterface $node): string {
    $value = fn(string $name) => $node->get("field_audio_$name")->value;
    $format = '';
    if ($value('format')) {
      $format .= strtoupper($value('format')) . ' ';
    }
    if ($value('channel_mode')) {
      $format .= ucfirst($value('channel_mode')) . ' ';
    }
    if ($value('sample_rate')) {
      $format .= (int) ($value('sample_rate') / 1000) . 'kHz ';
    }
    if ($value('bitrate')) {
      $format .= (int) ($value('bitrate') / 1000) . 'Kbps ';
      if ($value('bitrate_mode')) {
        $format .= '(' . strtoupper($value('bitrate_mode')) . ')';
      }
    }
    return Html::escape($format);
  }

  /**
   * D6 theme_audio_display(): the tags (browsable ones linked), length and format, in ul.audio-info.
   */
  public static function display(NodeInterface $node): string {
    $items = [];
    foreach (self::TAGS as $tag => $browsable) {
      $value = $node->get("field_audio_$tag")->value;
      if ($value === NULL || $value === '') {
        continue;
      }
      $shown = $browsable
        ? '<a href="' . Url::fromUserInput("/audio/by/$tag/" . self::clean($value))->toString() . '">' . Html::escape($value) . '</a>'
        : Html::escape($value);
      $items[] = '<strong>' . ucwords($tag) . ':</strong> ' . $shown;
    }
    $items[] = '<strong>Length:</strong> ' . self::length($node);
    $items[] = '<strong>Format:</strong> ' . self::format($node);
    $list = '';
    foreach ($items as $i => $item) {
      $class = $i === 0 ? ' class="first"' : ($i === count($items) - 1 ? ' class="last"' : '');
      $list .= "<li$class>$item</li>\n";
    }
    return "<div class='audio-node block'>\n<div class=\"item-list\"><ul class=\"audio-info\">$list</ul></div></div>\n";
  }

  /**
   * The D6 download path: audio/download/NID/FILENAME.
   */
  public static function downloadUrl(NodeInterface $node): ?string {
    $file = $node->get('field_audio')->entity;
    return $file ? Url::fromUserInput('/audio/download/' . $node->id() . '/' . $file->getFilename(), ['absolute' => TRUE])->toString() : NULL;
  }

}
