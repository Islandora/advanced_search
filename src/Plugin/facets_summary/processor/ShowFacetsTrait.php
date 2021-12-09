<?php

namespace Drupal\advanced_search\Plugin\facets_summary\processor;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\facets\FacetInterface;

/**
 * Common logic to toggle the display of facets given a condition.
 */
trait ShowFacetsTrait {

  /**
   * Checks if the facet should be shown or not.
   */
  abstract protected function condition(FacetInterface $facet);

  /**
   * Classes to include on the shown facet.
   */
  abstract protected function classes();

  /**
   * {@inheritdoc}
   */
  protected function buildHelper(array $build, array $facets) {
    $request = \Drupal::request();
    $query_params = $request->query->all();
    foreach ($facets as $facet) {
      if ($this->condition($facet)) {
        $url_alias = $facet->getUrlAlias();
        $filter_key = $facet->getFacetSourceConfig()->getFilterKey() ?: 'f';
        $active_items = $facet->getActiveItems();
        foreach ($active_items as $active_item) {
          $url = Url::createFromRequest($request);
          $modified_query_params = $query_params;
          $modified_query_params[$filter_key] = array_filter($query_params[$filter_key], function ($query_param) use ($url_alias, $active_item) {
            $pos = strpos($query_param, ':');
            $alias = substr($query_param, 0, $pos);
            $value = substr($query_param, $pos + 1);
            return !($alias == $url_alias && $value == $active_item);
          });
          $url->setOption('query', $modified_query_params);
          $item = [
            '#theme' => 'facets_result_item__summary',
            '#value' => $active_item,
            // We do not have counts for excluded/missing facets...
            '#show_count' => FALSE,
            // Do not know the count.
            '#count' => 0,
            '#is_active' => TRUE,
            '#facet' => $facet,
            '#raw_value' => $active_item,
          ];
          $item = (new Link($item, $url))->toRenderable();
          $item['#wrapper_attributes'] = [
            'class' => $this->classes(),
          ];
          $build['#items'][] = $item;
        }
      }
    }
    return $build;
  }

}
