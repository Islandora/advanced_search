<?php

/**
 * @file
 * Post-update hooks for Advanced Search.
 */

use Drupal\Core\Utility\UpdateException;

/**
 * Enables legacy presentation for sites with Olivero installed.
 *
 * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
 *   A status message when the compatibility module was enabled, or NULL when
 *   no change was needed.
 *
 * @throws \Drupal\Core\Utility\UpdateException
 *   Thrown when the compatibility module cannot be enabled.
 */
function advanced_search_post_update_enable_olivero_compatibility(&$sandbox = NULL) {
  $installed_themes = \Drupal::config('core.extension')->get('theme') ?? [];
  if (!array_key_exists('olivero', $installed_themes)) {
    return NULL;
  }

  if (\Drupal::moduleHandler()->moduleExists('advanced_search_olivero')) {
    return NULL;
  }

  try {
    $installed = \Drupal::service('module_installer')
      ->install(['advanced_search_olivero']);
  }
  catch (\Throwable $exception) {
    throw new UpdateException(
      'Unable to enable Advanced Search Olivero compatibility.',
      0,
      $exception,
    );
  }

  if (!$installed) {
    throw new UpdateException(
      'Unable to enable Advanced Search Olivero compatibility.',
    );
  }

  return t('Advanced Search Olivero compatibility has been enabled.');
}
