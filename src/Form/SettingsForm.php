<?php

namespace Drupal\advanced_search\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\advanced_search\AdvancedSearchQuery;
use Drupal\advanced_search\GetConfigTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Config form for Islandora Advanced Search settings.
 */
class SettingsForm extends ConfigFormBase {

  use GetConfigTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  const CONFIG_NAME = 'advanced_search.settings';

  const SEARCH_QUERY_PARAMETER = 'search_query_parameter';

  const SEARCH_RECURSIVE_PARAMETER = 'search_recursive_parameter';

  const SEARCH_ADD_OPERATOR = 'search_add_operator';

  const SEARCH_REMOVE_OPERATOR = 'search_remove_operator';

  const FACET_TRUNCATE = 'facet_truncate';

  const EDISMAX_SEARCH_FLAG = 'lucene_on_off';

  const EDISMAX_SEARCH_LABEL = 'lucene_label';

  const SEARCH_ALL_FIELDS_FLAG = 'all_fields_on_off';

  const RECURSIVE_FLAG = 'recursive';

  const DISPLAY_LIST_FLAG = 'list_on_off';

  const DISPLAY_GRID_FLAG = 'grid_on_off';

  const DISPLAY_DEFAULT = 'default-display-mode';

  const QUERY_FIELDS = 'query_fields';

  const NO_FOLLOW = 'no_follow';

