<?php

namespace Drupal\islandora_advanced_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use \Drupal\Core\Url;

class SimpleSearchForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['search-textfield'] = array(
      '#type' => 'textfield',
      '#title' => t('Search:'),
        '#attributes' => ['placeholder' => 'Search the collections']
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('SEARCH'),
      '#button_type' => 'primary',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $base_url;
    $url = Url::fromRoute('view.advanced_search.page_1',  [
      'a[0][f]' => 'title',
      'a[0][i]' => 'IS',
      'a[0][v]' => $form_state->getValues()['search-textfield'],

      ]);
    $form_state->setRedirectUrl($url);

  }
}
