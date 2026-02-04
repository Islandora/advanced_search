<?php

namespace Drupal\advanced_search\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Plugin implementation of the 'searchable_entity_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "searchable_entity_formatter",
 *   label = @Translation("Searchable entity formatter"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class SearchableEntityFormatter extends EntityReferenceLabelFormatter {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'search_link' => 'search?f[0]',
      'search_var' => 'all_subjects',
      'search_term' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['search_link'] = [
      '#title' => $this->t('Search base path'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->getSetting('search_link'),
    ];
    $elements['search_var'] = [
      '#title' => $this->t('Search variable'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->getSetting('search_var'),
    ];
    $elements['search_term'] = [
      '#title' => $this->t('Use label as search term'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('search_term'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Search path: @search_link', ['@search_link' => $this->getSetting('search_link')]);
    $summary[] = $this->t('Variable: @search_var', ['@search_var' => $this->getSetting('search_var')]);
    $summary[] = $this->getSetting('search_term') ? $this->t('Use label as search term') : $this->t('Use ID as search term');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $par_elements = parent::viewElements($items, $langcode);
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $search_var = $this->getSetting('search_var');
      $search_term = $this->getSetting('search_term');

      if ($search_term == TRUE) {
        $param = $par_elements[$delta]['#title'];
      }
      else {
        $param = $entity->id();
      }

      $url = \Drupal::service('facets.utility.url_generator')->getUrl([$search_var => [$param]]);

      $par_elements[$delta]['#url'] = $url;

    }
    return $par_elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view label', NULL, TRUE);
  }

}
