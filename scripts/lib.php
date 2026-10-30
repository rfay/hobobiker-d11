<?php

/**
 * @file
 * Shared by the step scripts: the read-only connection to the Drupal 6 source (schema `d6`).
 */

use Drupal\Core\Database\Database;

// D6 text format id => the Drupal 11 format made from it by step 10 (0: none set, the D6 default).
const HOBO_FORMATS = [
  0 => 'filtered_html',
  1 => 'filtered_html',
  2 => 'php_code',
  3 => 'full_html_no_restrictions',
  5 => 'html_with_line_breaks',
  6 => 'wiki',
  7 => 'html_full_with_gmap',
  8 => 'full_html_with_email_obfuscation',
  9 => 'tripinfo_google_map',
  10 => 'flickr_full_html_linebreaks',
  11 => 'flickr_full_html',
];

/**
 * The Drupal 6 source database. Read only: never write to it.
 */
function d6(): \Drupal\Core\Database\Connection {
  if (!Database::getConnectionInfo('d6')) {
    $info = Database::getConnectionInfo('default')['default'];
    Database::addConnectionInfo('d6', 'default', ['database' => 'd6', 'prefix' => ''] + $info);
  }
  return Database::getConnection('default', 'd6');
}
