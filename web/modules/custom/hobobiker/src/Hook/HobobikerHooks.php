<?php

namespace Drupal\hobobiker\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for hobobiker.
 */
class HobobikerHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'hobobiker_photo' => [
        'variables' => ['href' => NULL, 'src' => NULL, 'caption' => '', 'rel' => ''],
      ],
    ];
  }

}
