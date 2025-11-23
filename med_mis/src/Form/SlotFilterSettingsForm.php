<?php

declare(strict_types=1);

namespace Drupal\med_mis\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure filter slots settings for this site.
 */
final class SlotFilterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'med_mis_slot_filter_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['med_mis.slot_filter_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('med_mis.slot_filter_settings');
    $filters = $config->get('filters');

    $form['filters'] = [
      '#type' => 'container',
    ];
    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="filters-settings-form-wrapper">';
    $form['#suffix'] = '</div>';

    $num_filters = $form_state->get('num_filters');

    if (!empty($filters)) {
      foreach ($filters as $key => $filter) {
        $form['filters'][$key] = [
          '#type' => 'fieldset',
          '#title' => 'Filter ' . $key + 1,
        ];

        $form['filters'][$key]['pu'] = [
          '#type' => 'textfield',
          '#title' => 'pu',
          '#default_value' => $filter['pu'],
          '#attributes' => [
            'size' => 90,
          ],
        ];

        $rows = count($filter['filter']) + 1;
        $filter = implode('
', $filter['filter']);
        $form['filters'][$key]['filter'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Filter'),
          '#default_value' => $filter,
          '#attributes' => [
            'rows' => $rows,
          ],
        ];
      }

      if ($num_filters > 0) {
        $range = range(0, $num_filters);

        foreach ($range as $additional_key) {
          $new_filter_key = $key + $additional_key;

          $form['filters'][$new_filter_key] = [
            '#type' => 'fieldset',
            '#title' => 'Filter ' . $new_filter_key + 1,
          ];

          $form['filters'][$new_filter_key]['pu'] = [
            '#type' => 'textfield',
            '#title' => 'pu',
            '#default_value' => '',
            '#attributes' => [
              'size' => 90,
            ],
          ];

          $form['filters'][$new_filter_key]['filter'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Filter'),
            '#default_value' => '',
          ];
        }
      }

      $form['actions']['add'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add filter'),
        '#submit' => ['::addOne'],
        '#ajax' => [
          'callback' => '::addFilterItemAjaxCallback',
          'wrapper' => 'filters-settings-form-wrapper',
        ],
      ];

      $form['actions']['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove filter'),
        '#submit' => ['::removeOne'],
        '#ajax' => [
          'callback' => '::addFilterItemAjaxCallback',
          'wrapper' => 'filters-settings-form-wrapper',
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler for the "add" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $num_filters = $form_state->get('num_filters') ?: 0;
    $add_button = $num_filters + 1;
    $form_state->set('num_filters', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeOne(array &$form, FormStateInterface $form_state) {
    $num_filters = $form_state->get('num_filters') ?: 0;
    $add_button = $num_filters - 1;
    $form_state->set('num_filters', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addFilterItemAjaxCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $filters = $form_state->getValue('filters');

    foreach ($filters as $filter_key => $filter) {
      $filter_value = $filter['filter'];
      $filter_rows = explode('
', $filter_value);
      foreach ($filter_rows as $key => $filter_row) {
        $filter_rows[$key] = trim($filter_row);
      }
      $filters[$filter_key]['filter'] = $filter_rows;
    }

    $config = $this->configFactory->getEditable('med_mis.slot_filter_settings');
    $config->set('filters', $filters)->save();

    parent::submitForm($form, $form_state);
  }

}
