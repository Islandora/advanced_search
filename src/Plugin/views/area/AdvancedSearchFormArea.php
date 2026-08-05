<?php

namespace Drupal\advanced_search\Plugin\views\area;

use Drupal\advanced_search\Form\AdvancedSearchForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Display\DisplayPluginManager;
use Drupal\search_api\IndexInterface;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders an Advanced Search form as part of a View display.
 */
#[ViewsArea('advanced_search_form')]
class AdvancedSearchFormArea extends AreaPluginBase {

  public const PLUGIN_ID = 'advanced_search_form';

  /**
   * Constructs an Advanced Search form area.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected DisplayPluginManager $displayPluginManager,
    protected FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.search_api.display'),
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['fields'] = ['default' => []];
    $options['context_filter'] = ['default' => ''];
    $options['empty']['default'] = TRUE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $fields = $this->getFields();
    $configured_fields = array_flip($this->options['fields'] ?? []);
    $rows = [];
    foreach ($fields as $identifier => $field) {
      $enabled = isset($configured_fields[$identifier]);
      $rows[$identifier] = [
        '#attributes' => ['class' => ['draggable']],
        'label' => ['#plain_text' => $field->getLabel()],
        'identifier' => ['#plain_text' => $identifier],
        'enabled' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable @field', ['@field' => $field->getLabel()]),
          '#title_display' => 'invisible',
          '#default_value' => $enabled,
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @field', ['@field' => $field->getLabel()]),
          '#title_display' => 'invisible',
          '#default_value' => $enabled ? $configured_fields[$identifier] : 0,
          '#delta' => max(count($fields), 10),
          '#attributes' => ['class' => ['advanced-search-field-weight']],
        ],
      ];
    }

    $form['fields'] = [
      '#type' => 'table',
      '#title' => $this->t('Search fields'),
      '#header' => [
        $this->t('Label'),
        $this->t('Field'),
        $this->t('Enabled'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('This View display does not expose a Search API index.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'advanced-search-field-weight',
        ],
      ],
    ] + $rows;

    $context_filter_options = ['' => $this->t('- None -')];
    foreach ($this->displayHandler->getOption('arguments') ?? [] as $id => $argument) {
      $field_id = $argument['field'] ?? '';
      $field_label = isset($fields[$field_id])
        ? $fields[$field_id]->getLabel()
        : $field_id;
      $context_filter_options[$id] = $field_label !== ''
        ? $this->t('@label: @id', ['@label' => $field_label, '@id' => $id])
        : $id;
    }
    $form['context_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Direct-child contextual filter'),
      '#description' => $this->t('Select the contextual filter to disable when Include Sub-Collections is selected.'),
      '#options' => $context_filter_options,
      '#default_value' => $this->options['context_filter'] ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $options = $form_state->getValue('options');
    $fields = array_filter(
      $options['fields'] ?? [],
      static fn (array $field): bool => !empty($field['enabled']),
    );
    uasort($fields, static function (array $a, array $b): int {
      return ((int) $a['weight']) <=> ((int) $b['weight']);
    });
    $options['fields'] = array_keys($fields);
    $form_state->setValue('options', $options);
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();
    if ($this->areaType !== 'header') {
      $errors[] = $this->t('The Advanced Search form must be placed in the View header.');
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if ($this->areaType !== 'header') {
      return [];
    }
    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    $fields = $this->getFields();
    $configured_fields = [];
    foreach ($this->options['fields'] ?? [] as $identifier) {
      if (isset($fields[$identifier])) {
        $configured_fields[$identifier] = $fields[$identifier];
      }
    }
    if (!$configured_fields) {
      return [];
    }

    $display = $this->view->storage->getDisplay($this->view->current_display);
    $context_filter = $this->options['context_filter'] ?? '';
    $title = $context_filter === ''
      ? $this->t('Search')
      : $this->t('Search within this collection');

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'advanced-search-form',
          'advanced-search-form-area',
          'islandora-advanced-search',
        ],
        'data-drupal-selector' => 'advanced-search-form',
      ],
      '#cache' => ['max-age' => 0],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $title,
        '#attributes' => [
          'class' => ['block-title', 'advanced-search-form-area__title'],
        ],
      ],
      'form' => $this->formBuilder->getForm(
        AdvancedSearchForm::class,
        $this->view->storage,
        $display,
        $configured_fields,
        $context_filter !== '' ? $context_filter : NULL,
        $this->view->dom_id ?: NULL,
      ),
    ];
  }

  /**
   * Gets Search API fields available to the current View display.
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   Fields keyed by their Search API identifier.
   */
  protected function getFields(): array {
    $index = $this->getIndex();
    if (!$index) {
      return [];
    }
    $fields = $index->getFields();
    uasort($fields, static function ($a, $b): int {
      return strcasecmp((string) $a->getLabel(), (string) $b->getLabel());
    });
    return $fields;
  }

  /**
   * Gets the Search API index for the current View display.
   */
  protected function getIndex(): ?IndexInterface {
    $display = $this->view->storage->getDisplay($this->view->current_display);
    $display_plugin = $display['display_plugin'] ?? '';
    if ($display_plugin === '') {
      return NULL;
    }

    $derivative_id = $this->view->id() . '__' . $this->view->current_display;
    try {
      $search_display = $this->displayPluginManager->createInstance(
        "views_{$display_plugin}:{$derivative_id}",
      );
      return method_exists($search_display, 'getIndex')
        ? $search_display->getIndex()
        : NULL;
    }
    catch (\Exception) {
      return NULL;
    }
  }

}
