<?php

namespace Drupal\advanced_search\EventSubscriber;

use Drupal\advanced_search\AdvancedSearchQuery;
use Drupal\search_api_solr\Event\PostConvertedQueryEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Subscribes to PostConvertedQueryEvents.
 *
 * @package Drupal\advanced_search\EventSubscriber
 */
class PostConvertedQueryEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a PostConvertedQueryEventSubscriber object.
   */
  public function __construct(
    protected AdvancedSearchQuery $advancedSearchQuery,
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[SearchAPISolrEvents::POST_CONVERT_QUERY][] = ['alter'];

    return $events;

  }

  /**
   * Alter the query.
   */
  public function alter(PostConvertedQueryEvent $event) {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return;
    }

    $search_api_query = $event->getSearchApiQuery();
    $solarium_query = $event->getSolariumQuery();

    // We must modify the query itself rather than the representation the
    // search_api presents as it is not possible to use the 'OR' operator
    // with it as it converts conditions into separate filter queries.
    // Additionally filter queries do not affect the score so are not
    // suitable for use in the advanced search queries.
    $this->advancedSearchQuery->alterQuery(
      $request,
      $solarium_query,
      $search_api_query,
    );
  }

}
