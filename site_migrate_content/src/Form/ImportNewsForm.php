<?php

declare(strict_types=1);

namespace Drupal\site_migrate_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Provides a ENC migrate content form.
 */
final class ImportNewsForm extends FormBase {

  const MIGRATION_ID = 'migration_news';
  const FILE_NAME = 'news.csv';
  const UPLOAD_LOCATION = 'public://migrations/';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'site_migrate_content_import_news';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['file'] = [
      '#title' => t('Update migration source csv file'),
      '#type' => 'managed_file',
      '#required' => FALSE,
      '#upload_location' => self::UPLOAD_LOCATION,
      '#multiple' => FALSE,
      '#required' => TRUE,
      '#upload_validators' => [
        'FileExtension' => ['csv'],
      ],
    ];

    // Import action.
    $form['actions']['start_import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start import'),
      '#submit' => ['::startImport'],
    ];

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
    $this->messenger()->addStatus($this->t('The message has been sent.'));
    $form_state->setRedirect('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function startImport(array &$form, FormStateInterface $form_state) {
    $fids = $form_state->getValue('file');
    $fid = reset($fids);
    $file = File::load($fid);

    $base_path = getcwd();

    $file_real_path = $base_path . $file->createFileUrl();
    $file_contents = file_get_contents($file_real_path);
    $first_load_place = self::UPLOAD_LOCATION . self::FILE_NAME;

    // Saves new file with fixed name and replaces any existing file.
    $new_file = \Drupal::service('file.repository')->writeData($file_contents, $first_load_place, FileSystemInterface::EXISTS_REPLACE);

    $module_path = $base_path . '/'. \Drupal::service('extension.list.module')->getPath('site_migrate_content');
    $directory_path = $module_path . '/assets';
    $destination_path = $directory_path . '/' . self::FILE_NAME;

    // Replace old file.
    \Drupal::service('file_system')->prepareDirectory($directory_path, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    rename($file_real_path, $destination_path);

    // Delete uploaded file.
    $file->delete();

    // Load and update migration.
    $migration_id = self::MIGRATION_ID;
    $obj_migration = Migration::load($migration_id);

    if ($obj_migration) {
      $source = $obj_migration->get('source');
      $source['path'] = $destination_path;
      $obj_migration->set('source', $source);
      $obj_migration->save();

      $options = [
        'limit' => 0,
        'update' => 1,
        'force' => 0,
      ];

      // Start the migration batch.
      $migrationPlugin = \Drupal::service('plugin.manager.migration')
        ->createInstance($migration_id);
      $executable = new MigrateBatchExecutable($migrationPlugin, new MigrateMessage(), $options);
      $executable->batchImport();
    }
  }

}
