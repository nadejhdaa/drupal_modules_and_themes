<?php

declare(strict_types=1);

namespace Drupal\med_mis\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\med_mis\Client\MisClient;
use Drupal\med_mis\Client\CLientBase;

/**
 * Configure ЭНЦ добмен данными с МИС settings for this site.
 */
final class MisDebugSettingsForm extends ConfigFormBase {

  /**
   * The "client_base" service.
   *
   * @var \Drupal\med_mis\Client\CLientBase
   */
  protected $clientBase;

  /**
   * The "mis_client" service.
   *
   * @var \Drupal\med_mis\Client\MisClient
   */
  protected $misClient;

  /**
   * The "database" service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $databaseConnection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * MisDebugSettingsForm constructor.
   *
   * @param \Drupal\med_mis\Client\ClientBase $client_base
   *   The client_base service.
   * @param \Drupal\med_mis\Client\MisClient $mis_client
   *   The mis_client service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The "database" service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ClientBase $client_base,
    MisClient $mis_client,
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->clientBase = $client_base;
    $this->misClient = $mis_client;
    $this->databaseConnection = $connection;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('client_base'),
      $container->get('mis_client'),
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'med_mis_debug_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['med_mis.debug_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('med_mis.debug_settings');

    $query = $this->databaseConnection->select('med_mis_debug_users', 'e');
    $query->fields('e');
    $result = $query->execute()->fetchAll();

    $str = '';
    if (!empty($result)) {
      foreach ($result as $row) {
        $rows[] = $row->ip . '|' . $row->email;
      }
      $str = implode(PHP_EOL, $rows);
    }

    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());

    $form['users'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Users who can watch debug info'),
      '#default_value' => $str,
      '#attributes' => [
        'placeholder' => '111.11.111.111|user1@endocrincentr.ru',
      ],
      '#description' => $this->t('@ip|@mail', ['@ip' => $_SERVER['REMOTE_ADDR'], '@mail' => $user->getEmail()]),
    ];

    $form['check'] = [
      '#type' => 'select',
      '#options' => [
        'no_check' => $this->t('Do not check'),
        'role_or_ip' => $this->t('Check by the role or IP'),
        'role_and_ip' => $this->t('Check by the role and IP'),
      ],
      '#default_value' => $config->get('check'),
    ];

    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role) {
      $options[$role->id()] = $role->label();
    }

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('User roles'),
      '#multiple' => TRUE,
      '#default_value' => $config->get('roles') ?: [],
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
    $users_str = $form_state->getValue('users');

    $this->databaseConnection->delete('med_mis_debug_users')->execute();

    if (!empty($users_str)) {
      try {
        $explode = explode(PHP_EOL, $users_str);
        foreach ($explode as $key => $value) {
          $explode[$key] = trim($value);
        }
        $explode = array_unique($explode);

        foreach ($explode as $str) {
          $str_exp = explode('|', $str);
          if (!empty($str_exp[0]) && !empty($str_exp[1])) {
            $fields = [
              'email' => trim($str_exp[1]),
              'ip' => trim($str_exp[0]),
            ];

            $this->databaseConnection->insert('med_mis_debug_users')
              ->fields($fields)->execute();
          }
        }
      }
      catch (Exception $ex) {
        $this->logger('med_mis')->error($ex->getMessage());
      }
    }

    $config = $this->configFactory->getEditable('med_mis.debug_settings');

    $roles_to_save = [];
    foreach ($form_state->getValue('roles') as $key => $value) {
      if (!empty($value)) {
        $roles_to_save[] = $value;
      }
    }

    $config
      ->set('check', $form_state->getValue('check'))
      ->set('methods', $form_state->getValue('methods'))
      ->set('enable_logging', $form_state->getValue('enable_logging'))
      ->set('roles', $roles_to_save)
      ->save();

    $set_debug = $this->clientBase->checkConfigDebugSettings();

    if ($set_debug) {
      $this->messenger()->addMessage($this->t("Debug is active now.<br> This is a test query to MIS."));

      $this->misClient->getAuthFields();
    }

    parent::submitForm($form, $form_state);
  }

}
