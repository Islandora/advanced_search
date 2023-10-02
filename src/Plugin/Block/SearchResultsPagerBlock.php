<?php

namespace Drupal\advanced_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\advanced_search\AdvancedSearchQuery;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\views\pager\SqlBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\advanced_search\Form\SettingsForm;

/**
 * Provides a 'AjaxViewBlock' block.
 *
 * @Block(
 *  id = "advanced_search_result_pager",
 *  deriver = "Drupal\advanced_search\Plugin\Block\SearchResultsPagerBlockDeriver",
 *  admin_label = @Translation("Search Results Pager"),
 *  category = @Translation("Islandora"),
 * )
 */
class SearchResultsPagerBlock extends BlockBase implements ContainerFactoryPluginInterface {
  use ViewAndDisplayIdentifiersTrait;

  /**
   * The clone of the current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Construct a FacetBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object for the current request.
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->request = clone $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getMainRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $id = $this->getDerivativeId();
    [$view_id, $display_id] = $this->getViewAndDisplayIdentifiers();
    $view = View::Load($view_id);
    $view_executable = $view->getExecutable();
    $view_executable->setDisplay($display_id);
    // Allow advanced search to alter the query.
    $advanced_search_query = new AdvancedSearchQuery();
    $advanced_search_query->alterView($this->request, $view_executable, $display_id);
    $view_executable->execute();
    $pager = $view_executable->getPager();
    $exposed_input = $view_executable->getExposedInput();
    $query_parameters = $this->request->query->all();
    $build = [
      '#attached' => [
        'drupalSettings' => [
          'advanced_search_pager_views_ajax' => [
            $id => [
              'view_id' => $view_id,
              'current_display_id' => $display_id,
              'ajax_path' => '/views/ajax',
            ],
          ],
        ],
      ],
      '#attributes' => [
        'class' => ['advanced_search_result_pager'],
        'data-drupal-pager-id' => $id,
      ],
      'result_summary' => $this->buildResultsSummary($view_executable),
      'container' => [
        '#prefix' => '<div class="pager__group">',
        '#suffix' => '</div>',
        'results_per_page_links' => $this->buildResultsPerPageLinks($pager, $query_parameters),
        'display_links' => $this->buildDisplayLinks($query_parameters),
        'sort_by' => $this->buildSortByForm($view_executable->sort, $query_parameters),
        'pager' => array_merge($pager->render($exposed_input), ['#wrapper_attributes' => ['class' => ['container']]]),
      ],
    ];
    return $build;
  }

  /**
   * Build the results summary portion of the pager.
   *
   * @param Drupal\views\ViewExecutable $view_executable
   *   The view to build the summary for.
   *
   * @return array
   *   A renderable array that represents the current page, and number of
   *   results in the view.
   */
  protected function buildResultsSummary(ViewExecutable $view_executable) {
    $current_page = (int) $view_executable->getCurrentPage() + 1;
    $per_page = (int) $view_executable->getItemsPerPage();
    $total = $view_executable->total_rows ?? count($view_executable->result);
    // If there is no result the "start" and "current_record_count" should be
    // equal to 0. To have the same calculation logic, we use a "start offset"
    // to handle all the cases.
    $start_offset = empty($total) ? 0 : 1;
    if ($per_page === 0) {
      $start = $start_offset;
      $end = $total;
    }
    else {
      $total_count = $current_page * $per_page;
      if ($total_count > $total) {
        $total_count = $total;
      }
      $start = ($current_page - 1) * $per_page + $start_offset;
      $end = $total_count;
    }
    if (!empty($total)) {
      // Return as render array.
      return [
        '#prefix' => '<div class="pager__summary">',
        '#suffix' => '</div>',
        '#markup' => $this->t('Displaying @start - @end of @total', [
          '@start' => $start,
          '@end' => $end,
          '@total' => $total,
        ]),
      ];
    }
    return [];
  }

