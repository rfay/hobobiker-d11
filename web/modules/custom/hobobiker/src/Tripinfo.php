<?php

namespace Drupal\hobobiker;

use Drupal\Component\Utility\Html;

/**
 * The D6 tripinfo module's output, ported: the trip table, the day page, its navigation and the maps.
 *
 * The markup and wording are the original's, character for character where the tests can see them. The data
 * is the triplog nodes' fields (D6 read the same values from content_type_triplog).
 */
class Tripinfo {

  /**
   * D6 validate_date(): normalizes Y-M-D to zero-padded YYYY-MM-DD.
   */
  public static function validateDate(?string $date): string {
    [$y, $m, $d] = array_pad(explode('-', (string) $date, 3), 3, 0);
    return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
  }

  /**
   * Triplog rows, ordered by date (D6 read the CCK table, whose ties come in vid order).
   *
   * @param string $where
   *   An SQL condition on d.field_date_value and m.field_miles_value.
   */
  protected static function rows(string $where, array $args, string $order = 'ASC', ?int $limit = NULL): array {
    $sql = "SELECT n.nid, n.vid, d.field_date_value AS date, s.field_start_value AS start,
        de.field_destination_value AS destination, m.field_miles_value AS miles,
        e.field_elevation_gain_value AS elevgain, c.field_camp_or_hotel_value AS camp, b.body_value AS body
      FROM {node_field_data} n
      JOIN {node__field_date} d ON d.entity_id = n.nid
      LEFT JOIN {node__field_start} s ON s.entity_id = n.nid
      LEFT JOIN {node__field_destination} de ON de.entity_id = n.nid
      LEFT JOIN {node__field_miles} m ON m.entity_id = n.nid
      LEFT JOIN {node__field_elevation_gain} e ON e.entity_id = n.nid
      LEFT JOIN {node__field_camp_or_hotel} c ON c.entity_id = n.nid
      LEFT JOIN {node__body} b ON b.entity_id = n.nid
      WHERE n.type = 'triplog' AND $where
      ORDER BY d.field_date_value $order, n.vid ASC";
    if ($limit) {
      $sql .= " LIMIT $limit";
    }
    return \Drupal::database()->query($sql, $args)->fetchAll();
  }

  /**
   * D6 tripinfo_list(): the page at tripinfo/START/END.
   */
  public static function listPage(string $startdate, string $enddate): string {
    $startdate = self::validateDate($startdate);
    $enddate = self::validateDate($enddate);
    $page = '<div >';
    $page .= "<h4>$startdate to $enddate</h4>";
    $page .= "<div>
		Here is detailed information about our trip. For each day's
		ride we have a map, gpx and kml files, and an elevation profile.<br/><br/>
		If you find that this isn't up-to-date or you want something that's
		not here, please send us an email and we'll try to get it for you.
		</div>";
    $page .= self::table($startdate, $enddate);
    $page .= '</div>';
    return $page;
  }

  /**
   * D6 tripinfo_table(): the days ridden between two dates, each linking to its day page.
   */
  public static function table(string $startdate, string $enddate): string {
    $page = "<table class='tripinfo_table_table'>\n";
    $page .= "<tr><th>Ride</th><th>date</th><th>miles/km</th>
		<th>Elev ft/mt</th></tr>\n";
    // D6 compared its stored "YYYY-MM-DDT00:00:00" with the bare dates as strings, so the end date's day was never
    // listed: the same comparison on D6's form of the date.
    $rows = self::rows("CONCAT(d.field_date_value, 'T00:00:00') >= :start AND CONCAT(d.field_date_value, 'T00:00:00') <= :end AND m.field_miles_value <> 0",
      [':start' => $startdate, ':end' => $enddate]);
    foreach ($rows as $row) {
      $date = substr($row->date, 0, 10);
      $page .= '<tr>';
      $page .= "<th scope='row'>" . self::link("$row->start to $row->destination", "tripinfo_item/$date/$startdate/$enddate") . '</th>';
      $page .= "<td>$date</td>";
      $km = round($row->miles / .62);
      $elevgainmt = round((float) $row->elevgain / 3.24);
      $page .= "<td>$row->miles/$km</td><td>$row->elevgain/$elevgainmt</td>";
      $page .= '</tr>';
    }
    $page .= '</table>';
    return $page;
  }

