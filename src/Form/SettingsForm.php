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
  const SOLR_CASE_INSENSITIVE_FIELD_PREFIX = "case_insensitive_solr_field_prefix";
  const LUCENE_SEARCH_FLAG = 'lucene_on_off';
  const COPY_FIELD_FLAG = 'copyfield_on_off';
  const LUCENE_SEARCH_LABEL = 'lucene_label';
  const COPY_FIELD_LABEL = 'copyfiled_label';
  const COPY_FIELD_NAME = 'copyfiled_name';

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
      'solr_case_insensitive_field_prefix' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Case Insensitive Search'),
        self::SOLR_CASE_INSENSITIVE_FIELD_PREFIX => [
          '#type' => 'textfield',
          '#title' => $this->t('Prefix for Solr Case Insensitive Field'),
          '#description' => $this->t('If you have configured case insenstive fields, please specify the prefix here (i.e sss_lowercase_)'),
          '#default_value' => self::getConfig(self::SOLR_CASE_INSENSITIVE_FIELD_PREFIX, ""),
        ],
      ],
    ];

    $form['lucene'] =  [
      '#type' => 'fieldset',
      '#title' => $this->t(" Solr's Standard Query parser (also known as 'lucene')"),
    ];

    $form['lucene'][self::LUCENE_SEARCH_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Enable Lucene Search.'),
      '#default_value' => self::getConfig(self::LUCENE_SEARCH_FLAG, 0),
      '#ajax' => [
        'callback' => '::LuceneSearchEnableDisableCallback',
        'wrapper' => 'lucene-container',
        'effect' => 'fade',
      ],
    ];

    $form['lucene']['textfields_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'lucene-container'],
    ];
    if (self::getConfig(self::LUCENE_SEARCH_FLAG, "All") === 1
       || $form_state->getValue(self::LUCENE_SEARCH_FLAG) === 1) {
      $form['lucene']['textfields_container'][self::LUCENE_SEARCH_LABEL] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#description' => $this->t('This label will be appear in Search Terms dropdown of Advanced Search form block if Lucene Search is enabled.'),
        '#default_value' => self::getConfig(self::LUCENE_SEARCH_LABEL, "All"),
      ];
    }



    $form['copyfield'] =  [
      '#type' => 'fieldset',
      '#title' => $this->t("Use Copy field"),
    ];

    $form['copyfield'][self::COPY_FIELD_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Enable Copy Field.'),
      '#default_value' => self::getConfig(self::COPY_FIELD_FLAG, 0),
      '#ajax' => [
        'callback' => '::copyFieldEnableDisableCallback',
        'wrapper' => 'copyfield-container',
        'effect' => 'fade',
      ],
    ];

    $form['copyfield']['textfields_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'copyfield-container'],
    ];
    if (self::getConfig(self::COPY_FIELD_FLAG, "Keyword") === 1
      || $form_state->getValue(self::COPY_FIELD_FLAG) === 1) {
      $form['copyfield']['textfields_container'][self::COPY_FIELD_LABEL] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#description' => $this->t('This label will be appear in Search Terms dropdown of Advanced Search form block if Lucene Search is enabled.'),
        '#default_value' => self::getConfig(self::COPY_FIELD_LABEL, "Keyword"),
      ];
      $form['copyfield']['textfields_container'][self::COPY_FIELD_NAME] = [
        '#type' => 'textfield',
        '#title' => $this->t('Copyfield Name in Solr'),
        '#default_value' => self::getConfig(self::COPY_FIELD_NAME, "default"),
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
      ->set(self::SOLR_CASE_INSENSITIVE_FIELD_PREFIX, $form_state->getValue(self::SOLR_CASE_INSENSITIVE_FIELD_PREFIX))
      ->set(self::LUCENE_SEARCH_FLAG, $form_state->getValue(self::LUCENE_SEARCH_FLAG))
      ->set(self::LUCENE_SEARCH_LABEL, $form_state->getValue(self::LUCENE_SEARCH_LABEL))

      ->set(self::COPY_FIELD_FLAG, $form_state->getValue(self::COPY_FIELD_FLAG))
      ->set(self::COPY_FIELD_LABEL, $form_state->getValue(self::COPY_FIELD_LABEL))
      ->set(self::COPY_FIELD_NAME, $form_state->getValue(self::COPY_FIELD_NAME))
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
    return $form['lucene']['textfields_container'];
  }
  /**
   * Callback for ajax_example_autotextfields.
   *
   * Selects the piece of the form we want to use as replacement markup and
   * returns it as a form (renderable array).
   */
  public function copyFieldEnableDisableCallback($form, FormStateInterface $form_state) {
    return $form['copyfield']['textfields_container'];
  }
}
