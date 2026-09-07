<?php

namespace Drupal\advanced_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\advanced_search\AdvancedSearchQuery;
use Drupal\advanced_search\SearchResultsToolbarBuilder;
use Drupal\views\Entity\View;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\advanced_search\Form\SettingsForm;
use Drupal\Core\Form\FormStateInterface;

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Builds toolbar controls for a View execution.
   *
   * @var \Drupal\advanced_search\SearchResultsToolbarBuilder
   */
  protected $toolbarBuilder;

  /**
   * Alters the View for configured recursive collection searches.
   *
   * @var \Drupal\advanced_search\AdvancedSearchQuery
   */
  protected $advancedSearchQuery;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\advanced_search\SearchResultsToolbarBuilder $toolbar_builder
   *   Builds toolbar controls from the executed View.
   * @param \Drupal\advanced_search\AdvancedSearchQuery $advanced_search_query
   *   Alters the View for configured recursive collection searches.
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Request $request,
    ConfigFactoryInterface $config_factory,
    SearchResultsToolbarBuilder $toolbar_builder,
    AdvancedSearchQuery $advanced_search_query,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->request = clone $request;
    $this->configFactory = $config_factory;
    $this->toolbarBuilder = $toolbar_builder;
    $this->advancedSearchQuery = $advanced_search_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getMainRequest(),
      $container->get('config.factory'),
      $container->get('advanced_search.search_results_toolbar_builder'),
      $container->get('advanced_search.query'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);

    $form['display-mode'] = [
      '#type' => 'fieldset',
      '#title' => $this->t("Pager Block"),
      '#description' => $this->t("If this settings are set here, they will override the global settings at `/admin/config/search/advanced`"),
    ];

    $form['display-mode']['override_list_on_off'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Expose "List view" option.'),
      '#default_value' => $this->configuration['override_list_on_off'] ?? $config->get(SettingsForm::DISPLAY_LIST_FLAG),
    ];

    $form['display-mode']['override_grid_on_off'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Expose "Grid view" option.'),
      '#default_value' => $this->configuration['override_grid_on_off'] ?? $config->get(SettingsForm::DISPLAY_GRID_FLAG),
    ];

    $form['display-mode']['override-default-display-mode'] = [
      '#type' => 'select',
      '#title' => $this
        ->t('Default view mode:'),
      '#options' => [
        'list' => $this->t('List'),
        'grid' => $this->t('Grid'),
      ],
      '#default_value' => $this->configuration['override-default-display-mode'] ?? $config->get(SettingsForm::DISPLAY_DEFAULT),
    ];

    $form['#attributes']['class'][] = 'clearfix';
    $form['#attached']['library'][] = 'advanced_search/advanced.search.admin';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration["override_list_on_off"] = $values["display-mode"]['override_list_on_off'];
    $this->configuration["override_grid_on_off"] = $values["display-mode"]['override_grid_on_off'];
    $this->configuration["override-default-display-mode"] = $values["display-mode"]['override-default-display-mode'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    [$view_id, $display_id] = $this->getViewAndDisplayIdentifiers();
    $view = View::Load($view_id);
    $view_executable = $view->getExecutable();
    $view_executable->setDisplay($display_id);
    // Allow advanced search to alter the query.
    $this->advancedSearchQuery->alterView(
      $this->request,
      $view_executable,
      $display_id,
    );
    $view_executable->execute();
    return $this->toolbarBuilder->build(
      $view_executable,
      $this->configuration,
    );
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
