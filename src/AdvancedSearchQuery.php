<?php

namespace Drupal\advanced_search;

use Drupal\block\Entity\Block;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\advanced_search\Form\SettingsForm;
use Drupal\advanced_search\Plugin\Block\AdvancedSearchBlock;
use Drupal\search_api\Query\QueryInterface as DrupalQueryInterface;
use Drupal\views\ViewExecutable;
use Solarium\Core\Query\QueryInterface as SolariumQueryInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\search_api_solr\Utility\Utility as SearchAPISolrUtility;

/**
 * Alter current search query / view from using URL parameters.
 */
class AdvancedSearchQuery {

  use GetConfigTrait;

  // User can set this configuration for the module.
  const DEFAULT_QUERY_PARAM = 'a';
  const DEFAULT_RECURSE_PARAM = 'r';

  /**
   * The query parameter is how terms are passed to the query.
   *
   * @var string
   */
  protected $queryParameter;

  /**
   * The recurse parameter indicates the search should be recursive or not.
   *
   * @var string
   */
  protected $recurseParameter;

  /**
   * Constructs a FacetBlockAjaxController object.
   *
   * @param string $query_parameter
   *   The field to search against.
   * @param string $recurse_parameter
   *   The field that signifies the search should be recursive.
   */
  public function __construct(string $query_parameter = self::DEFAULT_QUERY_PARAM, string $recurse_parameter = self::DEFAULT_RECURSE_PARAM) {
    $this->queryParameter = $query_parameter;
    $this->recurseParameter = $recurse_parameter;
  }

  /**
   * Gets the query parameter to use that stores the search terms.
   *
   * @return string
   *   The query parameter to use that stores the search terms.
   */
  public static function getQueryParameter() {
    return self::getConfig(SettingsForm::SEARCH_QUERY_PARAMETER, self::DEFAULT_QUERY_PARAM);
  }

  /**
   * Gets the query parameter to use that stores the search terms.
   *
   * @return string
   *   The recurse parameter used to indicate that the search should be
   *   recursive.
   */
  public static function getRecurseParameter() {
    return self::getConfig(SettingsForm::SEARCH_RECURSIVE_PARAMETER, self::DEFAULT_RECURSE_PARAM);
  }

  /**
   * Extracts a list of AdvancedSearchQueryTerms from the given request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to parse terms from.
   *
   * @return \Drupal\advanced_search\AdvancedSearchQueryTerm[]
   *   A list of search terms.
   */
  public function getTerms(Request $request) {
    $terms = [];
    if ($request->query->has($this->queryParameter)) {
      $query_params = $request->query->all()[$this->queryParameter];
      if (is_array($query_params)) {
        foreach ($query_params as $params) {
          $terms[] = AdvancedSearchQueryTerm::fromQueryParams($params);
        }
      }
    }
    return array_filter($terms);
  }

  /**
   * Checks if the query should recursively include sub-collections.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to parse.
   *
   * @return bool
   *   TRUE if the search should recurse FALSE otherwise.
   */
  public function shouldRecurse(Request $request) {
    if ($request->query->has($this->recurseParameter)) {
      $recurse_param = $request->query->get($this->recurseParameter);
      return filter_var($recurse_param, FILTER_VALIDATE_BOOLEAN);
    }
    return FALSE;
  }

