<?php

namespace Drupal\advanced_search;

use Drupal\advanced_search\Form\AdvancedSearchForm;
use Drupal\advanced_search\Form\SettingsForm;

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
  // These are also hard-coded in advanced_search.form.js.
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
   * @return \Drupal\advanced_search\AdvancedSearchQueryTerm|null
   *   An object which represents a valid search term.
   */
  public static function fromQueryParams(array $params) {
    foreach ([
      self::FIELD_QUERY_PARAMETER,
      self::VALUE_QUERY_PARAMETER,
      self::INCLUDE_QUERY_PARAMETER,
      self::CONJUNCTION_QUERY_PARAMETER,
    ] as $parameter) {
      if (array_key_exists($parameter, $params) && !is_string($params[$parameter])) {
        return NULL;
      }
    }

    // Field and value are required scalar values. Field membership is checked
    // later, once the Search API index's Solr mapping is available.
    if (
      !isset($params[self::FIELD_QUERY_PARAMETER], $params[self::VALUE_QUERY_PARAMETER])
      || trim($params[self::FIELD_QUERY_PARAMETER]) === ''
      || trim($params[self::VALUE_QUERY_PARAMETER]) === ''
    ) {
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
    if ($conjunction === self::CONJUNCTION_OR && !$include) {
      return NULL;
    }
    return new self($field, $value, $include, $conjunction);
  }

  /**
   * Creates a AdvancedSearchQueryTerm from user submitted form values.
   *
   * @param array $input
   *   An array representing the submitted form values for a single search term.
   *
   * @return \Drupal\advanced_search\AdvancedSearchQueryTerm|null
   *   An object which represents a valid search term.
   */
  public static function fromUserInput(array $input) {
    // Search field & value are required values we do not check if field is a
    // valid value only that it is non-empty. All other fields will use
    // defaults if they are not valid / missing.
    foreach ([
      AdvancedSearchForm::SEARCH_FORM_FIELD,
      AdvancedSearchForm::VALUE_FORM_FIELD,
      AdvancedSearchForm::INCLUDE_FORM_FIELD,
      AdvancedSearchForm::CONJUNCTION_FORM_FIELD,
    ] as $component) {
      if (array_key_exists($component, $input) && !is_string($input[$component])) {
        return NULL;
      }
    }
    $has_required_inputs = isset($input[AdvancedSearchForm::SEARCH_FORM_FIELD], $input[AdvancedSearchForm::VALUE_FORM_FIELD]);
    $search_value_empty = isset($input[AdvancedSearchForm::VALUE_FORM_FIELD]) && trim($input[AdvancedSearchForm::VALUE_FORM_FIELD]) === '';
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
    $mapped_fields = $this->getMappedSolrFields($solr_field_mapping);
    if ($this->field !== 'all' && $mapped_fields === []) {
      return '';
    }
    $query_helper = \Drupal::service('solarium.query_helper');
    $value = $query_helper->escapePhrase(trim($this->value));

    $config = \Drupal::config(SettingsForm::CONFIG_NAME);
    $isDismax = $config->get(SettingsForm::EDISMAX_SEARCH_FLAG);
    if (!isset($isDismax)) {
      $isDismax = TRUE;
    }

    if ($isDismax || $this->field === "all") {

      // Case 1:  if keyword contains one word or a phrase.
      if (strpos(trim($value), ' ') !== FALSE) {
        // Add Or for the search case "scarborough bulletin" show no results.
        $isNot = $this->getInclude() ? "" : "-";
        if (substr_count($value, '\"') == 2) {
          $value = str_replace('\"', "", trim($value));
          return $isNot . $value;
        }
        else {
          return $isNot . "(" . $value . " OR " . str_replace('"', "", trim($value)) . ")";
        }
      }
      if (!$this->getInclude()) {
        $value = "!" . str_replace('"', "", trim($value));
      }
      else {
        // Case 2: keywords is one word
        // if there is quotation (with backslash) surrounded,.
        if (strpos(trim($value), '\"') !== FALSE) {
          $value = str_replace('\"', "", trim($value));
        }
        else {
          // If there is quotation (without backslash) surrounded.
          $value = str_replace('"', "", trim($value));
        }
      }
      // Fixed for https://github.com/digitalutsc/advanced_search/issues/4
      if ($this->field !== "all") {
        $search_fields = "(";
        foreach ($mapped_fields as $field) {
          $search_fields .= " $field:$value";
        }
        $search_fields .= ")";
        return $search_fields;
      }
      return $value;
    }
    else {
      $isTitleSearch = FALSE;
      foreach ($mapped_fields as $field) {
        // If field fulltext title is selected.
        if (strpos($field, "fulltext_title") !== FALSE) {
          $isTitleSearch = TRUE;
          if (strpos(trim($value), " AND ") !== FALSE) {
            // Handle keyword with 'Orientation AND games'.
            $keyword = str_replace('"', '', $value);
            $keys = explode(" AND ", $keyword);
            $str = "(";
            $i = 0;
            foreach ($keys as $key) {

              if ($i != count($keys) - 1) {
                $str .= $field . ':"' . $key . '"  AND ';
              }
              else {
                $str .= $field . ':"' . $key . '")';
              }
              $i++;
            }
            $terms[] = $str;
          }
          else {
            if ($isTitleSearch) {
              $terms[] = 'tm_lowercase_title:' . $value;
            }
            else {
              $terms[] = "$field:$value";
            }

          }
        }
        else {
          $terms[] = "$field:$value";
        }
      }

    }
    $terms = implode(' ', $terms);
    return $this->include ? "($terms)" : "-($terms)";
  }

  /**
   * Using the provided field mapping create a Solr Fields string.
   *
   * @param array $solr_field_mapping
   *   An array that maps search api fields to one or more solr fields.
   *
   * @return string
   *   The conjunction to use for this term conjunction.
   */
  public function toSolrFields(array $solr_field_mapping) {
    return implode(' ', $this->getMappedSolrFields($solr_field_mapping));
  }

  /**
   * Determines whether this term has a usable Solr field mapping.
   */
  public function hasSolrFieldMapping(array $solr_field_mapping): bool {
    return $this->field === 'all'
      || $this->getMappedSolrFields($solr_field_mapping) !== [];
  }

  /**
   * Gets valid Solr field names for this term.
   *
   * @return string[]
   *   Non-empty mapped Solr field names.
   */
  private function getMappedSolrFields(array $solr_field_mapping): array {
    if (
      $this->field === 'all'
      || !isset($solr_field_mapping[$this->field])
      || !is_array($solr_field_mapping[$this->field])
    ) {
      return [];
    }

    return array_values(array_filter(
      $solr_field_mapping[$this->field],
      static fn ($field): bool => is_string($field) && $field !== '',
    ));
  }

  /**
   * Get Field search.
   */
  public function getField() {
    return $this->field;
  }

}
