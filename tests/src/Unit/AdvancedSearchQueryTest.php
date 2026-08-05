<?php

namespace Drupal\Tests\advanced_search\Unit;

use Drupal\advanced_search\AdvancedSearchQuery;
use Drupal\advanced_search\AdvancedSearchQueryTerm;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests defensive parsing of advanced-search query parameters.
 *
 * @group advanced_search
 */
final class AdvancedSearchQueryTest extends UnitTestCase {

  /**
   * Tests configured names and malformed nested query values.
   */
  public function testConfiguredParametersAndMalformedTerms(): void {
    $query = new AdvancedSearchQuery('criteria', 'descendants');
    $request = Request::create('/search', 'GET', [
      'criteria' => [
        ['f' => 'title', 'v' => 'A valid value'],
        'not-an-array',
        ['f' => ['title'], 'v' => 'Nested field'],
        ['f' => 'title', 'v' => ['Nested value']],
        ['f' => 'title', 'v' => 'Nested include', 'i' => ['1']],
        ['f' => 'title', 'v' => 'Unsupported OR NOT', 'c' => 'OR', 'i' => '0'],
        ['f' => '', 'v' => 'Missing field'],
        ['f' => 'title', 'v' => '0'],
      ],
      'descendants' => '1',
      // The legacy defaults must not be read by a configured query.
      'a' => [['f' => 'ignored', 'v' => 'Ignored']],
      'r' => '0',
    ]);

    $terms = $query->getTerms($request);

    $this->assertSame('criteria', $query->getQueryParameterName());
    $this->assertSame('descendants', $query->getRecurseParameterName());
    $this->assertTrue($query->shouldRecurse($request));
    $this->assertCount(2, $terms);
    $this->assertSame('A valid value', $terms[0]->toUserInput()['value']);
    $this->assertSame('0', $terms[1]->toUserInput()['value']);

    $malformed_recurse = Request::create('/search', 'GET', [
      'descendants' => ['1'],
    ]);
    $this->assertFalse($query->shouldRecurse($malformed_recurse));
  }

  /**
   * Tests that missing and malformed Solr mappings are safely rejected.
   */
  public function testSolrFieldMappingValidation(): void {
    $term = AdvancedSearchQueryTerm::fromQueryParams([
      'f' => 'title',
      'v' => 'Islandora',
    ]);
    $this->assertNotNull($term);

    $this->assertFalse($term->hasSolrFieldMapping([]));
    $this->assertFalse($term->hasSolrFieldMapping(['title' => 'not-an-array']));
    $this->assertFalse($term->hasSolrFieldMapping(['title' => [['nested']]]));
    $this->assertSame('', $term->toSolrFields([]));
    // Invalid mappings return before accessing Drupal's Solarium services.
    $this->assertSame('', $term->toSolrQuery([]));

    $mapping = ['title' => ['en' => 'tm_X3b_en_title', 'und' => '']];
    $this->assertTrue($term->hasSolrFieldMapping($mapping));
    $this->assertSame('tm_X3b_en_title', $term->toSolrFields($mapping));

    $all = AdvancedSearchQueryTerm::fromQueryParams([
      'f' => 'all',
      'v' => 'Islandora',
    ]);
    $this->assertNotNull($all);
    $this->assertTrue($all->hasSolrFieldMapping([]));
  }

}
