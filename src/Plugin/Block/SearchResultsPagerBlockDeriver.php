<?php

namespace Drupal\advanced_search\Plugin\Block;

/**
 * This deriver creates a block for every search_api.display.
 */
class SearchResultsPagerBlockDeriver extends SearchApiDisplayBlockDeriver {

  /**
   * {@inheritdoc}
   */
  protected function label() {
    return $this->t('Search Results Pager');
  }

}
