<?php

namespace Drupal\advanced_search\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use \Drupal\Core\Url;

class SearchForm  extends FormBase
{
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
  public function getFormId()
  {
    return 'search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $block = \Drupal\block\Entity\Block::load($this->block_id);
    if ($block) {
      $settings = $block->get('settings');
      $view_machine_name = $settings['search_view_machine_name'];
    }
    $form['search-textfield'] = array(
      '#type' => 'textfield',
      '#title' => (!empty($settings['search_textfield_label']) ? $settings['search_textfield_label'] : 'Enter Keyword'),
      '#attributes' => ['placeholder' => $settings['search_placeholder']],
      '#theme_wrappers' => []
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
    $block = \Drupal\block\Entity\Block::load($this->block_id);
    if ($block) {
      $settings = $block->get('settings');
      $view_machine_name = $settings['search_view_machine_name'];
    }
    if (empty($form_state->getValues()['search-textfield']) || empty($settings['search_method'])) {
      $url = Url::fromRoute($view_machine_name, [
        'a[0][f]' => ($settings['search_method'] == 'dismax') ? 'all' : "copyfield",
        'a[0][i]' => 'IS',
        'a[0][v]' => $form_state->getValues()['search-textfield'],
      ]);
    }
    else {
      $url = Url::fromRoute($view_machine_name, [
        'type' => $settings['search_method'],
        'a[0][f]' => ($settings['search_method'] == 'dismax') ? 'all' : "copyfield",
        'a[0][i]' => 'IS',
        'a[0][v]' => $form_state->getValues()['search-textfield'],
      ]);
    }

    $form_state->setRedirectUrl($url);
  }
}
