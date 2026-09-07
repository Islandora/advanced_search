<?php

namespace Drupal\Tests\advanced_search\Functional;

use Drupal\advanced_search\Form\AdvancedSearchForm;
use Drupal\advanced_search\SearchResultsToolbarBuilder;
use Drupal\Core\Form\FormState;
use Drupal\search_api\Item\FieldInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Entity\View;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the View-owned Advanced Search integration.
 *
 * @group advanced_search
 */
final class ViewIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'advanced_search',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that both View area plugins are discoverable.
   */
  public function testViewAreaPluginsAreDiscoverable(): void {
    $definitions = $this->container->get('plugin.manager.views.area')
      ->getDefinitions();
    $this->assertArrayHasKey('advanced_search_form', $definitions);
    $this->assertArrayHasKey('advanced_search_results_toolbar', $definitions);

    $views_data = $this->container->get('views.views_data')->get('views');
    $this->assertSame(
      'advanced_search_form',
      $views_data['advanced_search_form']['area']['id'],
    );
    $this->assertSame(
      'advanced_search_results_toolbar',
      $views_data['advanced_search_results_toolbar']['area']['id'],
    );
  }

  /**
   * Tests that client-side navigation is explicitly scoped to one toolbar.
   */
  public function testAjaxNavigationIsInstanceScoped(): void {
    $module_path = $this->container->get('extension.list.module')
      ->getPath('advanced_search');
    $ajax_javascript = file_get_contents(
      DRUPAL_ROOT . '/' . $module_path . '/js/facets/facets-views-ajax.js',
    );
    $form_javascript = file_get_contents(
      DRUPAL_ROOT . '/' . $module_path . '/js/advanced_search.form.js',
    );

    $this->assertIsString($ajax_javascript);
    $this->assertIsString($form_javascript);
    $this->assertStringContainsString(
      'Drupal.advancedSearchNavigate',
      $ajax_javascript,
    );
    $this->assertStringContainsString(
      'advancedSearchAjaxReady',
      $ajax_javascript,
    );
    $this->assertStringContainsString('findOwnedToolbar', $form_javascript);
    $this->assertStringNotContainsString(
      'history.pushState =',
      $ajax_javascript,
    );
    $this->assertStringNotContainsString(
      'historyInitiated',
      $ajax_javascript . $form_javascript,
    );
    $this->assertStringNotContainsString('toolbar_id', $form_javascript);
  }

  /**
   * Tests semantic structure supplied by the base search form.
   */
  public function testAdvancedSearchFormSemantics(): void {
    $field = $this->createMock(FieldInterface::class);
    $field->method('getFieldIdentifier')->willReturn('title');
    $field->method('getLabel')->willReturn('Title');

    $view = View::create([
      'id' => 'advanced_search_test',
      'label' => 'Advanced Search test',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [],
        ],
      ],
    ]);

    $form = $this->container->get('form_builder')->getForm(
      AdvancedSearchForm::class,
      $view,
      $view->getDisplay('default'),
      ['title' => $field],
      NULL,
      'view-instance-a',
    );
    $condition = $form['ajax']['terms'][0];

    $this->assertContains(
      'advanced-search-form',
      $form['#attributes']['class'],
    );
    $this->assertSame('Advanced search', (string) $form['#attributes']['aria-label']);
    $this->assertSame('group', $condition['#attributes']['role']);
    $this->assertSame(
      'Search condition 1',
      (string) $condition['#attributes']['aria-label'],
    );
    $this->assertContains(
      'advanced-search-form__field',
      $condition['search']['#attributes']['class'],
    );
    $this->assertSame(
      'Add another search condition',
      (string) $condition['actions']['add']['#attributes']['aria-label'],
    );
    $this->assertSame(
      'advanced-search-form-view-instance-a',
      $form['#id'],
    );
    $this->assertArrayHasKey(
      $form['#id'],
      $form['#attached']['drupalSettings']['advanced_search_form'],
    );
    $this->assertSame(
      'advanced-search-form-view-instance-a-advanced-search-ajax',
      $form['ajax']['#attributes']['id'],
    );

    $second_form = $this->container->get('form_builder')->getForm(
      AdvancedSearchForm::class,
      $view,
      $view->getDisplay('default'),
      ['title' => $field],
      NULL,
      'view-instance-b',
    );
    $this->assertNotSame($form['#id'], $second_form['#id']);
    $this->assertNotSame(
      $form['ajax']['#attributes']['id'],
      $second_form['ajax']['#attributes']['id'],
    );
  }

  /**
   * Tests accessible sort labels and relevance handling.
   */
  public function testToolbarSortOptions(): void {
    $relevance = new \stdClass();
    $relevance->options = [
      'exposed' => TRUE,
      'id' => 'search_api_relevance',
      'expose' => ['label' => 'Relevance'],
    ];
    $title = new \stdClass();
    $title->options = [
      'exposed' => TRUE,
      'id' => 'title',
      'expose' => ['label' => 'Title'],
    ];

    $method = new \ReflectionMethod(
      SearchResultsToolbarBuilder::class,
      'buildSortByForm',
    );
    $method->setAccessible(TRUE);
    $sort = $method->invoke(
      $this->container->get('advanced_search.search_results_toolbar_builder'),
      [$relevance, $title],
      [
        'sort_by' => 'search_api_relevance',
        'sort_order' => 'ASC',
      ],
    );

    $this->assertArrayNotHasKey(
      'search_api_relevance_asc',
      $sort['#options'],
    );
    $this->assertSame(
      'search_api_relevance_desc',
      $sort['#value'],
    );
    $this->assertSame(
      'Sort by Relevance',
      (string) $sort['#options']['search_api_relevance_desc'],
    );
    $this->assertSame(
      'Sort by Title (ascending)',
      (string) $sort['#options']['title_asc'],
    );
    $this->assertSame(
      'Sort by Title (descending)',
      (string) $sort['#options']['title_desc'],
    );
  }

  /**
   * Tests a saved toolbar area and its header-only placement contract.
   */
  public function testConfiguredToolbarAreaIsHeaderOnly(): void {
    $view = View::create([
      'id' => 'advanced_search_toolbar_test',
      'label' => 'Advanced Search toolbar test',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [
            'pager' => [
              'type' => 'full',
              'options' => [
                'items_per_page' => 15,
                'expose' => [
                  'items_per_page' => TRUE,
                  'items_per_page_options' => '15,60,120',
                ],
              ],
            ],
            'header' => [
              'advanced_search_results_toolbar' => [
                'id' => 'advanced_search_results_toolbar',
                'table' => 'views',
                'field' => 'advanced_search_results_toolbar',
                'relationship' => 'none',
                'group_type' => 'group',
                'admin_label' => 'Search results toolbar',
                'plugin_id' => 'advanced_search_results_toolbar',
                'empty' => TRUE,
                'override_list_on_off' => TRUE,
                'override_grid_on_off' => TRUE,
                'override-default-display-mode' => 'list',
              ],
            ],
          ],
        ],
      ],
    ]);
    $view->save();

    $executable = $view->getExecutable();
    $executable->setDisplay('default');
    $executable->execute();
    $handlers = $executable->display_handler->getHandlers('header');
    $toolbar = $handlers['advanced_search_results_toolbar'];

    $this->assertSame('header', $toolbar->areaType);
    $this->assertSame([], $toolbar->validate());
    $this->assertSame('container', $toolbar->render()['#type']);

    $builder = $this->container->get(
      'advanced_search.search_results_toolbar_builder',
    );
    $executable->dom_id = 'view-instance-a';
    $first_build = $builder->build($executable, [], TRUE);
    $first_id = $first_build['#attributes']['data-drupal-pager-id'];
    $this->assertArrayHasKey(
      $first_id,
      $first_build['#attached']['drupalSettings']['advanced_search_pager_views_ajax'],
    );
    $this->assertSame(
      $first_id,
      $builder->build($executable, [], TRUE)['#attributes']['data-drupal-pager-id'],
    );

    $executable->dom_id = 'view-instance-b';
    $second_id = $builder->build(
      $executable,
      [],
      TRUE,
    )['#attributes']['data-drupal-pager-id'];
    $this->assertNotSame($first_id, $second_id);

    $toolbar->areaType = 'footer';
    $this->assertNotEmpty($toolbar->validate());
    $this->assertSame([], $toolbar->render());
  }

  /**
   * Tests that the query service honors administrator-defined parameter names.
   */
  public function testConfiguredQueryService(): void {
    $this->config('advanced_search.settings')
      ->set('search_query_parameter', 'criteria')
      ->set('search_recursive_parameter', 'descendants')
      ->save();

    /** @var \Drupal\advanced_search\AdvancedSearchQuery $query */
    $query = $this->container->get('advanced_search.query');
    $request = Request::create('/search', 'GET', [
      'criteria' => [
        [
          'f' => 'title',
          'v' => 'Islandora',
        ],
      ],
      'descendants' => '1',
    ]);

    $this->assertSame('criteria', $query->getQueryParameterName());
    $this->assertSame('descendants', $query->getRecurseParameterName());
    $this->assertCount(1, $query->getTerms($request));
    $this->assertTrue($query->shouldRecurse($request));
  }

  /**
   * Tests recursive state from the URL and during add/remove AJAX rebuilds.
   */
  public function testRecursiveFormStateIsPreserved(): void {
    /** @var \Drupal\advanced_search\AdvancedSearchQuery $query */
    $query = $this->container->get('advanced_search.query');
    $form = new AdvancedSearchForm(
      Request::create('/search?r=1'),
      $this->container->get('current_route_match'),
      $query,
      $this->container->get('plugin.manager.views.display'),
    );
    $method = new \ReflectionMethod($form, 'processInput');
    $method->setAccessible(TRUE);
    $defaults = [
      'conjunction' => AdvancedSearchForm::AND_OP,
      'search' => 'title',
      'include' => AdvancedSearchForm::IS_OP,
      'value' => NULL,
    ];

    $initial_state = new FormState();
    [$recursive] = $method->invoke($form, $initial_state, $defaults);
    $this->assertTrue($recursive);

    $submitted_term = $defaults;
    $submitted_term['value'] = 'Islandora';
    $add_state = new FormState();
    $add_state->setUserInput([
      'recursive' => '1',
      'terms' => [$submitted_term],
    ]);
    $add_state->setTriggeringElement([
      '#term_index' => 0,
      '#value' => AdvancedSearchForm::DEFAULT_ADD_OP,
    ]);
    [$recursive, $terms] = $method->invoke($form, $add_state, $defaults);
    $this->assertTrue($recursive);
    $this->assertCount(2, $terms);
    $this->assertTrue($add_state->getUserInput()['recursive']);

    $remove_state = new FormState();
    $remove_state->setUserInput([
      'recursive' => '1',
      'terms' => $terms,
    ]);
    $remove_state->setTriggeringElement([
      '#term_index' => 1,
      '#value' => AdvancedSearchForm::DEFAULT_REMOVE_OP,
    ]);
    [$recursive, $terms] = $method->invoke($form, $remove_state, $defaults);
    $this->assertTrue($recursive);
    $this->assertCount(1, $terms);

    $unchecked_state = new FormState();
    $unchecked_state->setUserInput(['terms' => [$submitted_term]]);
    $unchecked_state->setTriggeringElement([
      '#term_index' => 0,
      '#value' => AdvancedSearchForm::DEFAULT_ADD_OP,
    ]);
    [$recursive] = $method->invoke($form, $unchecked_state, $defaults);
    $this->assertFalse($recursive);
  }

}
