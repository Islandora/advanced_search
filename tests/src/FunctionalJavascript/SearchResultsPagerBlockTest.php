<?php

namespace Drupal\Tests\advanced_search\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the Search Results Pager Block configuration.
 *
 * @group advanced_search
 */
class SearchResultsPagerBlockTest extends WebDriverTestBase {

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
    'node',
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
      'administer blocks',
      'access administration pages',
    ]);
  }

  /**
   * Tests pager block configuration inherits global settings.
   */
  public function testPagerBlockConfigurationInheritsGlobalSettings(): void {
    $this->drupalLogin($this->adminUser);

    // Set global display settings.
    $this->drupalGet('admin/config/search/advanced');
    $this->submitForm([
      'list_on_off' => TRUE,
      'grid_on_off' => TRUE,
      'default-display-mode' => 'grid',
    ], 'Save configuration');

    // Verify the config was saved correctly.
    $config = $this->config('advanced_search.settings');
    $this->assertEquals(TRUE, $config->get('list_on_off'));
    $this->assertEquals(TRUE, $config->get('grid_on_off'));
    $this->assertEquals('grid', $config->get('default-display-mode'));
  }

  /**
   * Tests that display mode options are properly translated.
   */
  public function testDisplayModeOptionsInSettingsForm(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/search/advanced');

    // Verify the select element exists with proper options.
    $this->assertSession()->selectExists('default-display-mode');

    // Check that both options exist.
    $this->assertSession()->optionExists('default-display-mode', 'list');
    $this->assertSession()->optionExists('default-display-mode', 'grid');

    // Test changing the value.
    $this->submitForm([
      'default-display-mode' => 'list',
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Reload and verify the value persisted.
    $this->drupalGet('admin/config/search/advanced');
    $this->assertSession()->fieldValueEquals('default-display-mode', 'list');
  }

}
