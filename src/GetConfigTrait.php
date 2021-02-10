<?php

namespace Drupal\islandora_advanced_search;

use Drupal\islandora_advanced_search\Form\SettingsForm;

/**
 * Simple trait for accessing this modules configuration.
 */
trait GetConfigTrait {

  /**
   * Get a config setting or returns a default.
   *
   * @return string
   *   The config setting or default value.
   */
  protected static function getConfig($config, $default) {
    $settings = \Drupal::config(SettingsForm::CONFIG_NAME);
    $value = $settings->get($config);
    return !empty($value) ? $value : $default;
  }

}
