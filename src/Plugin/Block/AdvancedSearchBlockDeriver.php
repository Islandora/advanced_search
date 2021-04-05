<?php

namespace Drupal\islandora_advanced_search\Plugin\Block;

/**
 * Deriver for AdvancedSearchBlock.
 */
class AdvancedSearchBlockDeriver extends SearchApiDisplayBlockDeriver {

  /**
   * {@inheritdoc}
   */
  protected function label() {
    return $this->t('Advanced Search');
  }

}
