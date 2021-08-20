<?php

namespace Drupal\islandora_advanced_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use \Drupal\Core\Url;

class SimpleSearchForm extends FormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'simple_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $block = \Drupal\block\Entity\Block::load("search");

    if ($block) {
      $settings = $block->get('settings');
      $view_machine_name = $settings['search_view_machine_name'];

    }
    $form['search-textfield'] = array(
      '#type' => 'textfield',
      '#title' => (!empty($settings['search_textfield_label']) ? $settings['search_textfield_label'] : ''),
      '#attributes' => ['placeholder' => $settings['search_placeholder']]
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => (!empty($settings['search_submit_label']) ? $settings['search_submit_label'] : 'Search'),
      '#button_type' => 'primary',
    );
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

    global $base_url;
    $url = Url::fromRoute($view_machine_name, [
      'a[0][f]' => 'title',
      'a[0][i]' => 'IS',
      'a[0][v]' => $form_state->getValues()['search-textfield'],

    ]);
    $form_state->setRedirectUrl($url);

  }
}
