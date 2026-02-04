<?php

namespace Drupal\Tests\advanced_search\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the Search Block JavaScript functionality.
 *
 * @group advanced_search
 */
class SearchBlockTest extends WebDriverTestBase {

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
   * Tests that the Search Block can be configured.
   */
  public function testSearchBlockConfiguration(): void {
    $this->drupalLogin($this->adminUser);

    // First, enable the "search all fields" setting.
    $this->drupalGet('admin/config/search/advanced');
    $this->submitForm([
      'all_fields_on_off' => TRUE,
      'lucene_on_off' => TRUE,
    ], 'Save configuration');

    // Navigate to block placement.
    $this->drupalGet('admin/structure/block/add/search_block/stark');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the block configuration form loads.
    $this->assertSession()->pageTextContains('Configure Search Block');

    // The form should show the view selection field when all_fields is enabled.
    $this->assertSession()->fieldExists('settings[search-attributes][view_machine_name]');
    $this->assertSession()->fieldExists('settings[search-attributes][search_textfield]');
    $this->assertSession()->fieldExists('settings[search-attributes][search_placeholder_textfield]');
    $this->assertSession()->fieldExists('settings[search-attributes][search_submit]');
  }

  /**
   * Tests Search Block message when all fields search is disabled.
   */
  public function testSearchBlockDisabledMessage(): void {
    $this->drupalLogin($this->adminUser);

    // Ensure the "search all fields" setting is disabled.
    $this->drupalGet('admin/config/search/advanced');
    $this->submitForm([
      'all_fields_on_off' => FALSE,
    ], 'Save configuration');

    // Navigate to block placement.
    $this->drupalGet('admin/structure/block/add/search_block/stark');
    $this->assertSession()->statusCodeEquals(200);

    // Should show the warning message about enabling all fields search.
    $this->assertSession()->pageTextContains('This block is required to enable searching all fields');
    $this->assertSession()->pageTextContains('Advanced Seach Configuration');
  }

}
