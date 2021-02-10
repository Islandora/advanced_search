<?php

namespace Drupal\islandora_advanced_search;

use Drupal\islandora_advanced_search\Form\AdvancedSearchForm;

/**
 * Defines a single search term.
 *
 * Used for parsing query parameters as well as form submission and generating
 * search queries.
 */
class AdvancedSearchQueryTerm {
  // Conjunctions.
  // @see https://lucene.apache.org/solr/guide/7_1/the-standard-query-parser.html#TheStandardQueryParser-BooleanOperatorsSupportedbytheStandardQueryParser
  const CONJUNCTION_AND = 'AND';
  const CONJUNCTION_OR = 'OR';

  // Used for serializing / deserializing query parameters.
  // These are also hard-coded in islandora_advanced_search.form.js.
  const CONJUNCTION_QUERY_PARAMETER = 'c';
  const FIELD_QUERY_PARAMETER = 'f';
  const INCLUDE_QUERY_PARAMETER = 'i';
  const VALUE_QUERY_PARAMETER = 'v';

  // Defaults.
  const DEFAULT_CONJUNCTION = self::CONJUNCTION_AND;
  const DEFAULT_INCLUDE = TRUE;

  /**
   * The field to search.
   *
   * @var string
   */
  protected $field;

  /**
   * Include / exclude results where 'value' is in the 'search' term.
   *
   * @var bool
   */
  protected $include = TRUE;

  /**
   * The value to filter with.
   *
   * @var string
   */
  protected $value;

  /**
   * The conjunction to use for the condition group – either 'AND' or 'OR'.
   *
   * @var string
   */
  protected $conjunction;

  /**
   * Constructs a FacetBlockAjaxController object.
   *
   * @param string $field
   *   The field to search against.
   * @param string $value
   *   The value to search the field with.
   * @param bool $include
   *   Limit results to records whose field contains or does not contain the
   *   given value.
   * @param string $conjunction
   *   The conjunction to apply when combining this search term along with
   *   others.
   */
  public function __construct(string $field, string $value, bool $include = self::DEFAULT_INCLUDE, string $conjunction = self::DEFAULT_CONJUNCTION) {
    $this->field = $field;
    $this->value = $value;
    switch ($conjunction) {
      case self::CONJUNCTION_AND:
      case self::CONJUNCTION_OR:
        $this->conjunction = $conjunction;
        break;

      default:
        throw new \InvalidArgumentException('Invalid value given for argument "conjunction": $conjunction');
    }
    if ($this->conjunction == self::CONJUNCTION_OR && !$include) {
      throw new \InvalidArgumentException('Excluding terms with the conjunction "OR" is not supported');
    }
    $this->include = $include;
  }

