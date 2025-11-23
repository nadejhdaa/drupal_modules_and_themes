<?php

declare(strict_types=1);

namespace Drupal\med_mis\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Enc mis settings for this site.
 */
final class MisSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'med_mis_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['med_mis.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('med_mis.settings');

    $form['type'] = [
      '#type' => 'radios',
      '#options' => [
        'test' => $this->t('Test server'),
        'prod' => $this->t('Prod server'),
      ],
      '#title' => $this->t('Server for request'),
      '#default_value' => $config->get('type') ?: 'test',
    ];

    $form['url_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test URL'),
      '#default_value' => $config->get('url_test'),
      '#prefix' => '<div id="server-url">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'test'],
        ],
      ],
    ];

    $form['url_prod'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prod URL'),
      '#default_value' => $config->get('url_prod'),
      '#prefix' => '<div id="server-url">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'prod'],
        ],
      ],
    ];

    $form['port_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port on test server'),
      '#default_value' => $config->get('port_test'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'test'],
        ],
      ],
    ];

    $form['port_prod'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port on prod server'),
      '#default_value' => $config->get('port_prod'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'prod'],
        ],
      ],
    ];

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $config->get('username'),
    ];

    $form['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('password'),
    ];

    $form['rest_auth'] = [
      '#type' => 'fieldset',
      '#title' => 'Rest auth',
    ];

    $form['rest_auth']['qqc235'] = [
      '#type' => 'textfield',
      '#title' => $this->t('qqc235'),
      '#default_value' => $config->get('rest_auth')[0]['qqc235'] ?: '',
    ];

    $form['rest_auth']['apikey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('apikey'),
      '#default_value' => $config->get('rest_auth')[1]['apikey'] ?: '',
    ];

    $form['rest_auth']['qqc244'] = [
      '#type' => 'textfield',
      '#title' => $this->t('qqc244'),
      '#default_value' => $config->get('rest_auth')[2]['qqc244'] ?: '',
    ];

    return parent::buildForm($form, $form_state);
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
    $config = $this->configFactory->getEditable('med_mis.settings');

    $rest_auth = [
      ['qqc235' => $form_state->getValue('qqc235')],
      ['apikey' => $form_state->getValue('apikey')],
      ['qqc244' => $form_state->getValue('qqc244')],
    ];

    $config
      ->set('type', $form_state->getValue('type'))
      ->set('url_test', $form_state->getValue('url_test'))
      ->set('url_prod', $form_state->getValue('url_prod'))
      ->set('port_test', $form_state->getValue('port_test'))
      ->set('port_prod', $form_state->getValue('port_prod'))
      ->set('username', $form_state->getValue('username'))
      ->set('password', $form_state->getValue('password'))
      ->set('rest_auth', $rest_auth)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
