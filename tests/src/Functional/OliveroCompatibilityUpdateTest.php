<?php

namespace Drupal\Tests\advanced_search\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Olivero compatibility post-update path.
 *
 * @group advanced_search
 */
final class OliveroCompatibilityUpdateTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['advanced_search'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that compatibility is enabled for an Olivero default theme.
   */
  public function testCompatibilityEnabledForOliveroDefault(): void {
    $this->installDefaultTheme('olivero');
    $this->assertFalse($this->compatibilityIsEnabled());

    $this->assertSame(
      'Advanced Search Olivero compatibility has been enabled.',
      $this->runPostUpdate(),
    );
    $this->assertTrue($this->compatibilityIsEnabled());

    // A subsequent invocation has no work to do.
    $this->assertNull($this->runPostUpdate());
  }

  /**
   * Tests that compatibility is enabled for an Olivero subtheme.
   */
  public function testCompatibilityEnabledForOliveroSubtheme(): void {
    $this->installDefaultTheme('advanced_search_olivero_test');
    $this->assertFalse($this->compatibilityIsEnabled());

    $this->assertSame(
      'Advanced Search Olivero compatibility has been enabled.',
      $this->runPostUpdate(),
    );
    $this->assertTrue($this->compatibilityIsEnabled());
  }

  /**
   * Tests compatibility for an installed but inactive Olivero.
   */
  public function testCompatibilityEnabledWhenOliveroIsNotDefault(): void {
    $this->container->get('theme_installer')->install(['olivero']);
    $this->assertSame('stark', $this->config('system.theme')->get('default'));

    $this->assertSame(
      'Advanced Search Olivero compatibility has been enabled.',
      $this->runPostUpdate(),
    );
    $this->assertTrue($this->compatibilityIsEnabled());
  }

  /**
   * Tests that sites without Olivero are left unchanged.
   */
  public function testCompatibilitySkippedWithoutOlivero(): void {
    $themes = $this->config('core.extension')->get('theme') ?? [];
    $this->assertArrayNotHasKey('olivero', $themes);

    $this->assertNull($this->runPostUpdate());
    $this->assertFalse($this->compatibilityIsEnabled());
  }

  /**
   * Tests that an already-enabled compatibility module is left unchanged.
   */
  public function testCompatibilitySkippedWhenAlreadyEnabled(): void {
    $this->installDefaultTheme('olivero');
    \Drupal::service('module_installer')->install([
      'advanced_search_olivero',
    ]);
    $this->assertTrue($this->compatibilityIsEnabled());

    $this->assertNull($this->runPostUpdate());
    $this->assertTrue($this->compatibilityIsEnabled());
  }

  /**
   * Installs a theme and makes it the site's default.
   */
  private function installDefaultTheme(string $theme): void {
    $this->container->get('theme_installer')->install([$theme]);
    $this->config('system.theme')->set('default', $theme)->save();
  }

  /**
   * Determines whether the compatibility module is enabled.
   */
  private function compatibilityIsEnabled(): bool {
    return \Drupal::moduleHandler()->moduleExists('advanced_search_olivero');
  }

  /**
   * Invokes the compatibility post-update hook.
   */
  private function runPostUpdate(): ?string {
    require_once $this->container->get('extension.list.module')
      ->getPath('advanced_search') . '/advanced_search.post_update.php';
    $sandbox = [];
    $result = advanced_search_post_update_enable_olivero_compatibility(
      $sandbox,
    );
    return $result === NULL ? NULL : (string) $result;
  }

}
