<?php

declare(strict_types=1);

namespace Drupal\auto_import;

use Drupal\auto_import\FileService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Extension\ExtensionPathResolver;

/**
 * @todo Add class description.
 */
final class DeleteFiles {
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Constructs a FileDelete object.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $messenger,
    private readonly FileService $fileService,
    private readonly DatabaseFileUsageBackend $fileUsage,
    private readonly ExtensionPathResolver $extensionPathResolver,
  ) {}

  /**
   * @todo Add method description.
   */
  public function deleteUnusedFiles() {
    $files = $this->fileService->loadByProperties(['status' => 1]);

    if (!empty($files)) {
      $batch_builder = $this->batchDeleteFiles($files);
      batch_set($batch_builder->toArray());
    }
  }

  public function batchDeleteFiles($files) {
    $module_path = $this->extensionPathResolver->getPath('module', 'auto_import');
    $batch_builder = (new BatchBuilder())->setTitle('Удалить неспользуемые файлы')
      ->setFinishCallback([$this, 'batchDeleteFilesFinished'])
      ->setFile($module_path . '/src/DeleteFiles.php')
      ->setInitMessage('Запускается проверка на удаление. Кол-во файлов: ' . count($files))
      ->setProgressMessage($this->t('Completed @current of @total.'));

    $batch_builder->addOperation([$this, 'deleteFiles'], [
      $files,
      count($files),
    ]);

    return $batch_builder;
  }

  /**
   * Create delete items for Batch.
   */
  public function deleteFiles($files, $count, array &$context) {
    $limit = 50;

    if (empty($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['deleted'] = 0;
      $context['sandbox']['max'] = count($files);
    }

    if (empty($context['sandbox']['items'])) {
      $context['sandbox']['items'] = $files;
    }

    $counter = 0;
    if (!empty($context['sandbox']['items'])) {
      // Remove already processed items.
      if ($context['sandbox']['progress'] != 0) {
        array_splice($context['sandbox']['items'], 0, $limit);
      }

      foreach ($context['sandbox']['items'] as $item) {
        if ($counter != $limit) {
          $file_deleted = $this->deleteFile($item);
          if ($file_deleted) {
            $context['sandbox']['deleted']++;
          }

          $counter++;
          $context['sandbox']['progress']++;

          $context['message'] = $this->t('Now processing file :progress of :count', [
            ':progress' => $context['sandbox']['progress'],
            ':count' => $context['sandbox']['max'],
          ]);

          $context['results']['processed'] = $context['sandbox']['progress'];
          $context['results']['deleted'] = $context['sandbox']['deleted'];
        }
      }
    }

    // If not finished all tasks, we count percentage of process. 1 = 100%.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Delete contact node and yandex_review nodes.
   */
  public function deleteFile($file) {
    $usage = $this->fileService->getFileUsage($file);
    if (empty($usage)) {
      $file->delete();
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Сообщение с информацией об удалении.
   */
  public function batchDeleteFilesFinished($success, $results, $operations) {
    $message = 'Удалено файлов: ' . $results['deleted'] . ' из ' . $results['processed'];
    $this->messenger->addStatus($message);
  }

}
