<?php

namespace Drupal\advanced_search\Plugin\Block;

use Drupal\advanced_search\Form\SettingsForm;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

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
    return [
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['search-attributes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Configure Search Block'),
    ];
    $views = \Drupal::EntityTypeManager()->getStorage('view')->loadMultiple();
    $options = [];
    foreach ($views as $view_name => $view) {
        $displays = $view->get("display");
        foreach ($displays as $display) {
          if ($display['display_plugin'] === "page") {
            $options["view.$view_name". "." . $display['id']] = "view.$view_name". "." . $display['id'];
          }
        }
    }
    $form['search-attributes']['view_machine_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Search Results Page\'s Machine Name:'),
      '#default_value' => $this->configuration['search_view_machine_name'],
      '#options' => $options
    ];
    $form['search-attributes']['search_textfield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Keyword Textfield Label:'),
      '#default_value' => $this->configuration['search_textfield_label'],
      '#maxlength' => 255,
    ];
    $form['search-attributes']['search_placeholder_textfield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Keyword Textfield Placeholder:'),
      '#default_value' => $this->configuration['search_placeholder'],
      '#maxlength' => 255,
    ];

    $copyfield = \Drupal::config(SettingsForm::CONFIG_NAME)->get(SettingsForm::COPY_FIELD_FLAG);
    $dismax = \Drupal::config(SettingsForm::CONFIG_NAME)->get(SettingsForm::LUCENE_SEARCH_FLAG);

    if ($dismax && $copyfield) {
      $form['search-attributes']['method'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select method of search:'),
        '#default_value' => $this->configuration['search_method'],
        '#options' => [
          'dismax' => 'Dismax',
          'copyfield' => 'Copying Fields'
        ]
      ];
    }
    else {
      $method = 'none';
      if ($dismax) {
        $method = 'dismax';
      }
      else if ($copyfield) {
        $method = 'copyfield';
      }
      $form['search-attributes']['method'] = [
        '#type' => 'hidden',
        '#value' => $method
      ];
    }
    $form['search-attributes']['search_submit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Button Label:'),
      '#default_value' => $this->configuration['search_submit_label'],
      '#maxlength' => 255,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['search_view_machine_name'] = $form_state->getValues()['search-attributes']['view_machine_name'];
    $this->configuration['search_textfield_label'] = $form_state->getValues()['search-attributes']['search_textfield'];
    $this->configuration['search_placeholder'] = $form_state->getValues()['search-attributes']['search_placeholder_textfield'];
    $this->configuration['search_submit_label'] = $form_state->getValues()['search-attributes']['search_submit'];
    $this->configuration['search_method'] = $form_state->getValues()['search-attributes']['method'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\advanced_search\Form\SearchForm');
  }

}
