<?php

namespace Drupal\advanced_search\Form;

use Drupal\block\Entity\Block;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 *
 */
class SearchForm extends FormBase {
  protected $block_id;

  /**
   * @param $block_id
   */
  public function __construct($block_id) {
    $this->block_id = $block_id;
  }

  /**
   * @return mixed
   */
  public function getBlockId() {
    return $this->block_id;
  }

  /**
   * @param mixed $block_id
   */
  public function setBlockId($block_id): void {
    $this->block_id = $block_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config(SettingsForm::CONFIG_NAME);

    if (!$config->get(SettingsForm::SEARCH_ALL_FIELDS_FLAG)) {
      $form['search-attributes'][SettingsForm::SEARCH_ALL_FIELDS_FLAG] = [
        '#markup' => $this
          ->t('<strong>This block is required to enable searching all fields for the Advanced Search.
            To proceed, please enable the Search All fields in 
            <a href="/admin/config/search/advanced" target="_blank">Advanced Seach Configuration</a></strong>.'),
      ];
    }
    else {
      $block = Block::load($this->block_id);

      if ($block) {
        $settings = $block->get('settings');
        $view_machine_name = $settings['search_view_machine_name'];

      }
      $form['search-textfield'] = [
        '#type' => 'textfield',
        '#title' => (!empty($settings['search_textfield_label']) ? $settings['search_textfield_label'] : ''),
        '#attributes' => [
          'placeholder' => isset($settings['search_placeholder']) ? $this->t($settings['search_placeholder']) : $this->t("Search collections"),
          'aria-label' => (isset($settings['search_textfield_label']) ? $this->t($settings['search_textfield_label']) : $this->t('Enter Keyword')),
        ],
        '#theme_wrappers' => [],
      ];

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => (!empty($settings['search_submit_label']) ? $settings['search_submit_label'] : 'Search'),
        '#button_type' => 'primary',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $block = Block::load($this->block_id);
    if ($block) {
      $settings = $block->get('settings');
      $view_machine_name = $settings['search_view_machine_name'];
    }
    $url = Url::fromRoute($view_machine_name, [
      'a[0][f]' => 'all',
      'a[0][i]' => 'IS',
      'a[0][v]' => $form_state->getValues()['search-textfield'],

    ]);
    $form_state->setRedirectUrl($url);
  }

}
