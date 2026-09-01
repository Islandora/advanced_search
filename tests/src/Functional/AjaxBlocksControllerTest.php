<?php

namespace Drupal\Tests\advanced_search\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\RoleInterface;

/**
 * Tests access checking for blocks rendered via AJAX.
 *
 * @group advanced_search
 */
class AjaxBlocksControllerTest extends BrowserTestBase {

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
   * Tests that inaccessible blocks are not returned to anonymous users.
   */
  public function testBlockAccessIsEnforced(): void {
    $public_block = $this->drupalPlaceBlock('system_powered_by_block', [
      'id' => 'public_block',
      'label' => 'Public block content',
      'label_display' => TRUE,
    ]);
    $restricted_block = $this->drupalPlaceBlock('system_powered_by_block', [
      'id' => 'restricted_block',
      'label' => 'Restricted block content',
      'label_display' => TRUE,
    ]);
    $restricted_block->setVisibilityConfig('user_role', [
      'id' => 'user_role',
      'roles' => [
        RoleInterface::AUTHENTICATED_ID => RoleInterface::AUTHENTICATED_ID,
      ],
      'negate' => FALSE,
      'context_mapping' => [
        'user' => '@user.current_user_context:current_user',
      ],
    ])->save();

    $response = $this->getHttpClient()->post($this->buildUrl(
      Url::fromRoute('advanced_search.ajax.blocks')
    ), [
      'form_params' => [
        'link' => '/',
        'blocks' => [
          $public_block->id() => '.public-block',
          $restricted_block->id() => '.restricted-block',
        ],
      ],
      'http_errors' => FALSE,
    ]);

    $this->assertSame(200, $response->getStatusCode());
    $body = (string) $response->getBody();
    $this->assertStringContainsString('Public block content', $body);
    $this->assertStringNotContainsString('Restricted block content', $body);
    $this->assertStringNotContainsString('.restricted-block', $body);
  }

}
