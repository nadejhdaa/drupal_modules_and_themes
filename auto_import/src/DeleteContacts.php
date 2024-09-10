<?php

declare(strict_types=1);

namespace Drupal\auto_import;

use Drupal\auto_content\CarServiceService;
use Drupal\auto_content\YandexReviewService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\auto_content\Utility\Utility;
use Drupal\auto_import\TermService;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Batch\BatchManagerInterface;
use Drupal\Core\Batch\BatchStorage;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Extension\ExtensionPathResolver;

/**
 * @todo Add class description.
 */
final class DeleteContacts {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Constructs a DeleteContacts object.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $messenger,
    private readonly CarServiceService $contactService,
    private readonly YandexReviewService $yandexReview,
    private readonly TermService $termService,
    private readonly ExtensionPathResolver $extensionPathResolver,
  ) {}

  /**
   * @todo Add method description.
   */
  public function delete($brand) {
    $properties = [];
    if (!empty($brand) && $brand !== 'all' && $brand !== '_none') {
      $auto = $this->termService->findTidsByName($brand, Utility::VID_BRANDS);
      if (!empty($auto)) {
        $properties = ['field_auto' => reset($auto)];
      }
    }
    elseif ($brand == '_none') {
      $properties = ['field_auto' => 'notExists'];
    }
    $contacts = $this->contactService->loadByProperties($properties);
    if (!empty($contacts)) {
      foreach ($contacts as $contact) {
        $reviews = $this->yandexReview->getContatReviews($contact->id());
        if (!empty($reviews)) {
          $contact->reviews = $reviews;
        }
      }
      $batch_builder = $this->generateBatchDeleteNodes($contacts);
      batch_set($batch_builder->toArray());
    }
  }

  /**
   * Build Batch, add contacts nodes to it.
   */
  public function generateBatchDeleteNodes($contacts) {
    $module_path = $this->extensionPathResolver->getPath('module', 'auto_import');
    $batch_builder = (new BatchBuilder())->setTitle('Импорт контактов')
      ->setFinishCallback([$this, 'batchDeleteContactFinished'])
      ->setFile($module_path . '/src/DeleteContacts.php')
      ->setInitMessage('Запускается удаление. Кол-во контактов: ' . count($contacts))
      ->setProgressMessage($this->t('Completed @current of @total.'));

    $batch_builder->addOperation([$this, 'deleteContacts'], [
      $contacts,
      count($contacts),
    ]);

    return $batch_builder;
  }

  /**
   * Create delete items for Batch.
   */
  public function deleteContacts($contacts, $count, array &$context) {
    $limit = 50;

    if (empty($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['reviews'] = 0;
      $context['sandbox']['max'] = count($contacts);
    }

    if (empty($context['sandbox']['items'])) {
      $context['sandbox']['items'] = $contacts;
    }

    $counter = 0;
    if (!empty($context['sandbox']['items'])) {
      // Remove already processed items.
      if ($context['sandbox']['progress'] != 0) {
        array_splice($context['sandbox']['items'], 0, $limit);
      }

      foreach ($context['sandbox']['items'] as $item) {
        if ($counter != $limit) {
          $this->deleteItem($item);

          if (!empty($item->yandex_reviews)) {
            $context['sandbox']['reviews']  = $context['sandbox']['reviews'] + count($item->yandex_reviews);
          }

          $counter++;
          $context['sandbox']['progress']++;

          $context['message'] = $this->t('Now processing node :progress of :count', [
            ':progress' => $context['sandbox']['progress'],
            ':count' => $context['sandbox']['max'],
          ]);

          // Increment total processed item values. Will be used in finished
          // callback.
          $context['results']['processed'] = $context['sandbox']['progress'];
          $context['results']['reviews'] = $context['sandbox']['reviews'];
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
  public function deleteItem($contact) {
    if (!empty($contact->reviews)) {
      
      foreach ($contact->reviews as $review) {
        $review->delete();
      }

      $contact->delete();
    }
    return TRUE;
  }

  /**
   * Сообщение с информацией об удалении.
   */
  public function batchDeleteContactFinished($success, $results, $operations) {
    $message = $this->t('Deleted contacts: @count, отзывов: @reviews.', [
      '@count' => $results['processed'],
      '@reviews' => $results['reviews'],
    ]);

    $this->messenger->addStatus($message);
  }

}
