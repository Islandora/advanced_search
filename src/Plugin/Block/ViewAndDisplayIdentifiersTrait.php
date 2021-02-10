<?php

namespace Drupal\islandora_advanced_search\Plugin\Block;

/**
 * Gets the view and display identifiers used to create this block.
 *
 * @see Drupal\Component\Plugin\Discovery\DiscoveryInterface
 */
trait ViewAndDisplayIdentifiersTrait {

  /**
   * {@inheritdoc}
   */
  abstract public function getDerivativeId();

  /**
   * Gets the View and View Display identifiers used to derive this block.
   *
   * @return string[]
   *   Returns an array of two strings where the first is the View identifier
   *   and the second is the View Display identifier associated with the view
   *   used to derive this block.
   */
  public function getViewAndDisplayIdentifiers() {
    $id = $this->getDerivativeId();
    return preg_split('/__/', $id, 2);
  }

}
