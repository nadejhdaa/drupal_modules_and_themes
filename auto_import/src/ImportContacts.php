<?php

declare(strict_types=1);

namespace Drupal\auto_import;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\auto_import\Client\ClientBase;
use Drupal\auto_content\CarServiceService;
use Drupal\auto_content\YandexReviewService;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Batch\BatchManagerInterface;
use Drupal\Core\Batch\BatchStorage;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\auto_import\FileService;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\simple_sitemap\Manager\EntityManager;

/**
 * @todo Add class description.
 */
final class ImportContacts implements ContainerInjectionInterface {
  use StringTranslationTrait;

  use DependencySerializationTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal\auto_import\ClientBase definition.
   *
   * @var \Drupal\auto_import\ClientBase
   */
  protected $client;

  /**
   * Drupal\auto_content\CarServiceService definition.
   *
   * @var \Drupal\auto_content\CarServiceService
   */
  protected $contact;

  /**
   * The batch builder.
   *
   * @var \Drupal\Core\Batch\BatchBuilderInterface
   */
  protected $batchBuilder;

  /**
   * Drupal\auto_import\YandexReview definition.
   *
   * @var \Drupal\auto_content\YandexReviewService
   */
  protected $review;

  /**
   * Drupal\auto_import\FileService definition.
   *
   * @var \Drupal\auto_import\FileService
   */
  protected $fileService;

  /**
   * Drupal\Core\Extension\ExtensionPathResolver definition.
   *$entity_manager
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * Drupal\simple_sitemap\Manager\EntityManager definition.
   *
   * @var \Drupal\simple_sitemap\Manager\EntityManager
   */
  protected $entityManager;

  /**
   * Constructs an ImportContacts object.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $config_factory,
    private readonly EntityTypeManagerInterface $entity_type_manager,
    private readonly MessengerInterface $Messenger,
    private readonly ClientBase $Client,
    private readonly CarServiceService $contact_service,
    private readonly TranslationInterface $string_translation,
    private readonly YandexReviewService $yandex_review,
    private readonly FileService $file_service,
    private readonly ExtensionPathResolver $extension_path_resolver,
    private readonly EntityManager $entity_manager,
  ) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $Messenger;
    $this->client = $Client;
    $this->contact = $contact_service;
    $this->stringTranslation = $string_translation;
    $this->review = $yandex_review;
    $this->fileService = $file_service;
    $this->extensionPathResolver = $extension_path_resolver;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * Retrieves a configuration object.
   *
   * This is the main entry point to the configuration API. Calling
   * @code $this->config('book.admin') @endcode will return a configuration
   * object in which the book module can store its administrative settings.
   *
   * @param string $name
   *   The name of the configuration object to retrieve. The name corresponds to
   *   a configuration file. For @code \Drupal::config('book.admin') @endcode,
   *   the config object returned will contain the contents of book.admin
   *   configuration file.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   A configuration object.
   */
  protected function config($name) {
    return $this->configFactory->getEditable($name);
  }

  /**
   * Get data from source site and start Batch generation.
   */
  public function import($params) {
    $data = $this->getData($params);

    if (!empty($data['nodes'])) {
      $batch_builder = $this->generateBatchContacts($data);
      batch_set($batch_builder->toArray());
    }
  }

  /**
   * Create request to get contacts data from source url.
   *
   * @return array
   * Array of nodes data.
   */
  public function getData($params) {
    $this->client->setParams($params);
    $json = $this->client->getContacts();
    if (!empty($json)) {
      $data = json_decode($json, TRUE);
      return $data;
    }
    return FALSE;
  }

  /**
   * Build object for generate BatchBuilder.
   *
   * @return Drupal\Core\Batch\BatchBuilder;
   * Objects with data of batch object.
   */
  public function generateBatchContacts($data) {
    $module_path = $this->extensionPathResolver->getPath('module', 'auto_import');
    $nodes = $data['nodes'];
    $batch_builder = (new BatchBuilder())->setTitle('Импорт контактов')
      ->setFinishCallback([$this, 'batchContactFinished'])
      ->setFile($module_path . '/src/ImportContacts.php')
      ->setInitMessage('Запускается импорт. Кол-во контактов: ' . count($nodes))
      ->setProgressMessage($this->t('Completed @current of @total.'));

    $batch_builder->addOperation([$this, 'processItems'], [
      $nodes,
      count($nodes),
    ]);

    return $batch_builder;
  }

  /**
   * Process import contact data rows.
   *
   */
  public function processItems($items, $count, array &$context) {
    $limit = 50;

    if (empty($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['reviews'] = 0;
      $context['sandbox']['max'] = count($items);
    }

    if (empty($context['sandbox']['items'])) {
      $context['sandbox']['items'] = $items;
    }

    $counter = 0;
    if (!empty($context['sandbox']['items'])) {
      // Remove already processed items.
      if ($context['sandbox']['progress'] != 0) {
        array_splice($context['sandbox']['items'], 0, $limit);
      }

      foreach ($context['sandbox']['items'] as $item) {
        if ($counter != $limit) {
          $this->processItem($item);

          if (!empty($item['yandex_reviews'])) {
            $context['sandbox']['reviews']  = $context['sandbox']['reviews'] + count($item['yandex_reviews']);
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
   * Set function to handle row data.
   *
   * @return bulean;
   *
   */
  public function processItem($row) {
    if (!empty($row['field_remote_id'])) {
      $node = $this->contact->findByRemoteId($row['field_remote_id']);

      if (!empty($node)) {
        $node = $this->contact->updateNode($node, $row);
      }
      else {
        $node = $this->contact->createNode($row);
      }

      // Обновить статус индексации в sitemap.
      $this->entityManager->setEntityInstanceSettings(
        'node', $node->id(), [
          'index' => TRUE,
          'priority' => '1.0',
        ]
      );

      if (!empty($row['yandex_reviews']) && !empty($node->id())) {
        foreach ($row['yandex_reviews'] as $data) {
          $this->review->handleReviewData($data, $node->id());
        }
      }
    }
    return TRUE;
  }

  /**
   * Build message show on batch finished.
   *
   * @return bulean;
   *
   */
  public function batchContactFinished($success, $results, $operations) {
    $message = $this->t('Всего обработано контактов: @count, отзывов: @reviews.', [
      '@count' => $results['processed'],
      '@reviews' => $results['reviews'],
    ]);

    $this->messenger->addStatus($message);
    return TRUE;
  }

}
