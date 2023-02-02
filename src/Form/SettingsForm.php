<?php

namespace Drupal\advanced_search\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\advanced_search\AdvancedSearchQuery;
use Drupal\advanced_search\GetConfigTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config form for Islandora Advanced Search settings.
 */
class SettingsForm extends ConfigFormBase {

  use GetConfigTrait;

  const CONFIG_NAME = 'advanced_search.settings';
  const SEARCH_QUERY_PARAMETER = 'search_query_parameter';
  const SEARCH_RECURSIVE_PARAMETER = 'search_recursive_parameter';
  const SEARCH_ADD_OPERATOR = 'search_add_operator';
  const SEARCH_REMOVE_OPERATOR = 'search_remove_operator';
  const FACET_TRUNCATE = 'facet_truncate';
  const EDISMAX_SEARCH_FLAG = 'lucene_on_off';
  const EDISMAX_SEARCH_LABEL = 'lucene_label';
  const SEARCH_ALL_FIELDS_FLAG = 'all_fields_on_off';
  const DISPLAY_LIST_FLAG = 'list_on_off';
  const DISPLAY_GRID_FLAG = 'grid_on_off';
  const DISPLAY_DEFAULT = 'default-display-mode';
  
  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->setConfigFactory($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'advanced_search_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form += [
      'search' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Advanced Search'),
        self::SEARCH_QUERY_PARAMETER => [
          '#type' => 'textfield',
          '#title' => $this->t('Search Query Parameter'),
          '#description' => $this->t('The url parameter in which the advanced search query is stored.'),
          '#default_value' => AdvancedSearchQuery::getQueryParameter(),
        ],
        self::SEARCH_RECURSIVE_PARAMETER => [
          '#type' => 'textfield',
          '#title' => $this->t('Recurse Query Parameter'),
          '#description' => $this->t('The url parameter which can toggle recursive search.'),
          '#default_value' => AdvancedSearchQuery::getRecurseParameter(),
        ],
        self::SEARCH_ADD_OPERATOR => [
          '#type' => 'textfield',
          '#title' => $this->t('Facet Add Operator'),
          '#description' => $this->t('Users can customize the operator for adding facets to use font-awesome or some other icon, etc.'),
          '#default_value' => AdvancedSearchForm::getAddOperator(),
        ],
        self::SEARCH_REMOVE_OPERATOR => [
          '#type' => 'textfield',
          '#title' => $this->t('Facet Remove Operator'),
          '#description' => $this->t('Users can customize the operator for removing facets to use font-awesome or some other icon, etc.'),
          '#default_value' => AdvancedSearchForm::getRemoveOperator(),
        ],
      ],
      'facets' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Facets'),
        self::FACET_TRUNCATE => [
          '#type' => 'number',
          '#title' => $this->t('Truncate Facet'),
          '#description' => $this->t('Optionally truncate the length of facets titles in the display. If unspecified they will not be truncated.'),
          '#default_value' => self::getConfig(self::FACET_TRUNCATE, 32),
          '#min' => 1,
        ],
      ],
    ];

    $form['display-mode'] =  [
      '#type' => 'fieldset',
      '#title' => $this->t("Display Mode"),
    ];
    
    $form['display-mode'][self::DISPLAY_LIST_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Enable List View.'),
      '#default_value' => self::getConfig(self::DISPLAY_LIST_FLAG, 0),
    ];

    $form['display-mode'][self::DISPLAY_GRID_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Enable Grid View.'),
      '#default_value' => self::getConfig(self::DISPLAY_GRID_FLAG, 0),
    ];

    $form['display-mode'][self::DISPLAY_DEFAULT] = [
      '#type' => 'select',
      '#title' => $this
        ->t('Default display mode:'),
      '#options' => [
        'list' => 'List',
        'grid' => 'Grid'
      ],
      '#default_value' => self::getConfig(self::DISPLAY_DEFAULT, 'grid'),
    ];

    $form['edismax'] =  [
      '#type' => 'fieldset',
      '#title' => $this->t("Extended DisMax Query"),
    ];

    $form['edismax'][self::EDISMAX_SEARCH_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Enable Extended DisMax Query.'),
      '#default_value' => self::getConfig(self::EDISMAX_SEARCH_FLAG, 1),
      '#ajax' => [
        'callback' => '::LuceneSearchEnableDisableCallback',
        'wrapper' => 'edismax-container',
        'effect' => 'fade',
      ],
    ];

    $form['edismax']['textfields_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edismax-container'],
    ];

    if (self::getConfig(self::EDISMAX_SEARCH_FLAG, "All") === 1
       || $form_state->getValue(self::EDISMAX_SEARCH_FLAG) === 1) {
      $form['edismax']['textfields_container'][self::SEARCH_ALL_FIELDS_FLAG] = [
        '#type' => 'checkbox',
        '#title' => $this
          ->t('Enable searching all fields'),
        '#default_value' => self::getConfig(self::SEARCH_ALL_FIELDS_FLAG, 0),
      ];
      $form['edismax']['textfields_container'][self::EDISMAX_SEARCH_LABEL] = [
        '#type' => 'textfield',
        '#title' => $this->t('If enabled, set the label for the option of searching all fields'),
        '#description' => $this->t('This label will be appear in Search Terms dropdown of Advanced Search form block if Lucene Search is enabled.'),
        '#default_value' => self::getConfig(self::EDISMAX_SEARCH_LABEL, "All"),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);
    $config
      ->set(self::SEARCH_QUERY_PARAMETER, $form_state->getValue(self::SEARCH_QUERY_PARAMETER))
      ->set(self::SEARCH_RECURSIVE_PARAMETER, $form_state->getValue(self::SEARCH_RECURSIVE_PARAMETER))
      ->set(self::SEARCH_ADD_OPERATOR, $form_state->getValue(self::SEARCH_ADD_OPERATOR))
      ->set(self::SEARCH_REMOVE_OPERATOR, $form_state->getValue(self::SEARCH_REMOVE_OPERATOR))
      ->set(self::FACET_TRUNCATE, $form_state->getValue(self::FACET_TRUNCATE))
      ->set(self::EDISMAX_SEARCH_FLAG, $form_state->getValue(self::EDISMAX_SEARCH_FLAG))
      ->set(self::EDISMAX_SEARCH_LABEL, $form_state->getValue(self::EDISMAX_SEARCH_LABEL))
      ->set(self::SEARCH_ALL_FIELDS_FLAG, $form_state->getValue(self::SEARCH_ALL_FIELDS_FLAG))
      ->set(self::DISPLAY_LIST_FLAG, $form_state->getValue(self::DISPLAY_LIST_FLAG))
      ->set(self::DISPLAY_GRID_FLAG, $form_state->getValue(self::DISPLAY_GRID_FLAG))
      ->set(self::DISPLAY_DEFAULT, $form_state->getValue(self::DISPLAY_DEFAULT))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Callback for ajax_example_autotextfields.
   *
   * Selects the piece of the form we want to use as replacement markup and
   * returns it as a form (renderable array).
   */
  public function LuceneSearchEnableDisableCallback($form, FormStateInterface $form_state) {
    return $form['edismax']['textfields_container'];
  }
}
