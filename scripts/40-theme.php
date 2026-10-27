<?php

/**
 * @file
 * The theme: `hobo`, an Olivero subtheme carrying the Drupal 6 theme's node meta lines (web/themes/custom/hobo).
 * Step 1-2 of the kit's theming ladder: Olivero's look, the original's words.
 *
 * Idempotent. Run: ddev drush php:script scripts/40-theme.php
 */

use Drupal\block\Entity\Block;

// Installed while Olivero is the default, the subtheme gets a copy of Olivero's block layout.
\Drupal::service('theme_installer')->install(['hobo']);
\Drupal::configFactory()->getEditable('system.theme')->set('default', 'hobo')->save();

// The main menu with every level, as the original showed it (Olivero's block stops at 2).
$block = Block::load('hobo_main_menu');
$block->set('settings', ['level' => 1, 'depth' => 0, 'expand_all_items' => TRUE] + $block->get('settings'))->save();
print "Theme: hobo (" . count(\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['theme' => 'hobo'])) . " blocks)\n";
