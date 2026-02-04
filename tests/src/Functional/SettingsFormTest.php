<?php

namespace Drupal\Tests\advanced_search\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Advanced Search settings form.
 *
 * @group advanced_search
 */
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'advanced_search',
    'block',
    'views',
    'search_api',
    'search_api_solr',
    'facets',
    'facets_summary',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
    ]);
  }

  /**
   * Tests the settings form access.
   */
  public function testSettingsFormAccess(): void {
    // Anonymous user should not have access.
    $this->drupalGet('admin/config/search/advanced');
    $this->assertSession()->statusCodeEquals(403);

    // Admin user should have access.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/search/advanced');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Advanced Search Block');
  }

  /**
   * Tests the settings form fields are present.
   */
  public function testSettingsFormFields(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/search/advanced');

    // Check eDisMax settings.
    $this->assertSession()->fieldExists('lucene_on_off');
    $this->assertSession()->fieldExists('all_fields_on_off');
    $this->assertSession()->fieldExists('recursive');
    $this->assertSession()->fieldExists('lucene_label');

    // Check display settings.
    $this->assertSession()->fieldExists('list_on_off');
    $this->assertSession()->fieldExists('grid_on_off');
    $this->assertSession()->fieldExists('default-display-mode');

    // Check search parameters.
    $this->assertSession()->fieldExists('search_query_parameter');
    $this->assertSession()->fieldExists('search_recursive_parameter');
    $this->assertSession()->fieldExists('search_add_operator');
    $this->assertSession()->fieldExists('search_remove_operator');

    // Check facets settings.
    $this->assertSession()->fieldExists('facet_truncate');
  }

  /**
   * Tests the settings form submission.
   */
  public function testSettingsFormSubmission(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/search/advanced');

    $edit = [
      'lucene_on_off' => TRUE,
      'all_fields_on_off' => TRUE,
      'recursive' => TRUE,
      'lucene_label' => 'All Fields',
      'list_on_off' => TRUE,
      'grid_on_off' => TRUE,
      'default-display-mode' => 'list',
      'search_query_parameter' => 'q',
      'search_recursive_parameter' => 'recurse',
      'search_add_operator' => '+',
      'search_remove_operator' => '-',
      'facet_truncate' => 50,
    ];

    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the config was saved.
    $config = $this->config('advanced_search.settings');
    $this->assertEquals(TRUE, $config->get('lucene_on_off'));
    $this->assertEquals(TRUE, $config->get('all_fields_on_off'));
    $this->assertEquals(TRUE, $config->get('recursive'));
    $this->assertEquals('All Fields', $config->get('lucene_label'));
    $this->assertEquals('list', $config->get('default-display-mode'));
    $this->assertEquals('q', $config->get('search_query_parameter'));
    $this->assertEquals(50, $config->get('facet_truncate'));
  }

  /**
   * Tests default display mode options are translated.
   */
  public function testDisplayModeOptionsTranslation(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/search/advanced');

    // Check that the select options are present.
    $this->assertSession()->optionExists('default-display-mode', 'list');
    $this->assertSession()->optionExists('default-display-mode', 'grid');
  }

}
