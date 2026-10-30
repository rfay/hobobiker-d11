<?php

namespace Drupal\hobobiker\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * The D6 date module's date_argument with day granularity: START--END, each YYYY, YYYY-MM or YYYY-MM-DD.
 *
 * A partial start is the first day of its period, a partial end the last. An argument that is not such a range
 * matches nothing (D6: blog_in_order/1 lists nothing), and neither does one with a year outside the argument's
 * year_range, which D6 checked against the current year: relative ("-3:+3", the default) or absolute
 * ("2003:2020"). On the original that is why triplog/2006-06-01--2009-09-09 lists nothing today.
 */
#[ViewsArgument(id: 'hobobiker_date_range')]
class DateRange extends ArgumentPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['year_range'] = ['default' => '-3:+3'];
    $options['timezone'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['year_range'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Year range (D6): relative like -3:+3, or absolute like 2003:2020'),
      '#default_value' => $this->options['year_range'],
    ];
  }

  /**
   * One side of the range as YYYY-MM-DD, or NULL if it is not a date.
   */
  protected static function side(string $value, bool $end): ?string {
    if (!preg_match('/^(\d{4})(?:-(\d{1,2})(?:-(\d{1,2}))?)?$/', $value, $m)) {
      return NULL;
    }
    $year = (int) $m[1];
    $month = isset($m[2]) ? (int) $m[2] : ($end ? 12 : 1);
    if ($month < 1 || $month > 12) {
      return NULL;
    }
    $last = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $day = isset($m[3]) ? (int) $m[3] : ($end ? $last : 1);
    if ($day < 1 || $day > $last) {
      return NULL;
    }
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
  }

  /**
   * The range as [start, end] (YYYY-MM-DD), or NULL.
   */
  public static function parse(string $argument): ?array {
    $parts = explode('--', $argument, 2);
    $start = self::side($parts[0], FALSE);
    $end = self::side($parts[1] ?? $parts[0], TRUE);
    return $start && $end ? [$start, $end] : NULL;
  }

  /**
   * Whether both ends of the range fall within the year range.
   */
  protected function inYearRange(array $range): bool {
    [$from, $to] = array_map('trim', explode(':', $this->options['year_range']) + [1 => '']);
    $now = (int) date('Y');
    $bound = fn(string $v) => preg_match('/^[+-]/', $v) ? $now + (int) $v : (int) $v;
    $years = [(int) substr($range[0], 0, 4), (int) substr($range[1], 0, 4)];
    return min($years) >= $bound($from) && max($years) <= $bound($to);
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    $range = self::parse((string) $this->argument);
    if (!$range || !$this->inYearRange($range)) {
      $this->query->addWhereExpression(0, '1 = 0');
      return;
    }
    [$start, $end] = $range;
    $field = "$this->tableAlias.$this->realField";
    if (!empty($this->definition['timestamp'])) {
      // A timestamp column: whole days in the argument's timezone (D6's date module: its site timezone).
      $timezone = new \DateTimeZone($this->options['timezone'] ?: date_default_timezone_get());
      $start = (new \DateTime("$start 00:00:00", $timezone))->getTimestamp();
      $end = (new \DateTime("$end 23:59:59", $timezone))->getTimestamp();
    }
    $this->query->addWhere(0, $field, [$start, $end], 'BETWEEN');
  }

}