  /**
   * Validate 'include' or fallback to default value.
   *
   * @param string $include
   *   The value to cast to a boolean if possible.
   *
   * @return bool
   *   The normalized input for 'include' or its default.
   */
  protected static function normalizeInclude(string $include) {
    switch (strtoupper($include)) {
      case AdvancedSearchForm::IS_OP:
        return TRUE;

      case AdvancedSearchForm::NOT_OP:
        return FALSE;

      default:
        $include = filter_var($include, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        // Ignore include parameter if invalid and fallback to the default.
        return is_bool($include) ? $include : self::DEFAULT_INCLUDE;
    }
  }

  /**
   * Validate 'conjunction' or fallback to default value.
   *
   * @param string $conjunction
   *   The conjunction to validate.
   *
   * @return string
   *   The normalized input for 'include' or its default.
   */
  protected static function normalizeConjunction(string $conjunction) {
    switch (strtoupper($conjunction)) {
      case self::CONJUNCTION_AND:
        return self::CONJUNCTION_AND;

      case self::CONJUNCTION_OR:
        return self::CONJUNCTION_OR;

      default:
        return self::DEFAULT_CONJUNCTION;
    }
  }

  /**
   * Creates a AdvancedSearchQueryTerm from the given parameters if possible.
   *
   * @param array $params
   *   An array representing the query parameters for a single search term.
   *
   * @return \Drupal\islandora_advanced_search\AdvancedSearchQueryTerm|null
   *   An object which represents a valid search term.
   */
  public static function fromQueryParams(array $params) {
    // Field & value are required values. We do not check if field is a valid
    // value only that it is non-empty. All other fields will be cast to
    // defaults if they are not valid / missing.
    $has_required_params = isset($params[self::FIELD_QUERY_PARAMETER], $params[self::VALUE_QUERY_PARAMETER]);
    $search_value_empty = isset($params[self::VALUE_QUERY_PARAMETER]) && empty($params[self::VALUE_QUERY_PARAMETER]);
    if (!$has_required_params || $search_value_empty) {
      return NULL;
    }
    $field = $params[self::FIELD_QUERY_PARAMETER];
    $value = $params[self::VALUE_QUERY_PARAMETER];
    $include = isset($params[self::INCLUDE_QUERY_PARAMETER]) ?
      $include = self::normalizeInclude($params[self::INCLUDE_QUERY_PARAMETER]) :
      self::DEFAULT_INCLUDE;
    $conjunction = isset($params[self::CONJUNCTION_QUERY_PARAMETER]) ?
      self::normalizeConjunction($params[self::CONJUNCTION_QUERY_PARAMETER]) :
      self::DEFAULT_CONJUNCTION;
    return new self($field, $value, $include, $conjunction);
  }

  /**
   * Creates a AdvancedSearchQueryTerm from user submitted form values.
   *
   * @param array $input
   *   An array representing the submitted form values for a single search term.
   *
   * @return \Drupal\islandora_advanced_search\AdvancedSearchQueryTerm|null
   *   An object which represents a valid search term.
   */
  public static function fromUserInput(array $input) {
    // Search field & value are required values we do not check if field is a
    // valid value only that it is non-empty. All other fields will use
    // defaults if they are not valid / missing.
    $has_required_inputs = isset($input[AdvancedSearchForm::SEARCH_FORM_FIELD], $input[AdvancedSearchForm::VALUE_FORM_FIELD]);
    $search_value_empty = isset($input[AdvancedSearchForm::VALUE_FORM_FIELD]) && empty($input[AdvancedSearchForm::VALUE_FORM_FIELD]);
    if (!$has_required_inputs || $search_value_empty) {
      return NULL;
    }
    $field = $input[AdvancedSearchForm::SEARCH_FORM_FIELD];
    $value = $input[AdvancedSearchForm::VALUE_FORM_FIELD];
    $include = self::DEFAULT_INCLUDE;
    $conjunction = self::DEFAULT_CONJUNCTION;
    if (isset($input[AdvancedSearchForm::CONJUNCTION_FORM_FIELD])) {
      switch ($input[AdvancedSearchForm::CONJUNCTION_FORM_FIELD]) {
        case AdvancedSearchForm::AND_OP:
          $conjunction = self::CONJUNCTION_AND;
          break;

        case AdvancedSearchForm::OR_OP:
          $conjunction = self::CONJUNCTION_OR;
          break;
      }
    }
    // Only allow users to specify include when using 'AND' conjunction.
    if (
      $conjunction == self::CONJUNCTION_AND
      && isset($input[AdvancedSearchForm::INCLUDE_FORM_FIELD])
    ) {
      switch ($input[AdvancedSearchForm::INCLUDE_FORM_FIELD]) {
        case AdvancedSearchForm::IS_OP:
          $include = TRUE;
          break;

        case AdvancedSearchForm::NOT_OP:
          $include = FALSE;
          break;
      }
    }
    return new self($field, $value, $include, $conjunction);
  }

  /**
   * Get query parameter representation of this search term.
   *
   * @return array
   *   Representation of this search term which can be serialized to a query
   *   parameter.
   */
  public function toQueryParams() {
    $params = [
      self::FIELD_QUERY_PARAMETER => $this->field,
      self::VALUE_QUERY_PARAMETER => $this->value,
    ];
    // No need to specify conjunction if it is equivalent to the default.
    if ($this->conjunction != self::DEFAULT_CONJUNCTION) {
      $params[self::CONJUNCTION_QUERY_PARAMETER] = $this->conjunction;
    }
    if ($this->include != self::DEFAULT_CONJUNCTION) {
      $params[self::INCLUDE_QUERY_PARAMETER] = $this->include ? '1' : '0';
    }
    return $params;
  }

  /**
   * Get user input of search form representation of this search term.
   *
   * @return array
   *   Representation of this search term which can be used as input to the
   *   advanced search form.
   */
  public function toUserInput() {
    return [
      AdvancedSearchForm::SEARCH_FORM_FIELD => $this->field,
      AdvancedSearchForm::VALUE_FORM_FIELD => $this->value,
      AdvancedSearchForm::INCLUDE_FORM_FIELD => $this->include ? AdvancedSearchForm::IS_OP : AdvancedSearchForm::NOT_OP,
      AdvancedSearchForm::CONJUNCTION_FORM_FIELD => $this->conjunction == self::CONJUNCTION_AND ? AdvancedSearchForm::AND_OP : AdvancedSearchForm::OR_OP,
    ];
  }

  /**
   * Gets if this term should be included / excluded from results.
   *
   * @return bool
   *   TRUE if the term should be include in results, FALSE otherwise.
   */
  public function getInclude() {
    return $this->include;
  }

  /**
   * Gets the conjunction for this term.
   *
   * @return string
   *   The conjunction to use for this term.
   */
  public function getConjunction() {
    return $this->conjunction;
  }

  /**
   * Using the provided field mapping create a Solr Query string.
   *
   * @param array $solr_field_mapping
   *   An array that maps search api fields to one or more solr fields.
   *
   * @return string
   *   The conjunction to use for this term conjunction.
   */
  public function toSolrQuery(array $solr_field_mapping) {
    $terms = [];
    $query_helper = \Drupal::service('solarium.query_helper');
    $value = $query_helper->escapePhrase(trim($this->value));
    foreach ($solr_field_mapping[$this->field] as $field) {
      $terms[] = "$field:$value";
    }
    $terms = implode(' ', $terms);
    return $this->include ? "($terms)" : "-($terms)";
  }

}
