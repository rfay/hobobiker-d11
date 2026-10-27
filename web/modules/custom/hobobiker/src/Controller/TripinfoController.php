<?php

namespace Drupal\hobobiker\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\hobobiker\TripInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Drupal 6 tripinfo module's pages: a trip's list of days, and one day.
 */
class TripinfoController extends ControllerBase {

  public function __construct(protected TripInfo $tripInfo) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('hobobiker.tripinfo'));
  }

  /**
   * /tripinfo/START/END.
   */
  public function list(string $startdate, string $enddate): array {
    $start = TripInfo::validateDate($startdate);
    $end = TripInfo::validateDate($enddate);
    $page = "<div ><h4>$start to $end</h4><div>
\t\tHere is detailed information about our trip. For each day's
\t\tride we have a map, gpx and kml files, and an elevation profile.<br/><br/>
\t\tIf you find that this isn't up-to-date or you want something that's
\t\tnot here, please send us an email and we'll try to get it for you.
\t\t</div>" . $this->tripInfo->table($start, $end) . '</div>';
    return ['#markup' => Markup::create($page), '#cache' => ['tags' => ['node_list:triplog']]];
  }

  /**
   * /tripinfo_item/DATE/START/END: one day, with the trip's prev/up/next links above and below.
   */
  public function item(string $date, string $startdate, string $enddate): array {
    $date = TripInfo::validateDate($date);
    $start = TripInfo::validateDate($startdate);
    $end = TripInfo::validateDate($enddate);
    $row = $this->tripInfo->day($date) ?? (object) ['date' => NULL, 'start' => '', 'destination' => '', 'miles' => '', 'elevgain' => '', 'stayed' => '', 'body' => ''];
    $build = ['#cache' => ['tags' => ['node_list:triplog']]];

    $nav = $this->navigation($date, $start, $end);
    [$y, $m, $d] = array_map('intval', explode('-', $row->date ?? '0-0-0'));
    $fulldate = sprintf('%04d%02d%02d', $y, $m, $d);
    $when = date('F d, Y', mktime(12, 0, 0, $m, $d, $y));
    $gpx = "sites/default/files/gps/$fulldate.gpx";
    $kml = "sites/default/files/gps/$fulldate.kml";
    $profile = "sites/default/files/gps/$fulldate.png";
    $exists = fn($path) => is_file(DRUPAL_ROOT . '/' . $path);

    $page = $nav . '<h3>' . Html::escape("$when: $row->start to $row->destination") . '</h3>';
    if ($exists($kml)) {
      $page .= $this->tripInfo->map($kml);
      $build['#attached']['library'][] = 'hobobiker/tripinfo_map';
    }
    // The day's body, unfiltered on the original; admin markup here.
    $page .= "<div class=\"tripdesc\" style='margin-top: 60px; border-top: 2px solid black;'><i>" . Xss::filterAdmin((string) $row->body) . '</i></div>';
    $km = round(((int) $row->miles) / .62);
    $meters = round(((int) $row->elevgain) / 3.24);
    $page .= "<ul><li>$row->miles miles/$km kilometers</li>";
    $page .= "<li><a href='#profile'>Elevation Gain: $row->elevgain feet/$meters meters</a></li>";
    $page .= '<li>Where we stayed: ' . Html::escape((string) $row->stayed) . '</li>';
    if ($exists($gpx)) {
      $page .= "<li><a href=\"/$gpx\">Download GPX file</a></li>";
    }
    if ($exists($kml)) {
      $page .= "<li><a href=\"/$kml\">Download KML file</a></li>";
    }
    $page .= '</ul>';
    if ($exists($profile)) {
      $page .= "<hr /><a name='profile'><h4>Elevation Profile</h4></a><img id='elevation_profile' src=\"/$profile\">";
    }
    $page .= $nav;
    $build['#markup'] = Markup::create($page);
    return $build;
  }

  /**
   * "‹ previous day", "Up" (the trip's list), "next day ›", run together as on the original.
   */
  protected function navigation(string $date, string $start, string $end): string {
    $link = fn(string $text, string $path, string $class, string $title) => '<a href="' . Url::fromUserInput($path)->toString()
      . "\" class=\"$class\" title=\"$title\">" . Html::escape($text) . '</a>';
    $prev = $this->tripInfo->neighbour($date, FALSE);
    $next = $this->tripInfo->neighbour($date, TRUE);
    $links = $link('‹ ' . $prev->title, "/tripinfo_item/$prev->item/$start/$end", 'page-previous', 'Go to previous page')
      . $link('Up', "/tripinfo/$start/$end", 'page-up', 'Go back to list')
      . $link($next->title . ' ›', "/tripinfo_item/$next->item/$start/$end", 'page-next', 'Go to next page');
    return '<div class="book-navigation"><div class="page-links clear-block">' . $links . '</div></div>';
  }

  /**
   * The list page's title (the day pages have none on the original).
   */
  public function listTitle(): string {
    return 'Hobobiker Trip Details';
  }

}
