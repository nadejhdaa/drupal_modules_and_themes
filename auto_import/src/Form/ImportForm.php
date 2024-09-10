<?php

declare(strict_types=1);

namespace Drupal\auto_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Utility\UrlHelper;
use GuzzleHttp\Stream\Stream;
use Guzzle\Plugin\Oauth\OauthPlugin;
use Drupal\Core\Url;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SessionCookieJar;
use GuzzleHttp\Psr7\Uri;
use Drupal\Component\Serialization\Json;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\auto_import\TermService;
use Drupal\auto_import\ImportContacts;
use Drupal\auto_import\DeleteContacts;
use Drupal\auto_import\DeleteFiles;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Provides a Auto import form.
 */
final class ImportForm extends FormBase {
  use DependencySerializationTrait;
  /**
   * The importer service.
   *
   * @var \Drupal\auto_import\ImportContacts
   */
  protected $importContacts;

  /**
   * The importer factory.
   *
   * @var \Drupal\auto_import\TermService
   */
  protected $termService;

  /**
   * The remove service.
   *
   * @var \Drupal\auto_import\DeleteContacts
   */
  protected $deleteContacts;

  /**
   * The file delete service.
   *
   * @var \Drupal\auto_import\DeleteFiles
   */
  protected $deleteFiles;

  /**
   * {@inheritdoc}
   */
  public function __construct(ImportContacts $import_contacts, TermService $term_service, DeleteContacts $delete_contacts, DeleteFiles $file_delete) {
    $this->importContacts = $import_contacts;
    $this->termService = $term_service;
    $this->deleteContacts = $delete_contacts;
    $this->deleteFiles = $file_delete;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('auto_import.import_contacts'),
      $container->get('auto_import.term_service'),
      $container->get('auto_import.delete_contacts'),
      $container->get('auto_import.file_delete'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'auto_import_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('auto_import.import');

    $form['base_auth'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('BASE AUTH'),
      '#description' => 'Внесенные изменения будут сохранены в базу',
    ];

    $form['base_auth']['base_auth_login'] = [
      '#type' => 'textfield',
      '#default_value' => !empty($config->get('base_auth_login')) ? $config->get('base_auth_login') : '',
    ];

    $form['base_auth']['base_auth_pass'] = [
      '#type' => 'textfield',
      '#default_value' => !empty($config->get('base_auth_pass')) ? $config->get('base_auth_pass') : '',
    ];

    $options = $this->termService->getBrandsOptions();
    $options['_none'] = 'Не имеющие марки';

    $form['brand'] = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => !empty($input['brand']) ? $input['brand'] : 'all',
      '#empty_value' => 'all',
      '#empty_option' => 'Все контакты',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Import contacts'),
        '#submit' => ['::submitForm', '::import'],
      ],
      'delete' => [
        '#type' => 'submit',
        '#value' => $this->t('Delete contacts'),
        '#submit' => ['::delete'],
      ],
      'remove_files' => [
        '#type' => 'submit',
        '#value' => $this->t('Delete unused files'),
        '#submit' => ['::deleteUnusedFiles'],
      ],
    ];



    return $form;
  }

  /**
   * Get options from parent Brands terms.
   *
   * @return array
   */
  public function getBrands() {
    return $this->terms->getBrandsOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = \Drupal::service('config.factory')->getEditable('auto_import.import');
    $input = $form_state->getUserInput();

    if (!empty($input['base_auth_login'])) {
      $config->set('base_auth_login', $input['base_auth_login'])->save();
    }

    if (!empty($input['base_auth_pass'])) {
      $config->set('base_auth_pass', $input['base_auth_pass'])->save();
    }
    $form_state->setRebuild(TRUE);
  }

  /**
   * Start import contacts from recipient site.
   *
   */
  public function import(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $params = [];
    if (!empty($input['brand']) && $input['brand'] !== 'all') {
      $params['brand'] = $input['brand'];
    }
    $this->importContacts->import($params);
  }

  public function delete(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $this->deleteContacts->delete($input['brand']);
  }

  public function deleteUnusedFiles(array &$form, FormStateInterface $form_state) {
    $this->deleteFiles->deleteUnusedFiles();
  }

}