  /**
   * Build the results per page portion of the pager.
   *
   * @param Drupal\views\Plugin\views\pager\SqlBase $pager
   *   The pager for the view.
   * @param array $query_parameters
   *   The query parameters used to change the number of results per page.
   *
   * @return array
   *   A renderable array representing the results per page portion of pager.
   */
  protected function buildResultsPerPageLinks(SqlBase $pager, array $query_parameters) {
    $active_items_per_page = $query_parameters['items_per_page'] ?? $pager->options['items_per_page'];
    $items_per_page_options = array_map(function ($value) {
      return trim($value);
    }, explode(',', $pager->options['expose']['items_per_page_options']));
    $items = [];
    foreach ($items_per_page_options as $items_per_page) {
      $url = Url::fromRoute('<current>', [], [
        // When changing the number of items displayed always return the user
        // to the first page.
        'query' => array_merge($query_parameters, [
          'items_per_page' => $items_per_page,
          'page' => 0,
        ]),
        'absolute' => TRUE,
      ]);
      $active = $items_per_page == $active_items_per_page;
      $items[] = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => $items_per_page,
        '#attributes' => [
          'aria-label' => $this->t("@item items per page", ["@item" => $items_per_page]),
          'class' => $active ?
            ['pager__link', 'pager__link--is-active', 'pager__itemsperpage'] :
            ['pager__link', 'pager__itemsperpage'],
        ],
        '#wrapper_attributes' => [
          'class' => $active ? ['pager__item', 'is-active'] : ['pager__item'],
        ],
      ];
    }
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Results per page'),
      '#list_type' => 'ul',
      '#items' => $items,
      '#attributes' => [],
      '#wrapper_attributes' => ['class' => ['pager__results', 'container']],
    ];
  }

  /**
   * Build the display links portion of the pager (list/grid).
   *
   * @param array $query_parameters
   *   The query parameters used to change the display format.
   *
   * @return array
   *   A renderable array representing the display links portion of pager.
   */
  protected function buildDisplayLinks(array $query_parameters) {
    $config = \Drupal::config(SettingsForm::CONFIG_NAME);
    $display_options = [];

    if ($this->configuration[SettingsForm::DISPLAY_LIST_FLAG] == 1) {
      $display_options['list'] = [
        'icon' => 'fa-list',
        'title' => $this->t('List'),
      ];
    }

    if ($this->configuration[SettingsForm::DISPLAY_GRID_FLAG] == 1) {
      $display_options['grid'] = [
        'icon' => 'fa-th',
        'title' => $this->t('Grid'),
      ];
    }

    $active_display = $query_parameters['display'] ?? $this->configuration[SettingsForm::DISPLAY_DEFAULT];
    $items = [];
    foreach ($display_options as $display => $options) {
      $url = Url::fromRoute('<current>', [], [
        'query' => array_merge($query_parameters, ['display' => $display]),
        'absolute' => TRUE,
      ]);
      $text = "<i class='fa {$options['icon']}' aria-hidden='true'>&nbsp;</i><span class='display-mode'>{$options['title']}</span>";
      $active = $active_display == $display;
      $items[] = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => Markup::create($text),
        '#attributes' => [
          'class' => $active ?
            ['pager__link', 'pager__link--is-active', 'pager__display'] :
            ['pager__link', 'pager__display'],
          'aria-label' => $this->t("Display as @link", ["@link" => Markup::create($text)]),
        ],
        '#wrapper_attributes' => [
          'class' => $active ? ['pager__item', 'is-active'] : ['pager__item'],
        ],
      ];
    }
    return [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $items,
      '#attributes' => [],
      '#wrapper_attributes' => ['class' => ['pager__display', 'container']],
    ];
  }

  /**
   * Build the sort by portion of the pager.
   *
   * @param array $sort_criteria
   *   The search fields which can be sorted.
   * @param array $query_parameters
   *   The query parameters used to change the display format.
   *
   * @return array
   *   A renderable array representing the sort by portion of pager.
   */
  protected function buildSortByForm(array $sort_criteria, array $query_parameters) {
    $default_order = $query_parameters['sort_order'] ?? 'DESC';
    $default_sort_by = $query_parameters['sort_by'] ?? 'search_api_relevance';
    $default_value = $default_sort_by . '_' . strtolower($default_order);
    $options = [];
    $options_attributes = [];
    // Not sure if this will work without defining a sort per direction.
    foreach ($sort_criteria as $sort) {
      if ($sort->options['exposed'] == TRUE) {
        $id = $sort->options['id'];
        // Label should be translated via views already.
        $label = $sort->options['expose']['label'];
        $asc = "{$id}_asc";
        $desc = "{$id}_desc";
        $options[$asc] = "{$label} ↓";
        $options[$desc] = "{$label} ↑";
        $options_attributes[$asc] = [
          'data-sort_by' => $id,
          'data-sort_order' => 'ASC',
        ];
        $options_attributes[$desc] = [
          'data-sort_by' => $id,
          'data-sort_order' => 'DESC',
        ];
      }
    }
    return [
      '#type' => 'select',
      '#title' => 'Sort',
      '#title_display' => 'invisible',
      '#options' => $options,
      '#options_attributes' => $options_attributes,
      '#attributes' => ['autocomplete' => 'off', "aria-label" => "Sort By"],
      '#wrapper_attributes' => ['class' => ['pager__sort', 'container']],
      '#name' => 'order',
      '#value' => $default_value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = \Drupal::service('config.factory')->getEditable(SettingsForm::CONFIG_NAME);
    return [
      SettingsForm::DISPLAY_LIST_FLAG => $config->get(SettingsForm::DISPLAY_LIST_FLAG),
      SettingsForm::DISPLAY_GRID_FLAG => $config->get(SettingsForm::DISPLAY_GRID_FLAG),
      SettingsForm::DISPLAY_DEFAULT => $config->get(SettingsForm::DISPLAY_DEFAULT),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['display-mode'] = [
      '#type' => 'fieldset',
      '#title' => $this->t("Pager Block"),
    ];

    $form['display-mode'][SettingsForm::DISPLAY_LIST_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Expose "List view" option.'),
      '#default_value' => $this->configuration[SettingsForm::DISPLAY_LIST_FLAG],
    ];

    $form['display-mode'][SettingsForm::DISPLAY_GRID_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Expose "Grid view" option.'),
      '#default_value' => $this->configuration[SettingsForm::DISPLAY_GRID_FLAG],
    ];

    $form['display-mode'][SettingsForm::DISPLAY_DEFAULT] = [
      '#type' => 'select',
      '#title' => $this
        ->t('Default view mode:'),
      '#options' => [
        'list' => 'List',
        'grid' => 'Grid',
      ],
      '#default_value' => $this->configuration[SettingsForm::DISPLAY_DEFAULT],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration[SettingsForm::DISPLAY_LIST_FLAG] = $values['display-mode'][SettingsForm::DISPLAY_LIST_FLAG];
    $this->configuration[SettingsForm::DISPLAY_GRID_FLAG] = $values['display-mode'][SettingsForm::DISPLAY_GRID_FLAG];
    $this->configuration[SettingsForm::DISPLAY_DEFAULT] = $values['display-mode'][SettingsForm::DISPLAY_DEFAULT];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // The block cannot be cached, because it must always match the current
    // search results.
    return 0;
  }

}
