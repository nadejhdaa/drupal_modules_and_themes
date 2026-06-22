<?php

declare(strict_types=1);

namespace Drupal\demo_entity_and_queue\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form controller for the service data entity edit forms.
 */
class DemoEntityFilterForm extends FormBase {

  public function getFormId() {
    return 'demo_entity_and_queue_filter_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->getRequest();

    $form['#method'] = 'get'; // Important for using query parameters
    $form['#action'] = Url::fromRoute('<current>')->toString();

    $form['label'] = [
      '#type' => 'textfield',
      '#default_value' => $request->get('label'),
    ];

    $form['filter_actions'] = [
      '#type' => 'actions',
    ];

    $form['filter_actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply filters'),
    ];

    $form['filter_actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset filters'),
      '#url' => Url::fromRoute('<current>'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get filter value, build URL with query parameter, and set redirect
    // ...
  }
}
