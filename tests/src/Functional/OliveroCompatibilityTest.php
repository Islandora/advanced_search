<?php

namespace Drupal\Tests\advanced_search\Functional;

use Drupal\Core\Form\FormState;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests conditional loading of the Olivero compatibility styles.
 *
 * @group advanced_search
 */
final class OliveroCompatibilityTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'advanced_search',
    'advanced_search_olivero',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that compatibility libraries do not attach for another theme.
   */
  public function testLibrariesDoNotAttachForAnotherTheme(): void {
    $this->assertNotContains(
      'advanced_search_olivero/form',
      $this->alterAdvancedSearchForm(),
    );
    $this->assertNotContains(
      'advanced_search_olivero/pager',
      $this->preprocessAdvancedSearchView(),
    );
  }

  /**
   * Tests narrowly scoped attachments when Olivero is active.
   */
  public function testLibrariesAttachForOlivero(): void {
    $this->activateTheme('olivero');

    $this->assertContains(
      'advanced_search_olivero/form',
      $this->alterAdvancedSearchForm(),
    );
    $this->assertContains(
      'advanced_search_olivero/pager',
      $this->preprocessAdvancedSearchView(),
    );

    // An unrelated View without Advanced Search behavior remains untouched.
    $this->assertNotContains(
      'advanced_search_olivero/pager',
      $this->preprocessView([]),
    );
  }

  /**
   * Tests that Olivero subthemes receive the compatibility libraries.
   */
  public function testLibrariesAttachForOliveroSubtheme(): void {
    $this->activateTheme('advanced_search_olivero_test');

    $this->assertContains(
      'advanced_search_olivero/form',
      $this->alterAdvancedSearchForm(),
    );
    $this->assertContains(
      'advanced_search_olivero/pager',
      $this->preprocessAdvancedSearchView(),
    );
  }

  /**
   * Makes a theme active in the test process.
   */
  private function activateTheme(string $theme): void {
    $this->container->get('theme_installer')->install([$theme]);
    $active_theme = $this->container->get('theme.initialization')
      ->getActiveThemeByName($theme);
    $this->container->get('theme.manager')->setActiveTheme($active_theme);
  }

  /**
   * Invokes the form-specific compatibility alter hook.
   *
   * @return string[]
   *   The libraries attached to the form.
   */
  private function alterAdvancedSearchForm(): array {
    $form = [];
    $form_state = new FormState();
    advanced_search_olivero_form_advanced_search_form_alter(
      $form,
      $form_state,
      'advanced_search_form',
    );
    return $form['#attached']['library'] ?? [];
  }

  /**
   * Invokes preprocessing for a View with Advanced Search behavior.
   *
   * @return string[]
   *   The libraries attached to the View.
   */
  private function preprocessAdvancedSearchView(): array {
    return $this->preprocessView([
      'advanced_search/advanced.search.facets_views_ajax',
    ]);
  }

  /**
   * Invokes compatibility preprocessing for a View.
   *
   * @param string[] $libraries
   *   Libraries already attached to the View.
   *
   * @return string[]
   *   Libraries attached after preprocessing.
   */
  private function preprocessView(array $libraries): array {
    $view = new \stdClass();
    $view->element = [
      '#attached' => [
        'library' => $libraries,
      ],
    ];
    $variables = ['view' => $view];
    advanced_search_olivero_preprocess_views_view($variables);
    return $view->element['#attached']['library'];
  }

}
