<?php

namespace Drupal\islandora_advanced_search\Plugin\facets_summary\processor;

use Drupal\facets_summary\FacetsSummaryInterface;
use Drupal\facets_summary\Processor\BuildProcessorInterface;
use Drupal\facets_summary\Processor\ProcessorPluginBase;
use Drupal\facets\FacetInterface;

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

  use ShowFacetsTrait;

  /**
   * {@inheritdoc}
   */
  protected function condition(FacetInterface $facet) {
    return !$facet->getExclude() && empty($facet->getResults());
  }

  /**
   * {@inheritdoc}
   */
  protected function classes() {
    return ['facet-summary-item--facet'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetsSummaryInterface $facets_summary, array $build, array $facets) {
    return $this->buildHelper($build, $facets);
  }

}
