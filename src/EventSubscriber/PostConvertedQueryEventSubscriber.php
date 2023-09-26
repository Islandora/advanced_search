<?php

namespace Drupal\advanced_search\EventSubscriber;

use Drupal\advanced_search\AdvancedSearchQuery;
use Drupal\search_api_solr\Event\PostConvertedQueryEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to PostConvertedQueryEvents.
 *
 * @package Drupal\advanced_search\EventSubscriber
 */
class PostConvertedQueryEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SearchAPISolrEvents::POST_CONVERT_QUERY][] = ['alter'];

    return $events;

  }

  /**
   * Alter the query.
   */
  public function alter(PostConvertedQueryEvent $event) {
    $search_api_query = $event->getSearchApiQuery();
    $solarium_query = $event->getSolariumQuery();
    $advanced_search_query = new AdvancedSearchQuery();
    $advanced_search_query->alterQuery(\Drupal::request(), $solarium_query, $search_api_query);
  }

}
