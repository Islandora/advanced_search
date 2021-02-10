<?php

namespace Drupal\islandora_advanced_search\Plugin\facets_summary\processor;

use Drupal\facets_summary\FacetsSummaryInterface;
use Drupal\facets_summary\Processor\BuildProcessorInterface;

/**
 * Reset should also remove the page query attribute.
 *
 * @SummaryProcessor(
 *   id = "reset_remove_page",
 *   label = @Translation("Remove page from query when resetting facets/query."),
 *   description = @Translation("Remove page from query when resetting facets/query."),
 *   stages = {
 *     "build" = 45
 *   }
 * )
 */
class ResetRemovePage extends ShowSearchQueryProcessor implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetsSummaryInterface $facets_summary, array $build, array $facets) {
    // This processor is weighted to occur after the reset facets link.
    // Which leaves two cases:
    // - No facets selected so no reset link (we must add one).
    // - Reset link exists at the top of the list (we must remove the
    //   search term from the link as well).
    $reset_index = $this->getResetLinkIndex($build);
    if ($reset_index !== NULL) {
      $reset = &$build['#items'][$reset_index];
      // Remove query from reset url as well.
      $query_params = $reset['#url']->getOption('query');
      unset($query_params['page']);
      $reset['#url']->setOption('query', $query_params);
    }
    return $build;
  }

}