  /**
   * {@inheritdoc}
   */
  final public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
  ) {
    $this->setConfigFactory($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
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
    return [self::CONFIG_NAME];
  }

  /**
   * Get available fields from all Search API indexes.
   *
   * @return array
   *   Field ID => label.
   */
  protected function getAvailableFields(): array {
    $options = [];
    $indexes = $this->entityTypeManager
      ->getStorage('search_api_index')
      ->loadMultiple();

    foreach ($indexes as $index) {
      foreach ($index->getFields() as $id => $field) {
        $options[$id] = $field->getLabel() . " ($id)";
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* -------------------------
     * Advanced Search (eDisMax)
     * ------------------------- */
    $form['eDisMax'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced Search Block'),
      '#weight' => -1,
    ];

    $form['eDisMax']['description'] = [
      '#markup' => $this->t('Advanced Search Blocks are available in the Blocks interface for each Search API view. These settings apply globally.'),
    ];

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

    $form['eDisMax']['container'] = [
      '#type' => 'container',
    ];

    $form['eDisMax']['textfields_container'][self::SEARCH_ALL_FIELDS_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Enable searching all fields'),
      '#description' => $this->t('<ul>
          <li>This makes an additional option visible in all Advanced Search Blocks, which searches across all fields. Its label is configured below.</li>
          <li>This setting must be enabled for the "Simple Search Block" to function.</li>
        </ul>'),
      '#default_value' => self::getConfig(self::SEARCH_ALL_FIELDS_FLAG, 0),
    ];

    $form['eDisMax']['textfields_container'][self::RECURSIVE_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Search collections recursively by default.'),
      '#description' => $this->t('<ul> <li>Select whether subcollections are searched by default.</li> <li>The user can override this setting.</li> </ul>'),
      '#default_value' => self::getConfig(self::RECURSIVE_FLAG, 0),
    ];

    $form['eDisMax']['container'][self::EDISMAX_SEARCH_LABEL] = [
      '#type' => 'textfield',
      '#title' => $this->t('All fields label'),
      '#default_value' => self::getConfig(self::EDISMAX_SEARCH_LABEL, 'Keyword'),
    ];

    /* -------------------------
     * Query Fields
     * ------------------------- */
    $form['query_fields_block'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Query Fields'),
    ];

    $base_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
    $form['query_fields_block']['description'] = [
      '#markup' => $this->t(
        'Select fields to query when using the all-fields option. If none are selected, <a href="@url" target="_blank">all fields will be searched</a>.',
        ['@url' => $base_url . '/admin/config/search/search-api']
      ),
    ];

    $form['query_fields_block'][self::QUERY_FIELDS] = [
      '#type' => 'checkboxes',
      '#options' => $this->getAvailableFields(),
      '#default_value' => self::getConfig(self::QUERY_FIELDS, []),
    ];

    /* -------------------------
     * Pager Block
     * ------------------------- */
    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Pager Block'),
    ];

    $form['display'][self::DISPLAY_LIST_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose list view'),
      '#default_value' => self::getConfig(self::DISPLAY_LIST_FLAG, 0),
    ];

    $form['display'][self::DISPLAY_GRID_FLAG] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose grid view'),
      '#default_value' => self::getConfig(self::DISPLAY_GRID_FLAG, 0),
    ];

    $form['display'][self::DISPLAY_DEFAULT] = [
      '#type' => 'select',
      '#title' => $this->t('Default display mode'),
      '#options' => ['list' => $this->t('List'), 'grid' => $this->t('Grid')],
      '#default_value' => self::getConfig(self::DISPLAY_DEFAULT, 'grid'),
    ];

    /* -------------------------
     * Advanced Search Params
     * ------------------------- */
    $form['search'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced Search'),
    ];

    $form['search'][self::SEARCH_QUERY_PARAMETER] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Query Parameter'),
      '#default_value' => AdvancedSearchQuery::getQueryParameter(),
    ];

    $form['search'][self::SEARCH_RECURSIVE_PARAMETER] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recursive Query Parameter'),
      '#default_value' => AdvancedSearchQuery::getRecurseParameter(),
    ];

    $form['search'][self::SEARCH_ADD_OPERATOR] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facet Add Operator'),
      '#default_value' => AdvancedSearchForm::getAddOperator(),
    ];

    $form['search'][self::SEARCH_REMOVE_OPERATOR] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facet Remove Operator'),
      '#default_value' => AdvancedSearchForm::getRemoveOperator(),
    ];

    /* -------------------------
     * Facets
     * ------------------------- */
    $form['facets'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Facets'),
    ];

    $form['facets'][self::FACET_TRUNCATE] = [
      '#type' => 'number',
      '#title' => $this->t('Truncate facet labels'),
      '#default_value' => self::getConfig(self::FACET_TRUNCATE, 32),
      '#min' => 1,
    ];

    /* -------------------------
     * No follow option
     * ------------------------- */
    $form['no-follow'] = [
      '#type' => 'fieldset',
      '#title' => $this->t("No Follow Block"),
    ];
    $form['no-follow'][self::NO_FOLLOW] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add rel="nofollow" attribute in links'),
      '#default_value' => self::getConfig(self::NO_FOLLOW, 0),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query_fields = array_filter((array) $form_state->getValue(self::QUERY_FIELDS));

    $this->configFactory->getEditable(self::CONFIG_NAME)
      ->set(self::SEARCH_QUERY_PARAMETER, $form_state->getValue(self::SEARCH_QUERY_PARAMETER))
      ->set(self::SEARCH_RECURSIVE_PARAMETER, $form_state->getValue(self::SEARCH_RECURSIVE_PARAMETER))
      ->set(self::SEARCH_ADD_OPERATOR, $form_state->getValue(self::SEARCH_ADD_OPERATOR))
      ->set(self::SEARCH_REMOVE_OPERATOR, $form_state->getValue(self::SEARCH_REMOVE_OPERATOR))
      ->set(self::FACET_TRUNCATE, $form_state->getValue(self::FACET_TRUNCATE))
      ->set(self::EDISMAX_SEARCH_FLAG, $form_state->getValue(self::EDISMAX_SEARCH_FLAG))
      ->set(self::EDISMAX_SEARCH_LABEL, $form_state->getValue(self::EDISMAX_SEARCH_LABEL))
      ->set(self::SEARCH_ALL_FIELDS_FLAG, $form_state->getValue(self::SEARCH_ALL_FIELDS_FLAG))
      ->set(self::RECURSIVE_FLAG, $form_state->getValue(self::RECURSIVE_FLAG))
      ->set(self::DISPLAY_LIST_FLAG, $form_state->getValue(self::DISPLAY_LIST_FLAG))
      ->set(self::DISPLAY_GRID_FLAG, $form_state->getValue(self::DISPLAY_GRID_FLAG))
      ->set(self::DISPLAY_DEFAULT, $form_state->getValue(self::DISPLAY_DEFAULT))
      ->set(self::QUERY_FIELDS, $query_fields)
      ->set(self::NO_FOLLOW, $form_state->getValue(self::NO_FOLLOW))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
