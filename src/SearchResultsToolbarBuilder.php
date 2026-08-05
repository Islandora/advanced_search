<?php

namespace Drupal\advanced_search;

use Drupal\advanced_search\Form\SettingsForm;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\pager\SqlBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds result controls for an already initialized Search API View.
 */
class SearchResultsToolbarBuilder {

  use StringTranslationTrait;

  /**
   * Constructs a search results toolbar builder.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected RequestStack $requestStack,
    protected CurrentPathStack $currentPath,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Builds result controls from the current View execution.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   An initialized and executed View.
   * @param array $configuration
   *   Optional per-instance display-mode overrides.
   * @param bool $integrated
   *   Whether the toolbar is rendered as a Views area instead of a block.
   *
   * @return array
   *   The toolbar render array.
   */
  public function build(ViewExecutable $view, array $configuration = [], bool $integrated = FALSE): array {
    $pager = $view->getPager();
    if (!$pager instanceof SqlBase) {
      return [];
    }

    $request = $this->requestStack->getMainRequest();
    $query_parameters = $request?->query->all() ?? [];
    $view_id = $view->id();
    $display_id = $view->current_display;
    $ajax_enabled = $view->ajaxEnabled();
    $view_dom_id = $view->dom_id;
    $id_parts = [
      'advanced-search-toolbar',
      $view_id,
      $display_id,
    ];
    if ($view_dom_id !== NULL && $view_dom_id !== '') {
      $id_parts[] = $view_dom_id;
    }
    $id_base = implode('-', $id_parts);
    $id = $view_dom_id !== NULL && $view_dom_id !== ''
      ? Html::getId($id_base)
      : Html::getUniqueId($id_base);

    $attributes = [
      'class' => ['advanced_search_result_pager'],
      'data-drupal-pager-id' => $id,
      'data-advanced-search-view-id' => $view_id,
      'data-advanced-search-display-id' => $display_id,
      'data-advanced-search-ajax-enabled' => $ajax_enabled ? 'true' : 'false',
    ];
    if ($view_dom_id !== NULL && $view_dom_id !== '') {
      $attributes['data-advanced-search-view-dom-id'] = $view_dom_id;
    }

    $build = [
      '#attached' => [
        'drupalSettings' => [
          'advanced_search_pager_views_ajax' => [
            $id => [
              'view_id' => $view_id,
              'current_display_id' => $display_id,
              'view_dom_id' => $view_dom_id,
              'ajax_enabled' => $ajax_enabled,
              'ajax_path' => Url::fromRoute('views.ajax')->toString(),
            ],
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
      '#attributes' => $attributes,
      'container' => [
        '#prefix' => '<div class="pager__group advanced-search-results-toolbar">',
        '#suffix' => '</div>',
        'result_summary' => $this->buildResultsSummary($view),
        'sort_by' => $this->buildSortByForm($view->sort, $query_parameters),
        'results_per_page_links' => $this->buildResultsPerPageLinks($pager, $query_parameters),
        'display_links' => $this->buildDisplayLinks($configuration, $query_parameters),
        'pager' => array_merge($pager->render($view->getExposedInput()), [
          '#wrapper_attributes' => ['class' => ['container']],
        ]),
      ],
    ];

    if ($integrated) {
      $build['#type'] = 'container';
      unset($build['container']['pager']);
    }

    return $build;
  }

  /**
   * Builds the current result range summary.
   */
  protected function buildResultsSummary(ViewExecutable $view): array {
    $current_page = (int) $view->getCurrentPage() + 1;
    $per_page = (int) $view->getItemsPerPage();
    $total = $view->total_rows ?? count($view->result);
    $start_offset = empty($total) ? 0 : 1;

    if ($per_page === 0) {
      $start = $start_offset;
      $end = $total;
    }
    else {
      $end = min($current_page * $per_page, $total);
      $start = ($current_page - 1) * $per_page + $start_offset;
    }

    if (empty($total)) {
      return [];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Displaying @start - @end of @total', [
        '@start' => $start,
        '@end' => $end,
        '@total' => $total,
      ]),
      '#attributes' => [
        'class' => ['pager__summary'],
        'role' => 'status',
        'aria-live' => 'polite',
        'aria-atomic' => 'true',
      ],
    ];
  }

  /**
   * Builds links for the exposed page sizes.
   */
  protected function buildResultsPerPageLinks(SqlBase $pager, array $query_parameters): array {
    $option_list = (string) ($pager->options['expose']['items_per_page_options'] ?? '');
    if ($option_list === '') {
      return [];
    }

    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);
    $active_items_per_page = $query_parameters['items_per_page'] ?? $pager->options['items_per_page'];
    $items_per_page_options = array_map('trim', explode(',', $option_list));
    $items = [];

    foreach ($items_per_page_options as $items_per_page) {
      if ($items_per_page === '') {
        continue;
      }
      $url = Url::fromUserInput($this->currentPath->getPath(), [
        'query' => array_merge($query_parameters, [
          'items_per_page' => $items_per_page,
          'page' => 0,
        ]),
        'absolute' => TRUE,
      ]);
      $active = $items_per_page == $active_items_per_page;
      $item = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => $items_per_page,
        '#attributes' => [
          'aria-label' => $active
            ? $this->t('Current page size: @item items per page', ['@item' => $items_per_page])
            : $this->t('@item items per page', ['@item' => $items_per_page]),
          'class' => $active
            ? ['pager__link', 'pager__link--is-active', 'pager__itemsperpage']
            : ['pager__link', 'pager__itemsperpage'],
          'itemsperpage' => $items_per_page,
        ],
        '#wrapper_attributes' => [
          'class' => $active ? ['pager__item', 'is-active'] : ['pager__item'],
        ],
      ];
      if ($active) {
        $item['#attributes']['aria-current'] = 'true';
      }
      if (!empty($config->get(SettingsForm::NO_FOLLOW))) {
        $item['#attributes']['rel'] = 'nofollow';
      }
      $items[] = $item;
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
   * Builds list/grid display links.
   */
  protected function buildDisplayLinks(array $configuration, array $query_parameters): array {
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);
    $has_overrides = isset(
      $configuration['override_list_on_off'],
      $configuration['override_grid_on_off'],
      $configuration['override-default-display-mode'],
    );
    $list_enabled = $has_overrides
      ? $configuration['override_list_on_off']
      : $config->get(SettingsForm::DISPLAY_LIST_FLAG);
    $grid_enabled = $has_overrides
      ? $configuration['override_grid_on_off']
      : $config->get(SettingsForm::DISPLAY_GRID_FLAG);
    $default_display = $has_overrides
      ? $configuration['override-default-display-mode']
      : $config->get(SettingsForm::DISPLAY_DEFAULT);
    $active_display = $query_parameters['display'] ?? $default_display;
    if (!in_array($active_display, ['list', 'grid'], TRUE)) {
      $active_display = in_array($default_display, ['list', 'grid'], TRUE)
        ? $default_display
        : 'list';
    }
    $display_options = [];

    if ($list_enabled) {
      $display_options['list'] = [
        'icon' => 'fa-list',
        'title' => $this->t('List'),
      ];
    }
    if ($grid_enabled) {
      $display_options['grid'] = [
        'icon' => 'fa-th',
        'title' => $this->t('Grid'),
      ];
    }

    $items = [];
    foreach ($display_options as $display => $options) {
      $url = Url::fromUserInput($this->currentPath->getPath(), [
        'query' => array_merge($query_parameters, ['display' => $display]),
        'absolute' => TRUE,
      ]);
      $active = $active_display == $display;
      $item = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => [
          'icon' => [
            '#type' => 'html_tag',
            '#tag' => 'i',
            '#value' => '',
            '#attributes' => [
              'class' => ['fa', $options['icon']],
              'aria-hidden' => 'true',
            ],
          ],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $options['title'],
            '#attributes' => ['class' => ['display-mode']],
          ],
        ],
        '#attributes' => [
          'class' => $active
            ? ['pager__link', 'pager__link--is-active', 'pager__display']
            : ['pager__link', 'pager__display'],
          'aria-label' => $active
            ? $this->t('Current display: @display', ['@display' => $options['title']])
            : $this->t('Display as @display', ['@display' => $options['title']]),
          'type' => $display,
        ],
        '#wrapper_attributes' => [
          'class' => $active ? ['pager__item', 'is-active'] : ['pager__item'],
        ],
      ];
      if ($active) {
        $item['#attributes']['aria-current'] = 'true';
      }
      if (!empty($config->get(SettingsForm::NO_FOLLOW))) {
        $item['#attributes']['rel'] = 'nofollow';
      }
      $items[] = $item;
    }

    if ($items) {
      return [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => $items,
        '#attributes' => [],
        '#wrapper_attributes' => ['class' => ['pager__display', 'container']],
      ];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $active_display,
      '#attributes' => [
        'class' => ['pager__display', 'container'],
        'hidden' => 'hidden',
        'id' => 'override-default-display-mode',
      ],
    ];
  }

  /**
   * Builds the exposed sort selector.
   */
  protected function buildSortByForm(array $sort_criteria, array $query_parameters): array {
    $default_order = $query_parameters['sort_order'] ?? 'DESC';
    $default_sort_by = $query_parameters['sort_by'] ?? 'search_api_relevance';
    $default_value = $default_sort_by . '_' . strtolower($default_order);
    $options = [];
    $options_attributes = [];

    foreach ($sort_criteria as $sort) {
      if (!empty($sort->options['exposed'])) {
        $id = $sort->options['id'];
        $label = $sort->options['expose']['label'];
        $asc = "{$id}_asc";
        $desc = "{$id}_desc";
        if ($id !== 'search_api_relevance') {
          $options[$asc] = $this->t('Sort by @label (ascending)', [
            '@label' => $label,
          ]);
          $options_attributes[$asc] = [
            'data-sort_by' => $id,
            'data-sort_order' => 'ASC',
          ];
        }
        $options[$desc] = $id === 'search_api_relevance'
          ? $this->t('Sort by @label', ['@label' => $label])
          : $this->t('Sort by @label (descending)', ['@label' => $label]);
        $options_attributes[$desc] = [
          'data-sort_by' => $id,
          'data-sort_order' => 'DESC',
        ];
      }
    }

    if (!array_key_exists($default_value, $options)) {
      $default_value = array_key_exists('search_api_relevance_desc', $options)
        ? 'search_api_relevance_desc'
        : array_key_first($options);
    }

    return [
      '#type' => 'select',
      '#title' => $this->t('Sort'),
      '#title_display' => 'invisible',
      '#options' => $options,
      '#options_attributes' => $options_attributes,
      '#attributes' => [
        'autocomplete' => 'off',
        'aria-label' => $this->t('Sort by'),
      ],
      '#wrapper_attributes' => ['class' => ['pager__sort', 'container']],
      '#name' => 'order',
      '#value' => $default_value,
    ];
  }

}
