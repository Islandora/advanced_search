<?php

namespace Drupal\islandora_advanced_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'SimpleSearchBlock' block.
 *
 * @Block(
 *  id = "simple_search_block",
 *  admin_label = @Translation("Search"),
 * )
 */
class SimpleSearchBlock extends BlockBase {

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
    $form['advanced_search_view_textfield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Advanced Search View Machine Name:'),
      '#default_value' => $this->configuration['search_view_machine_name'],
      '#maxlength' => 255,
      '#size' => 64,
      '#weight' => '1',
    ];
    $form['search_textfield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Keyword Textfield Label:'),
      '#default_value' => $this->configuration['search_textfield_label'],
      '#maxlength' => 255,
      '#size' => 64,
      '#weight' => '1',
    ];
    $form['search_placeholder_textfield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Keyword Textfield Placeholder:'),
      '#default_value' => $this->configuration['search_placeholder'],
      '#maxlength' => 255,
      '#size' => 64,
      '#weight' => '1',
    ];

    $form['search_submit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Button Label:'),
      '#default_value' => $this->configuration['search_submit_label'],
      '#maxlength' => 255,
      '#size' => 64,
      '#weight' => '1',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['search_view_machine_name'] = $form_state->getValue('advanced_search_view_textfield');
    $this->configuration['search_textfield_label'] = $form_state->getValue('search_textfield');
    $this->configuration['search_placeholder'] = $form_state->getValue('search_placeholder_textfield');
    $this->configuration['search_submit_label'] = $form_state->getValue('search_submit');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\islandora_advanced_search\Form\SimpleSearchForm');
  }

}
