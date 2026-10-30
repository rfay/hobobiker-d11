<?php

namespace Drupal\hobobiker\Plugin\views\pager;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsPager;
use Drupal\views\Plugin\views\pager\Full;

/**
 * The full pager as D6 paged a page past the end: D6 Views showed the first page, D6 core's pager_query() (the
 * blog module's pages) the last one with its content. Drupal 11 shows the last page in the pager, but empty.
 */
#[ViewsPager(
  id: "hobobiker_full",
  title: new TranslatableMarkup("Paged output, full pager (D6 Views)"),
  short_title: new TranslatableMarkup("Full (D6)"),
  help: new TranslatableMarkup("A page out of range shows the first or the last page, as D6 did."),
  theme: "pager",
  register_theme: FALSE
)]
class D6Full extends Full {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // 'first' (D6 Views) or 'last' (D6 pager_query()).
    $options['out_of_range'] = ['default' => 'first'];
    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * Runs before the main query gets its range, so the query and the pager Drupal builds from the request afterwards
   * both get the page D6 showed.
   */
  public function executeCountQuery(&$count_query) {
    $total = parent::executeCountQuery($count_query);
    $per_page = (int) $this->getItemsPerPage();
    $pages = $per_page ? (int) ceil($total / $per_page) : 0;
    if ($pages && $this->getCurrentPage() >= $pages) {
      $page = $this->options['out_of_range'] === 'last' ? $pages - 1 : 0;
      $this->setCurrentPage($page);
      $this->view->query->setOffset($page * $per_page + $this->getOffset());
      $request = \Drupal::requestStack()->getCurrentRequest();
      $query = array_pad(explode(',', (string) $request->query->get('page', '')), $this->options['id'] + 1, '0');
      $query[$this->options['id']] = (string) $page;
      $request->query->set('page', implode(',', $query));
    }
    return $total;
  }

}
