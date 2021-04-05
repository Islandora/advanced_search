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
 *   id = "show_active_excluded_facets",
 *   label = @Translation("Show active excluded facets."),
 *   description = @Translation("When checked, negated facets will appear in the summary."),
 *   stages = {
 *     "build" = 20
 *   }
 * )
 */
class ShowActiveExcludedFacets extends ProcessorPluginBase implements BuildProcessorInterface {

  use ShowFacetsTrait;

  /**
   * {@inheritdoc}
   */
  protected function condition(FacetInterface $facet) {
    return $facet->getExclude();
  }

  /**
   * {@inheritdoc}
   */
  protected function classes() {
    return ['facet-summary-item--facet', 'facet-summary-item--exclude'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetsSummaryInterface $facets_summary, array $build, array $facets) {
    return $this->buildHelper($build, $facets);
  }

}
