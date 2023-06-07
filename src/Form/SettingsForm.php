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
  final public function __construct(ConfigFactoryInterface $config_factory) {
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
    $form['eDisMax'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced Search Block'),
      '#weight' => -1,
    ];
    $form['eDisMax']['advanced-search-block-description'] = [
      '#markup' => $this->t("Advanced Search Blocks are available in the Blocks interface for each Search API view. When placing an Advanced Search Block, you can configure the fields that are used for field-based search and whether a “recursive” search is available.  The following settings apply to all Advanced Search blocks."),
      '#weight' => -2,
    ];

    $isEDismax = \Drupal::config(SettingsForm::CONFIG_NAME)->get(self::EDISMAX_SEARCH_FLAG);
    $form['eDisMax'][self::EDISMAX_SEARCH_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Enable Extended DisMax Query.'),
      '#description' => $this->t('<ul> <li>When enabled, all queries using an Advanced Search Block use the Extended Dismax (eDisMax) query processor.</li>
        <li>This setting must be enabled for the “Simple Search Block” to function. </li>
        <li>If enabled, the “Simple Search Block”/”Advanced Search Blocks” support:
           <ul> 
            <li>queries that include AND, OR, NOT, -, and + (user documentation needed)</li>
            <li>Wildcard operator *</li>
            <li>Words in query are treated as distinct words. They are combined using OR unless the user specifies using AND/NOT in their query.</li>
           </ul>
          </li>
        </ul>'),
      '#default_value' => $isEDismax ?? 1,
    ];

    $form['eDisMax']['textfields_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edismax-container'],
    ];

    $form['eDisMax']['textfields_container'][self::SEARCH_ALL_FIELDS_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Enable searching all fields'),
      '#description' => $this->t('<ul>
          <li>This makes an additional option visible in all Advanced Search Blocks, which searches across all fields. Its label is configured below.</li>
          <li>This setting must be enabled for the “Simple Search Block” to function.</li>
        </ul>'),
      '#default_value' => self::getConfig(self::SEARCH_ALL_FIELDS_FLAG, 0),
    ];
    $form['eDisMax']['textfields_container'][self::EDISMAX_SEARCH_LABEL] = [
      '#type' => 'textfield',
      '#title' => $this->t('If enabled, set the label for the option of searching all fields'),
      '#description' => $this->t('E.g. keyword.'),
      '#default_value' => self::getConfig(self::EDISMAX_SEARCH_LABEL, "Keyword"),
    ];

    $form['display-mode'] = [
      '#type' => 'fieldset',
      '#title' => $this->t("Pager Block"),
    ];
    $form['display-mode']['pager-block-description'] = [
      '#markup' => $this->t("Pager blocks are available in the Blocks interface for each Search API view.  The following settings apply for all Pager blocks."),
    ];

    $form['display-mode'][self::DISPLAY_LIST_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Expose "List view" option.'),
      '#default_value' => self::getConfig(self::DISPLAY_LIST_FLAG, 0),
    ];

    $form['display-mode'][self::DISPLAY_GRID_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Expose "Grid view" option.'),
      '#default_value' => self::getConfig(self::DISPLAY_GRID_FLAG, 0),
    ];

    $form['display-mode'][self::DISPLAY_DEFAULT] = [
      '#type' => 'select',
      '#title' => $this
        ->t('Default view mode:'),
      '#options' => [
        'list' => 'List',
        'grid' => 'Grid',
      ],
      '#default_value' => self::getConfig(self::DISPLAY_DEFAULT, 'grid'),
    ];

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

}
