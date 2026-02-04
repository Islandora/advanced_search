<?php

namespace Drupal\Tests\advanced_search\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Search Block functionality.
 *
 * @group advanced_search
 */
class SearchBlockTest extends BrowserTestBase {

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
   * Tests that the Search Block plugin exists and can be accessed.
   */
  public function testSearchBlockExists(): void {
    $this->drupalLogin($this->adminUser);

    // Check that the block type exists in the block library.
    $this->drupalGet('admin/structure/block/library/stark');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Search');
  }

  /**
   * Tests Search Block configuration form access.
   */
  public function testSearchBlockConfigurationAccess(): void {
    $this->drupalLogin($this->adminUser);

    // Access the block configuration form.
    $this->drupalGet('admin/structure/block/add/search_block/stark');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests Search Block shows warning when all fields search is disabled.
   */
  public function testSearchBlockWarningWhenDisabled(): void {
    $this->drupalLogin($this->adminUser);

    // Ensure the setting is disabled.
    $this->config('advanced_search.settings')
      ->set('all_fields_on_off', FALSE)
      ->save();

    // Access the block configuration form.
    $this->drupalGet('admin/structure/block/add/search_block/stark');
    $this->assertSession()->statusCodeEquals(200);

    // Should show the warning message.
    $this->assertSession()->pageTextContains('This block is required to enable searching all fields');
  }

  /**
   * Tests Search Block shows configuration when all fields search is enabled.
   */
  public function testSearchBlockConfigurationWhenEnabled(): void {
    $this->drupalLogin($this->adminUser);

    // Enable the setting.
    $this->config('advanced_search.settings')
      ->set('all_fields_on_off', TRUE)
      ->set('lucene_on_off', TRUE)
      ->save();

    // Access the block configuration form.
    $this->drupalGet('admin/structure/block/add/search_block/stark');
    $this->assertSession()->statusCodeEquals(200);

    // Should show the configuration fields.
    $this->assertSession()->fieldExists('settings[search-attributes][view_machine_name]');
    $this->assertSession()->fieldExists('settings[search-attributes][search_textfield]');
    $this->assertSession()->fieldExists('settings[search-attributes][search_placeholder_textfield]');
    $this->assertSession()->fieldExists('settings[search-attributes][search_submit]');
  }

  /**
   * Tests that views are loaded into the select options.
   */
  public function testSearchBlockLoadsViews(): void {
    $this->drupalLogin($this->adminUser);

    // Enable the setting.
    $this->config('advanced_search.settings')
      ->set('all_fields_on_off', TRUE)
      ->set('lucene_on_off', TRUE)
      ->save();

    // Access the block configuration form.
    $this->drupalGet('admin/structure/block/add/search_block/stark');

    // The view machine name select should exist.
    $this->assertSession()->fieldExists('settings[search-attributes][view_machine_name]');
  }

}
