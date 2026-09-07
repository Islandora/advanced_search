<?php

namespace Drupal\advanced_search;

use Drupal\advanced_search\Plugin\Block\AdvancedSearchBlock;
use Drupal\advanced_search\Plugin\Block\SearchResultsPagerBlock;
use Drupal\views\ViewExecutable;

/**
 * Helper functions.
 */
class Utilities {

  /**
   * Gets one configured Views header-area handler for a display.
   *
   * Advanced Search areas are intentionally header-only so the search form,
   * exposed filters, and result toolbar can be ordered inside one AJAX-owned
   * render region.
   *
   * @return array|null
   *   The handler configuration with its `handler_id`, or NULL when absent.
   */
  public static function getViewAreaConfiguration(
    ViewExecutable $view,
    string $display_id,
    string $plugin_id,
  ): ?array {
    if ($view->current_display !== $display_id) {
      return NULL;
    }

    $handlers = $view->display_handler->getOption('header') ?? [];
    foreach ($handlers as $handler_id => $configuration) {
      if (($configuration['plugin_id'] ?? '') === $plugin_id) {
        return ['handler_id' => $handler_id] + $configuration;
      }
    }
    return NULL;
  }

  /**
   * Gets the list of views for which pager blocks have been created.
   *
   * @return array
   *   List of view and display ids which have that have been used to
   *   derive a SearchResultsPagerBlock.
   */
  public static function getPagerViewDisplays() {
    $views = &drupal_static(__FUNCTION__);
    if (!isset($views)) {
      $block_storage = \Drupal::entityTypeManager()->getStorage('block');
      $active_theme = \Drupal::theme()->getActiveTheme();
      $views = [];
      /** @var \Drupal\block\Entity\Block $block */
      foreach ($block_storage->loadByProperties(['theme' => $active_theme->getName()]) as $block) {
        $plugin = $block->getPlugin();
        if ($plugin instanceof SearchResultsPagerBlock) {
          [$view_id, $display_id] = $plugin->getViewAndDisplayIdentifiers();
          $views[$block->id()] = [$view_id, $display_id];
        }
      }
    }
    return $views;
  }

  /**
   * Gets the list of views for which advanced search blocks have been created.
   *
   * @return array
   *   List of view and display ids which have that have been used to
   *   derive a SearchResultsPagerBlock.
   */
  public static function getAdvancedSearchViewDisplays() {
    $views = &drupal_static(__FUNCTION__);
    if (!isset($views)) {
      $block_storage = \Drupal::entityTypeManager()->getStorage('block');
      $active_theme = \Drupal::theme()->getActiveTheme();
      $views = [];
      /** @var \Drupal\block\Entity\Block $block */
      foreach ($block_storage->loadByProperties(['theme' => $active_theme->getName()]) as $block) {
        $plugin = $block->getPlugin();
        if ($plugin instanceof AdvancedSearchBlock) {
          [$view_id, $display_id] = $plugin->getViewAndDisplayIdentifiers();
          $views[$block->id()] = [$view_id, $display_id];
        }
      }
    }
    return $views;
  }

}
