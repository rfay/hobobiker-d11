<?php

namespace Drupal\hobobiker\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * D6 custom block 14, "Big Trip Stats", which was PHP code: the same figures from the triplog and wp nodes.
 *
 * The owner's rule is that no stored PHP runs, so the block's code lives here. Its output is the original's,
 * quirks included: the distance adds a fixed 275 miles, and "Longest day" shows only "km" because that cell used
 * a short open tag (<? ... ?>) that PHP never ran and the browser hid.
 */
#[Block(
  id: 'hobobiker_trip_stats',
  admin_label: new TranslatableMarkup('Big Trip Stats'),
)]
class TripStatsBlock extends BlockBase {

  const START = '2006-06-09';
  const END = '2009-06-30';

  /**
   * One aggregate over a triplog field in the trip's dates, like the block's general_oneitem_query().
   */
  protected static function triplog(string $aggregate, string $field, string $condition = '1 = 1'): float {
    $sql = "SELECT $aggregate(f.{$field}_value) FROM {node__$field} f
      JOIN {node__field_date} d ON d.entity_id = f.entity_id
      WHERE d.field_date_value >= :start AND d.field_date_value <= :end AND $condition";
    return (float) \Drupal::database()->query($sql, [':start' => self::START, ':end' => self::END])->fetchField();
  }

  /**
   * The block's milestokm().
   */
  protected static function km(float $miles): int {
    return (int) ($miles * 1.609);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $miles = (int) self::triplog('SUM', 'field_miles') + 275;
    $km = self::km($miles);
    $flats = (int) self::triplog('SUM', 'field_flat_tires');
    $shortestday = (int) self::triplog('MIN', 'field_miles', 'f.field_miles_value > 4');
    $avgday = self::triplog('AVG', 'field_miles', 'f.field_miles_value > 4');
    $wonderfulpeople = \Drupal::database()->query('SELECT COUNT(*) FROM {node__field_date_0} WHERE field_date_0_value >= :start AND field_date_0_value <= :end',
      [':start' => self::START, ':end' => self::END])->fetchField();

    $html = '<div style="border:2px">
    <TABLE class="statstable">
        <tr class="headerrow">
            <td colspan="2"><b>Final Trip Statistics</b></td>
        </tr>
        <tr>
            <td><a href="/triplog/2006-06-01--2009-09-09">Distance</a></td>
            <td>
                <a href="/triplog/2006-06-01--2009-09-09">' . "$miles mi/$km" . '
                    km</a></td>
        </tr>
        <tr>
            <td>Longest day:</td>
            <td> km</td>
        </tr>

        <tr>
            <td>Shortest day:</td>
            <td>' . "$shortestday mi/" . self::km($shortestday) . 'km
            </td>
        </tr>
        <tr>
            <td>Average day:</td>
            <td>' . (int) $avgday . ' mi/' . self::km($avgday) . '  km</td>
        </tr>
        <tr>
            <td><a href="/wp/2006-06-01--2009-09-09">Wonderful People:</a></td>
            <td>
                <a href="/wp/2006-06-01--2009-09-09">' . $wonderfulpeople . '</a>
            </td>
        </tr>
        <tr>
            <td>Flats:</td>
            <td>' . $flats . '</td>
        </tr>

    </table>
</div>';
    return ['#markup' => Markup::create($html), '#cache' => ['tags' => ['node_list:triplog', 'node_list:wp']]];
  }

}
