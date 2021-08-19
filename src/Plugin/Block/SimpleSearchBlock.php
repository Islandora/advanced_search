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
    $form['search_textfield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Keyword Textfield Label'),
      '#default_value' => $this->configuration['search_textfield'],
      '#maxlength' => 255,
      '#size' => 64,
      '#weight' => '1',
    ];
    $form['search_submit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Button Label'),
      '#default_value' => $this->configuration['search_textfield'],
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
    $this->configuration['search_textfield'] = $form_state->getValue('search_textfield');
    $this->configuration['search_submit'] = $form_state->getValue('search_submit');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\islandora_advanced_search\Form\SimpleSearchForm');
  }

}
