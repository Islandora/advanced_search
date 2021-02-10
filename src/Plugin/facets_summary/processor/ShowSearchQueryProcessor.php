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
 *   id = "show_search_query",
 *   label = @Translation("Show the current search query"),
 *   description = @Translation("When checked, this facet will show the search query."),
 *   stages = {
 *     "build" = 40
 *   }
 * )
 */
class ShowSearchQueryProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetsSummaryInterface $facets_summary, array $build, array $facets) {
    $request = \Drupal::request();
    $query_params = $request->query->all();
    if (!empty($query_params['search_api_fulltext'])) {
      $text = $query_params['search_api_fulltext'];
      unset($query_params['search_api_fulltext']);
      $url = Url::createFromRequest($request);
      $url->setOption('query', $query_params);
      $item = [
        '#theme' => 'facets_result_item__summary',
        '#is_active' => FALSE,
        '#value' => $text,
        '#show_count' => FALSE,
      ];
      $item = Link::fromTextAndUrl($item, $url)->toRenderable();
      $item['#wrapper_attributes'] = [
        'class' => [
          'facet-summary-item--query',
        ],
      ];
      // This processor is weighted to occur after the reset facets link.
      // Which leaves two cases:
      // - No facets selected so no reset link (we must add one).
      // - Reset link exists at the top of the list (we must remove the search
      //   term from the link as well).
      $reset_index = $this->getResetLinkIndex($build);
      if ($reset_index !== NULL) {
        $reset = $build['#items'][$reset_index];
        // Remove query from reset url as well.
        $query_params = $reset['#url']->getOption('query');
        unset($query_params['search_api_fulltext']);
        $reset['#url']->setOption('query', $query_params);
        array_splice($build['#items'], $reset_index + 1, 0, [$item]);
      }
      else {
        array_unshift($build['#items'], $item);
        $text = $this->t('Reset');
        if (isset($facets_summary->getProcessorConfigs()['reset_facets']['settings']['link_text'])) {
          $text = $facets_summary->getProcessorConfigs()['reset_facets']['settings']['link_text'];
        }
        $reset = Link::fromTextAndUrl($text, $url)->toRenderable();
        $reset['#wrapper_attributes'] = [
          'class' => [
            'facet-summary-item--clear',
          ],
        ];
        array_unshift($build['#items'], $reset);
      }
      return $build;
    }
    return $build;
  }

  /**
   * Gets the index in the $build render array of the reset link.
   *
   * @param array $build
   *   The render array of the FacetSummary block.
   *
   * @return mixed|null
   *   The index of the reset link the $build render array.
   */
  protected function getResetLinkIndex(array $build) {
    if (isset($build['#items'])) {
      foreach ($build['#items'] as $index => $item) {
        if (isset($item['#wrapper_attributes']['class']) && in_array('facet-summary-item--clear', $item['#wrapper_attributes']['class'])) {
          return $index;
        }
      }
    }
    return NULL;
  }

}
