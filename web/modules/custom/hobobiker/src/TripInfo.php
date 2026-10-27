<?php

namespace Drupal\hobobiker;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;

/**
 * The Drupal 6 tripinfo module's trip days, read from the imported triplog nodes, and its markup.
 *
 * Kept as the original computed it, quirks included (each one is noted where it happens): a range excludes its
 * last day, prev/next cross from one trip to another, a missing elevation prints as "/0".
 */
class TripInfo {

  public function __construct(protected Connection $database) {}

  /**
   * Trip days (triplog nodes), each with date, start, destination, miles, elevation, stay and body.
   *
   * Nine dates have two days. The original's queries had no tie-break, and MySQL returned them in table order, the
   * lower id first; so do these (ORDER BY nid as the last key).
   */
  protected function days(): \Drupal\Core\Database\Query\SelectInterface {
    $q = $this->database->select('node_field_data', 'n')->condition('n.type', 'triplog');
    $q->addField('n', 'nid');
    $fields = ['field_date' => 'date', 'field_start' => 'start', 'field_destination' => 'destination', 'field_miles' => 'miles',
      'field_elevation_gain' => 'elevgain', 'field_camp_or_hotel' => 'stayed'];
    foreach ($fields as $field => $alias) {
      $q->leftJoin("node__$field", $alias, "$alias.entity_id = n.nid AND $alias.deleted = 0");
      $q->addField($alias, $field . '_value', $alias);
    }
    $q->leftJoin('node__body', 'b', 'b.entity_id = n.nid AND b.deleted = 0');
    $q->addField('b', 'body_value', 'body');
    return $q;
  }

  /**
   * The day of $date, or NULL.
   */
  public function day(string $date): ?object {
    return $this->days()->condition('date.field_date_value', $date)->orderBy('n.nid')->range(0, 1)->execute()->fetchObject() ?: NULL;
  }

  /**
   * The previous or next day across all trips. The original made a link even when there was none: to
   * "tripinfo_item//START/END", titled " to ".
   */
  public function neighbour(string $date, bool $next): object {
    $row = $this->days()->condition('date.field_date_value', $date, $next ? '>' : '<')
      ->orderBy('date.field_date_value', $next ? 'ASC' : 'DESC')->orderBy('n.nid')->range(0, 1)->execute()->fetchObject();
    return (object) ['item' => $row->date ?? '', 'title' => ($row->start ?? '') . ' to ' . ($row->destination ?? '')];
  }

  /**
   * The table of days with miles from $start up to, but not including, $end. Drupal 6 compared
   * '2006-08-24T00:00:00' <= '2006-08-24' as strings, which drops the last day.
   */
  public function table(string $start, string $end): string {
    $rows = $this->days()->condition('date.field_date_value', $start, '>=')->condition('date.field_date_value', $end, '<')
      ->condition('miles.field_miles_value', 0, '<>')->orderBy('date.field_date_value')->orderBy('n.nid')->execute();
    $page = "<table class='tripinfo_table_table'>\n<tr><th>Ride</th><th>date</th><th>miles/km</th>\n\t\t<th>Elev ft/mt</th></tr>\n";
    foreach ($rows as $row) {
      $link = '<a href="' . Url::fromUserInput("/tripinfo_item/$row->date/$start/$end")->toString() . '">'
        . Html::escape("$row->start to $row->destination") . '</a>';
      $page .= "<tr><th scope='row'>$link</th><td>$row->date</td>";
      $page .= '<td>' . $row->miles . '/' . round($row->miles / .62) . '</td><td>' . $row->elevgain . '/' . round(((int) $row->elevgain) / 3.24) . '</td></tr>';
    }
    return $page . '</table>';
  }

  /**
   * One map: tripinfo-map.js draws the KML or KMZ file at $path (site-relative, no leading slash).
   */
  public function mapDiv(string $path): string {
    $url = Html::escape($this->url($path));
    return "<div style='height:500px;width:100%'><div class='mapholder' id='mapholder' style='width:100%;height:100%'>\n"
      . "\t <div class='filtermap tripinfo-map' id='filtermap' data-kml='$url' style='width:100%;height:100%;border:2px solid black'></div>";
  }

  /**
   * A map with its "Explore in Google Earth" link.
   */
  public function map(string $path): string {
    return $this->mapDiv($path) . '<a href="' . Html::escape($this->url($path)) . '">Explore in Google Earth</a></div></div>';
  }

  /**
   * A site-relative path gets the base path; an absolute URL stays as it is.
   */
  protected function url(string $path): string {
    return preg_match('@^https?://@', $path) ? $path : base_path() . ltrim($path, '/');
  }

  /**
   * Normalizes a date argument as the original did: "2003-0-01" becomes "2003-00-01".
   */
  public static function validateDate(string $date): string {
    [$y, $m, $d] = array_pad(explode('-', $date, 3), 3, 0);
    return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
  }

}
