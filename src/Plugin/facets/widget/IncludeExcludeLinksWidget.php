<?php

namespace Drupal\islandora_advanced_search\Plugin\facets\widget;

use Drupal\facets\Plugin\facets\widget\LinksWidget;
use Drupal\Core\Link;
use Drupal\facets\Result\ResultInterface;

/**
 * The links widget.
 *
 * @FacetsWidget(
 *   id = "include_exclude_links",
 *   label = @Translation("List of links that allow the user to include / exclude facets."),
 *   description = @Translation("A simple widget that shows a list of +/- links."),
 * )
 */
class IncludeExcludeLinksWidget extends LinksWidget {

  /**
   * {@inheritdoc}
   */
  protected function prepareLink(ResultInterface $result) {
    $facet = $result->getFacet();
    $facet_source_id = $facet->getFacetSourceId();
    $facet_manager = \Drupal::service('facets.manager');
    $facets = $facet_manager->getFacetsByFacetSourceId($facet_source_id);
    $raw_value = $result->getRawValue();
    $count = $result->getCount();
    $url = $result->getUrl();
    $exclude_facet = $this->getExcludeFacet($facet, $facets);
    $exclude_result = $this->getExcludeResult($exclude_facet, $raw_value);
    $exclude_url = $exclude_result ? $exclude_result->getUrl() : NULL;
    return [
      '#theme' => 'facets_result_item',
      '#is_active' => $result->isActive(),
      '#value' => [
        'text' => (new Link($result->getDisplayValue(), $url))->toRenderable(),
        'include' => (new Link(' ', $url))->toRenderable() + [
          '#attributes' => [
            'class' => ['facet-item__include', 'fa', 'fa-plus'],
          ],
        ],
        'exclude' => $exclude_url ? (new Link(' ', $exclude_url))->toRenderable() + [
          '#attributes' => [
            'class' => ['facet-item__exclude', 'fa', 'fa-minus'],
          ],
        ] : NULL,
      ],
      '#show_count' => $this->getConfiguration()['show_numbers'] && ($count !== NULL),
      '#count' => $count,
      '#facet' => $facet,
      '#raw_value' => $raw_value,
    ];
  }

  /**
   * Looks for the excluded facet version of the included facet.
   */
  protected function getExcludeResult($facet, $raw_value) {
    if ($facet) {
      foreach ($facet->getResults() as $result) {
        if ($result->getRawValue() === $raw_value) {
          return $result;
        }
      }
    }
    return NULL;
  }

  /**
   * Looks for the excluded facet version of the included facet.
   */
  protected function getExcludeFacet($include, $facets) {
    $field_identifier = $include->getFieldIdentifier();
    foreach ($facets as $facet) {
      if ($field_identifier === $facet->getFieldIdentifier() && $facet->getExclude()) {
        return $facet;
      }
    }
    return NULL;
  }

}