  /**
   * D6 tripinfo_item(): one day's ride at tripinfo_item/DATE/START/END.
   *
   * @return array
   *   [markup, whether a map is on the page].
   */
  public static function item(string $date, string $startdate, string $enddate): array {
    $date = self::validateDate($date);
    $startdate = self::validateDate($startdate);
    $enddate = self::validateDate($enddate);
    $row = self::rows('d.field_date_value = :date', [':date' => $date], 'ASC', 1)[0] ?? NULL;
    [$year, $month, $day] = $row ? array_map('intval', explode('-', substr($row->date, 0, 10))) : [0, 0, 0];

    $fulldate = sprintf('%04d%02d%02d', $year, $month, $day);
    $gpxfname = "sites/default/files/gps/$fulldate.gpx";
    $kmlfname = "sites/default/files/gps/$fulldate.kml";
    $profile_fname = "sites/default/files/gps/$fulldate.png";
    $when = date('F d, Y', mktime(12, 0, 0, $month, $day, $year));

    $page = ($nav = self::navigation($date, $startdate, $enddate));
    $page .= "<h3>$when: {$row?->start} to {$row?->destination}</h3>";
    $map = file_exists(DRUPAL_ROOT . "/$kmlfname");
    if ($map) {
      $page .= self::mapDiv($kmlfname) . self::link('Explore in Google Earth', $kmlfname) . '</div></div>';
    }
    $page .= "<div class=\"tripdesc\" style='margin-top: 60px; border-top: 2px solid black;'><i>{$row?->body}</i></div>";
    $page .= '<ul>';
    $km = round((float) $row?->miles / .62);
    $meters = round((float) $row?->elevgain / 3.24);
    $page .= "<li>{$row?->miles} miles/$km kilometers</li>";
    $page .= "<li><a href='#profile'>Elevation Gain: {$row?->elevgain} feet/$meters meters</a></li>";
    $page .= "<li>Where we stayed: {$row?->camp}</li>";
    if (file_exists(DRUPAL_ROOT . "/$gpxfname")) {
      $page .= "<li><a href=\"/$gpxfname\">Download GPX file</a></li>";
    }
    if ($map) {
      $page .= "<li><a href=\"/$kmlfname\">Download KML file</a></li>";
    }
    $page .= '</ul>';
    if (file_exists(DRUPAL_ROOT . "/$profile_fname")) {
      $page .= "<hr /><a name='profile'><h4>Elevation Profile</h4></a>";
      $page .= "<img id='elevation_profile' src=\"/$profile_fname\">";
    }
    $page .= $nav;
    return [$page, $map];
  }

  /**
   * D6 tripinfo_navigation(): previous day, Up (the list), next day, with no space between the links.
   */
  protected static function navigation(string $date, string $startdate, string $enddate): string {
    // As on D6, the first and last days still get a previous/next link, to an empty day (" to ").
    $prev = self::rows('d.field_date_value < :date', [':date' => $date], 'DESC', 1)[0] ?? NULL;
    $next = self::rows('d.field_date_value > :date', [':date' => $date], 'ASC', 1)[0] ?? NULL;
    $links = self::link('‹ ' . "{$prev?->start} to {$prev?->destination}", 'tripinfo_item/' . substr((string) $prev?->date, 0, 10) . "/$startdate/$enddate",
      ['class' => 'page-previous', 'title' => 'Go to previous page']);
    $links .= self::link('Up', "tripinfo/$startdate/$enddate", ['class' => 'page-up', 'title' => 'Go back to list']);
    $links .= self::link("{$next?->start} to {$next?->destination}" . ' ›', 'tripinfo_item/' . substr((string) $next?->date, 0, 10) . "/$startdate/$enddate",
      ['class' => 'page-next', 'title' => 'Go to next page']);
    return '<div class="book-navigation"><div class="page-links clear-block">' . $links . '</div></div>';
  }

  /**
   * D6 tripinfo_gen_gmap(): the [multisource:URL, ...] macro. Only the first source is drawn.
   */
  public static function multisource(string $overlays): string {
    $kmlfile = trim(preg_replace('/,.*$/', '', $overlays));
    // The site's own absolute URLs become site-relative, without the cache-busting query.
    if (preg_match('@^https?://(www\.)?hobobiker\.com/\s*([^?]*)@', $kmlfile, $matches)) {
      $kmlfile = $matches[2];
    }
    return self::mapDiv($kmlfile) . self::link('Explore in Google Earth', $kmlfile) . '</div></div><br/><br/>';
  }

  /**
   * D6 tripinfo_map_div(): the map element; tripinfo_filtermap.js draws the file named in data-kml.
   */
  public static function mapDiv(string $kmlpath): string {
    return "<div style='height:500px;width:100%'><div class='mapholder' id='mapholder' style='width:100%;height:100%'>
	 <div class='filtermap tripinfo-map' id='filtermap' data-kml='" . Html::escape(self::url($kmlpath)) . "' style='width:100%;height:100%;border:2px solid black'></div>";
  }

  /**
   * D6 url() for a site path: the base path in front, an absolute URL as is.
   */
  protected static function url(string $path): string {
    return preg_match('@^[a-z]+://@i', $path) ? $path : base_path() . $path;
  }

  /**
   * D6 l(): a link with escaped text and attributes.
   */
  protected static function link(string $text, string $path, array $attributes = []): string {
    $attrs = '';
    foreach ($attributes as $name => $value) {
      $attrs .= " $name=\"" . Html::escape($value) . '"';
    }
    return '<a href="' . Html::escape(self::url($path)) . "\"$attrs>" . Html::escape($text) . '</a>';
  }

}
