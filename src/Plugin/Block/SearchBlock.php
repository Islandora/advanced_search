<?php

namespace Drupal\advanced_search\Plugin\Block;

use Drupal\advanced_search\Form\SearchForm;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\advanced_search\Form\SettingsForm;

/**
 * Provides a 'SearchBlock' block.
 *
 * @Block(
 *  id = "search_block",
 *  admin_label = @Translation("Search"),
 * )
 */
class SearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = \Drupal::config(SettingsForm::CONFIG_NAME);
    $form['search-attributes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Configure Search Block'),
    ];

    if (!$config->get(SettingsForm::SEARCH_ALL_FIELDS_FLAG)) {
      $form['search-attributes'][SettingsForm::SEARCH_ALL_FIELDS_FLAG] = [
        '#markup' => $this
          ->t('<strong>This block is required to enable searching all fields for the Advanced Search.
            To proceed, please enable "Enable searching all fields" in
            <a href="/admin/config/search/advanced" target="_blank">Advanced Seach Configuration</a></strong>.'),
      ];
    }
    else {
      $views = \Drupal::EntityTypeManager()->getStorage('view')->loadMultiple();
      $options = [];
      foreach ($views as $view_name => $view) {
        $displays = $view->get("display");
        foreach ($displays as $display) {
          if ($display['display_plugin'] === "page") {
            $options["view.$view_name" . "." . $display['id']] = "view.$view_name" . "." . $display['id'];
          }
        }
      }
      $form['search-attributes']['view_machine_name'] = [
        '#type' => 'select',
        '#title' => $this->t('Select the machine name of Search Results Page:'),
        '#default_value' => $this->configuration['search_view_machine_name'],
        '#options' => $options,
      ];
      $form['search-attributes']['search_textfield'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Search Keyword Text field label:'),
        '#default_value' => $this->configuration['search_textfield_label'],
        '#maxlength' => 255,
      ];
      $form['search-attributes']['search_placeholder_textfield'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Search Keyword text field placeholder:'),
        '#default_value' => $this->configuration['search_placeholder'],
        '#maxlength' => 255,
      ];

      $form['search-attributes']['search_submit'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Search Button Label:'),
        '#default_value' => $this->configuration['search_submit_label'],
        '#maxlength' => 255,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['block_id'] = $form_state->getBuildInfo()['callback_object']->getEntity()->id();
    $this->configuration['search_view_machine_name'] = $form_state->getValues()['search-attributes']['view_machine_name'];
    $this->configuration['search_textfield_label'] = $form_state->getValues()['search-attributes']['search_textfield'];
    $this->configuration['search_placeholder'] = $form_state->getValues()['search-attributes']['search_placeholder_textfield'];
    $this->configuration['search_submit_label'] = $form_state->getValues()['search-attributes']['search_submit'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $blockId = $config['block_id'];
    $searchForm = new SearchForm($blockId);
    return \Drupal::formBuilder()->getForm($searchForm);
  }

}
