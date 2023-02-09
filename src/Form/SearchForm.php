<?php

namespace Drupal\advanced_search\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use \Drupal\Core\Url;

class SearchForm  extends FormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
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
      $block = \Drupal\block\Entity\Block::load("search");

      if ($block) {
        $settings = $block->get('settings');
        $view_machine_name = $settings['search_view_machine_name'];

      }
      $form['search-textfield'] = array(
        '#type' => 'textfield',
        '#title' => (!empty($settings['search_textfield_label']) ? $settings['search_textfield_label'] : ''),
        '#attributes' => [
          'placeholder' => $this->t($settings['search_placeholder']),
          'aria-label' => $this->t($settings['search_placeholder']),
        ],
        '#theme_wrappers' => []
      );

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => (!empty($settings['search_submit_label']) ? $settings['search_submit_label'] : 'Search'),
        '#button_type' => 'primary',
      );
    }
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $block = \Drupal\block\Entity\Block::load("search");
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
