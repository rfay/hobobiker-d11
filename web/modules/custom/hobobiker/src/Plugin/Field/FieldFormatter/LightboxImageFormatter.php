<?php

namespace Drupal\hobobiker\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hobobiker\Plugin\Filter\PhotosFilter;
use Drupal\image\Entity\ImageStyle;

/**
 * D6 imagefield's lightbox2 formatter (imagefield__lightbox2__180x240__600x800) as hobo_golden themed it.
 *
 * The image at one style links to another for the lightbox, with the image's text (D6: its description) as alt
 * and as a caption line under the image.
 */
#[FieldFormatter(
  id: 'hobobiker_lightbox_image',
  label: new TranslatableMarkup('Lightbox image (D6)'),
  field_types: ['image'],
)]
class LightboxImageFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return ['image_style' => '180x240', 'link_style' => '600x800'] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $style = ImageStyle::load($this->getSetting('image_style'));
    $link = ImageStyle::load($this->getSetting('link_style'));
    $field = $items->getFieldDefinition()->getName();
    foreach ($items as $delta => $item) {
      if (!$file = $item->entity) {
        continue;
      }
      $uri = $file->getFileUri();
      $text = Html::escape((string) $item->alt);
      // The image is the link's only content, so its alt names the link: the stored alt, else the photo's Flickr
      // title or file name (the visible caption stays the stored alt only).
      $alt = $text !== '' ? $text : Html::escape(PhotosFilter::describe($uri) ?: (string) $this->t('Enlarge photo'));
      $preset = $style->id();
      $img = '<img src="' . Html::escape($style->buildUrl($uri)) . "\" alt=\"$alt\" title=\"\" width=\"180\" height=\"240\" class=\"imagecache imagecache-$preset\"/>";
      // The lightbox caption: the stored alt, else the description (not the "Enlarge photo" fallback).
      $caption = $text !== '' || $alt === Html::escape((string) $this->t('Enlarge photo')) ? $text : str_replace(['[', ']'], '', $alt);
      $markup = '<a href="' . Html::escape($link->buildUrl($uri)) . "\" rel=\"lightbox[$field][$caption]\" class=\"imagefield imagefield-lightbox2 imagecache imagecache-$preset\"><div>$img"
        . ($text !== '' ? "<br />$text<br />" : '') . '</div></a>';
      $elements[$delta] = ['#markup' => Markup::create($markup)];
    }
    return $elements;
  }

}
