<?php

namespace Drupal\islandora_advanced_search\Plugin\facets_summary\processor;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\facets_summary\FacetsSummaryInterface;
use Drupal\facets_summary\Processor\BuildProcessorInterface;
use Drupal\facets_summary\Processor\ProcessorPluginBase;

/**
 * Provides a processor that shows the search query.
 *
 * @SummaryProcessor(
 *   id = "show_missing_facets",
 *   label = @Translation("Shows facets from the url that are missing from the results."),
 *   description = @Translation("When checked, show facets not included in the solr result but specified in the URL."),
 *   stages = {
 *     "build" = 20
 *   }
 * )
 */
class ShowMissingFacets extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetsSummaryInterface $facets_summary, array $build, array $facets) {
    $request = \Drupal::request();
    $query_params = $request->query->all();
    foreach ($facets as $facet) {
      if (!$facet->getExclude() && empty($facet->getResults())) {
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
          // We do not have counts for missing facets...
            '#show_count' => FALSE,
          // Do not know the count.
            '#count' => 0,
            '#is_active' => TRUE,
            '#facet' => $facet,
            '#raw_value' => $active_item,
          ];
          $item = (new Link($item, $url))->toRenderable();
          $item['#wrapper_attributes'] = [
            'class' => [
              'facet-summary-item--facet',
            ],
          ];
          $build['#items'][] = $item;
        }
      }
    }
    return $build;
  }

}