  /**
   * Checks if the all of the given terms are negations or not.
   *
   * @param \Drupal\advanced_search\AdvancedSearchQueryTerm[] $terms
   *   The terms to search for.
   *
   * @return bool
   *   TRUE if all terms are to be excluded otherwise FALSE.
   */
  protected function negativeQuery(array $terms) {
    foreach ($terms as $term) {
      if ($term->getInclude()) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Alters the given query using search terms provided in the given request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to parse terms from.
   * @param \Solarium\Core\Query\QueryInterface $solarium_query
   *   The solr query to modify.
   * @param \Drupal\search_api\Query\QueryInterface $search_api_query
   *   The search api query from which the solr query was build.
   */
  public function alterQuery(Request $request, SolariumQueryInterface &$solarium_query, DrupalQueryInterface $search_api_query) {
    // Only apply if a Advanced Search Query was made.
    $terms = $this->getTerms($request);
    if (!empty($terms)) {
      $index = $search_api_query->getIndex();
      /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
      $backend = $index->getServerInstance()->getBackend();
      $language_ids = $search_api_query->getLanguages();
      $field_mapping = $backend->getSolrFieldNamesKeyedByLanguage($language_ids, $index);

      // Disable for Lucene and wildcard
      //$q[] = "{!boost b=boost_document}";
      
      // Create a flag for active/inactive dismax.
      $config = \Drupal::config(SettingsForm::CONFIG_NAME);
      $isDismax = $config->get(SettingsForm::EDISMAX_SEARCH_FLAG);
      if (!isset($isDismax)) {
        $isDismax = TRUE;
      }
      $isSearchAllFields = FALSE;
      $fields_list = [];

      if (!$isDismax) {
        // To support negative queries we must first bring in all documents.
        $q[] = $this->negativeQuery($terms) ? "*:*" : "";
      }

      $term = array_shift($terms);
      $q[] = $term->toSolrQuery($field_mapping);

      // New.
      $fields_list[] = $term->toSolrFields($field_mapping);

      // Set edismax is enabled if the field set to "all".
      if ($term->getField() === "all") {
        $isSearchAllFields = TRUE;

      }

      // For multiple conditions.
      foreach ($terms as $term) {
        $q[] = $term->getConjunction();
        $q[] = $term->toSolrQuery($field_mapping);

        // New.
        $fields_list[] = $term->toSolrFields($field_mapping);

        // Set dismax is enabled if the field set to "all".
        if ($term->getField() === "all") {
          $isSearchAllFields = TRUE;

        }

      }
      $q = implode(' ', $q);

      // Limit extra processing if Luncene Search is enable.
      if ($isDismax) {
        // Enable dismax search query option.
        /** @var Solarium\QueryType\Select\Query\Component\DisMax $dismax */
        $dismax = $solarium_query->getEDisMax();
        $dismax->setQueryParser('edismax');
        $query_fields = [];

        if ($isSearchAllFields) {
          foreach ($field_mapping as $key => $field) {
            foreach ($field as $f => $item) {
              // bs_ are boolean fields, do not work well with text search.
              if (substr($item, 0, 3) !== "bs_") {
                array_push($query_fields, $item);
              }
            }
          }
        }
        else {
          $query_fields = $fields_list;

        }
        $query_fields = implode(" ", array_unique($query_fields));
        $dismax->setQueryFields($query_fields);
      }

      if ($backend->getConfiguration()['highlight_data']) {
        // Just highlight string and text fields to avoid Solr exceptions.
          $highlighted_fields = array_filter(array_unique($fields_list), function ($v) {
          return preg_match('/^t.*?[sm]_/', $v) || preg_match('/^s[sm]_/', $v);
        });

        if (empty($highlighted_fields)) {
          $highlighted_fields = ['*'];
        }

        $this->setHighlighting($solarium_query, $search_api_query, $highlighted_fields);

        // The Search API Highlight processor checks if the 'keys' field of
        // the Search API Query is non-empty before creating an excerpt.
        // Since we are getting the highlighting result from Solr instead
        // of using the Search API processor to create one, we just need
        // make this field non-empty.
        //$search_api_query->keys("advanced search");
      }

      $solarium_query->setQuery($q);
    }
  }

  /**
   * Alters the given view to be recursive if applicable.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to parse terms from.
   * @param \Drupal\views\ViewExecutable $view
   *   The view to modify.
   * @param string $display_id
   *   The view display to potentially alter.
   */
  public function alterView(Request $request, ViewExecutable $view, $display_id) {
    $views = Utilities::getAdvancedSearchViewDisplays();
    // Only specify contextual filters for views which the advanced search
    // blocks are derived from.
    $block_id = array_search([$view->id(), $display_id], $views);
    if ($block_id !== FALSE) {
      $block = Block::load($block_id);
      $settings = $block->get('settings');
      // Ignore the immediate children contextual filter in the query to allow
      // for recursive search.
      if (isset($settings[AdvancedSearchBlock::SETTING_CONTEXTUAL_FILTER])) {
        $display = $view->getDisplay();
        $display_arguments = $display->getOption('arguments');
        $immediate_children_contextual_filter = $settings[AdvancedSearchBlock::SETTING_CONTEXTUAL_FILTER];
        $index = array_search($immediate_children_contextual_filter, array_keys($display_arguments));
        if ($this->shouldRecurse($request)) {
          // Change the argument to the exception value which should cause the
          // contextual filter to be ignored.
          $view->args[$index] = $display_arguments[$immediate_children_contextual_filter]['exception']['value'];
        }
        else {
          // Explicitly set the default argument for AJAX requests.
          // We need to restore the default as that functionality is currently
          // broken. @see https://www.drupal.org/project/drupal/issues/3173778
          //
          // We fake the current request from the refer only to set the default
          // argument in case it is build from the URL. If this is not an AJAX
          // request this logic can be ignored.
          if ($request->isXmlHttpRequest()) {
            $view->initHandlers();
            $request_stack = \Drupal::requestStack();
            $refer = Request::create($request->server->get('HTTP_REFERER'));
            $refer->getPathInfo();
            $refer->attributes->add(\Drupal::getContainer()->get('router')->matchRequest($refer));
            $request_stack->push($refer);
            if (isset($view->argument[$immediate_children_contextual_filter])) {
              $plugin = $view->argument[$immediate_children_contextual_filter]->getPlugin('argument_default');
              if ($plugin) {
                $view->args[$index] = $plugin->getArgument();
              }
            }
            $request_stack->pop();
          }
        }
      }
    }
  }

  /**
   * Get query parameter for all search terms.
   *
   * @return \Drupal\Core\Url
   *   Url for the given request combined with search query parameters.
   */
  public function toUrl(Request $request, array $terms, bool $recurse, $route = NULL) {
    $query_params = $request->query->all();
    if ($route) {
      $url = Url::fromRoute($route);
      // The form that built the url may use AJAX, but we are redirecting to a
      // new page, so it should be disabled.
      unset($query_params[FormBuilderInterface::AJAX_FORM_REQUEST]);
      unset($query_params[MainContentViewSubscriber::WRAPPER_FORMAT]);
    }
    else {
      $url = Url::createFromRequest($request);
    }
    unset($query_params[$this->queryParameter]);
    foreach ($terms as $term) {
      $query_params[$this->queryParameter][] = $term->toQueryParams();
    }
    if ($recurse) {
      $query_params[$this->recurseParameter] = '1';
    }
    else {
      unset($query_params[$this->recurseParameter]);
    }
    $url->setOptions(['query' => $query_params]);
    return $url;
  }

  /**
   * Sets the highlighting parameters.
   *
   * @param \Solarium\Core\Query\QueryInterface $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $search_api_query
   *   The query object.
   * @param array $highlighted_fields
   *   (optional) The solr fields to be highlighted.
   */
  protected function setHighlighting(SolariumQueryInterface $solarium_query, DrupalQueryInterface $search_api_query, array $highlighted_fields = []) {
    $index = $search_api_query->getIndex();
    $settings = SearchAPISolrUtility::getIndexSolrSettings($index);
    $highlighter = $settings['highlighter'];

    $hl = $solarium_query->getHighlighting();
    $hl->setSimplePrefix('[HIGHLIGHT]');
    $hl->setSimplePostfix('[/HIGHLIGHT]');
    $hl->setSnippets($highlighter['highlight']['snippets']);
    $hl->setFragSize($highlighter['highlight']['fragsize']);
    $hl->setMergeContiguous($highlighter['highlight']['mergeContiguous']);
    $hl->setRequireFieldMatch($highlighter['highlight']['requireFieldMatch']);

    // Overwrite Solr default values only if required to have shorter request
    // strings.
    if (51200 != $highlighter['maxAnalyzedChars']) {
      $hl->setMaxAnalyzedChars($highlighter['maxAnalyzedChars']);
    }
    if ('gap' !== $highlighter['fragmenter']) {
      $hl->setFragmenter($highlighter['fragmenter']);
      if ('regex' !== $highlighter['fragmenter']) {
        $hl->setRegexPattern($highlighter['regex']['pattern']);
        if (0.5 != $highlighter['regex']['slop']) {
          $hl->setRegexSlop($highlighter['regex']['slop']);
        }
        if (10000 != $highlighter['regex']['maxAnalyzedChars']) {
          $hl->setRegexMaxAnalyzedChars($highlighter['regex']['maxAnalyzedChars']);
        }
      }
    }
    if (!$highlighter['usePhraseHighlighter']) {
      $hl->setUsePhraseHighlighter(FALSE);
    }
    if (!$highlighter['highlightMultiTerm']) {
      $hl->setHighlightMultiTerm(FALSE);
    }
    if ($highlighter['preserveMulti']) {
      $hl->setPreserveMulti(TRUE);
    }

    foreach ($highlighted_fields as $highlighted_field) {
      // We must not set the fields at once using setFields() to not break
      // the altered queries.
      $hl->addField($highlighted_field);
    }
  }

}
