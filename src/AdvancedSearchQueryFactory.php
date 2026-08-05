<?php

namespace Drupal\advanced_search;

use Drupal\advanced_search\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Creates advanced-search queries using the configured URL parameter names.
 */
final class AdvancedSearchQueryFactory {

  /**
   * Constructs an AdvancedSearchQueryFactory object.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Creates a configured advanced-search query helper.
   */
  public function create(): AdvancedSearchQuery {
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);

    return new AdvancedSearchQuery(
      $this->parameterName(
        $config->get(SettingsForm::SEARCH_QUERY_PARAMETER),
        AdvancedSearchQuery::DEFAULT_QUERY_PARAM,
      ),
      $this->parameterName(
        $config->get(SettingsForm::SEARCH_RECURSIVE_PARAMETER),
        AdvancedSearchQuery::DEFAULT_RECURSE_PARAM,
      ),
    );
  }

  /**
   * Normalizes one configured query-string parameter name.
   */
  private function parameterName(mixed $value, string $default): string {
    return is_string($value) && trim($value) !== '' ? trim($value) : $default;
  }

}
