<?php

namespace Drupal\advanced_search\Plugin\facets_summary\processor;

use Drupal\Core\Link;
use Drupal\facets_summary\FacetsSummaryInterface;
use Drupal\facets_summary\Processor\BuildProcessorInterface;
use Drupal\facets_summary\Processor\ProcessorPluginBase;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;

/**
 * Provides a processor that shows the search query.
 *
 * @SummaryProcessor(
 *   id = "show_active_facets",
 *   label = @Translation("Shows active hidden facets."),
 *   description = @Translation("When checked, undoes 'hide_active_items_processor', etc."),
 *   stages = {
 *     "build" = 20
 *   }
 * )
 */
class ShowActiveFacets extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetsSummaryInterface $facets_summary, array $build, array $facets) {
    // Rebuild list of results, add back ones that have been removed.
    $facet_manager = \Drupal::service('facets.manager');
    $facet_source_id = $facets_summary->getFacetSourceId();
    $facet_manager->updateResults($facet_source_id);
    $facet_manager->processFacets($facet_source_id);
    $facets_config = $facets_summary->getFacets();
    foreach ($facets as $facet) {
      $processors = $facet->getProcessors();
      /** @var \Drupal\facets\Processor\BuildProcessorInterface $url_handler */
      $url_handler = $processors['url_processor_handler'];
      $results = $url_handler->build($facet, $facet->getResults());
      foreach ($results as $result) {
        if ($result->isActive() && $this->resultMissing($facet, $result, $build['#items'])) {
          $item = [
            '#theme' => 'facets_result_item__summary',
            '#value' => $result->getDisplayValue(),
            '#show_count' => $facets_config[$facet->id()]['show_count'],
            '#count' => $result->getCount(),
            '#is_active' => TRUE,
            '#facet' => $result->getFacet(),
            '#raw_value' => $result->getRawValue(),
          ];
          $item = (new Link($item, $result->getUrl()))->toRenderable();
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

  /**
   * Checks if the results are missing for the given facet.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet to check.
   * @param \Drupal\facets\Result\ResultInterface $result
   *   The result of the facet to check.
   * @param array $items
   *   The already completed render array of facets to check against.
   *
   * @return bool
   *   TRUE if the result is missing FALSE otherwise.
   */
  protected function resultMissing(FacetInterface $facet, ResultInterface $result, array $items) {
    foreach ($items as $item) {
      $item_facet = $item['#title']['#facet'];
      $raw_value = $item['#title']['#raw_value'];
      if ($item_facet === $facet && $raw_value === $result->getRawValue()) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
