<?php

namespace Drupal\hobobiker\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;

/**
 * An image as the original's lightbox2 formatter showed it: a small derivative linked to a large one, with the
 * alt text as a caption under it (Drupal 6 "imagefield__lightbox2__180x240__600x800").
 */
#[FieldFormatter(
  id: 'hobobiker_lightbox_image',
  label: new TranslatableMarkup('Linked image with caption (Drupal 6 lightbox2)'),
  field_types: ['image'],
)]
class LightboxImageFormatter extends ImageFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return ['image_style' => '180x240', 'link_style' => '600x800'] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $styles = image_style_options(FALSE);
    return [
      'image_style' => ['#type' => 'select', '#title' => $this->t('Image style'), '#options' => $styles, '#default_value' => $this->getSetting('image_style')],
      'link_style' => ['#type' => 'select', '#title' => $this->t('Linked image style'), '#options' => $styles, '#default_value' => $this->getSetting('link_style')],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $style = ImageStyle::load($this->getSetting('image_style'));
    $link = ImageStyle::load($this->getSetting('link_style'));
    $elements = [];
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $uri = $file->getFileUri();
      $caption = (string) $file->_referringItem->alt;
      $elements[$delta] = [
        '#theme' => 'hobobiker_photo',
        '#href' => $link->buildUrl($uri),
        '#src' => $style->buildUrl($uri),
        '#caption' => $caption,
        '#rel' => 'lightbox[' . $items->getName() . '][' . $caption . ']',
        '#cache' => ['tags' => array_merge($file->getCacheTags(), $style->getCacheTags(), $link->getCacheTags())],
      ];
    }
    return $elements;
  }

}
