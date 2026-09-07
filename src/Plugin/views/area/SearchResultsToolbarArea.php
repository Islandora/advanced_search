<?php

namespace Drupal\advanced_search\Plugin\views\area;

use Drupal\advanced_search\Form\SettingsForm;
use Drupal\advanced_search\SearchResultsToolbarBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders search result controls from the View's current execution.
 */
#[ViewsArea('advanced_search_results_toolbar')]
class SearchResultsToolbarArea extends AreaPluginBase {

  public const PLUGIN_ID = 'advanced_search_results_toolbar';

  /**
   * Constructs a search results toolbar area.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected SearchResultsToolbarBuilder $toolbarBuilder,
    protected ConfigFactoryInterface $configFactory,
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
      $container->get('advanced_search.search_results_toolbar_builder'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['override_list_on_off'] = ['default' => NULL];
    $options['override_grid_on_off'] = ['default' => NULL];
    $options['override-default-display-mode'] = ['default' => NULL];
    $options['empty']['default'] = TRUE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);

    $form['override_list_on_off'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose the List view option'),
      '#default_value' => $this->options['override_list_on_off'] ?? $config->get(SettingsForm::DISPLAY_LIST_FLAG),
    ];
    $form['override_grid_on_off'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose the Grid view option'),
      '#default_value' => $this->options['override_grid_on_off'] ?? $config->get(SettingsForm::DISPLAY_GRID_FLAG),
    ];
    $form['override-default-display-mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Default view mode'),
      '#options' => [
        'list' => $this->t('List'),
        'grid' => $this->t('Grid'),
      ],
      '#default_value' => $this->options['override-default-display-mode'] ?? $config->get(SettingsForm::DISPLAY_DEFAULT),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();
    if ($this->areaType !== 'header') {
      $errors[] = $this->t('The Advanced Search results toolbar must be placed in the View header.');
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
    return $this->toolbarBuilder->build($this->view, $this->options, TRUE);
  }

}
