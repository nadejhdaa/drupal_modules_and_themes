<?php

declare(strict_types=1);

namespace Drupal\demo_entity_and_queue\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Unicode;

/**
 * Defines 'demo_entity_and_queue_updater' queue worker.
 */
#[QueueWorker(
  id: 'demo_entity_and_queue_updater',
  title: new TranslatableMarkup('Enc service data getPr updater')
)]
final class Updater extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  const ENTITY_TYPE = 'demo_entity_and_queue';

  /**
   * Constructs a new Updater instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($row): void {
    if (!empty($row['Duv'])) {
      $duv = $row['Duv'];

      $service_data_pr_entity = $this->findServiceByDuv($duv);

      if (!empty($service_data_pr_entity)) {
        $this->updateServiceDataPrEntity($service_data_pr_entity, $row);
      }
      else {
        $this->createServiceDataPrEntity($row);
      }
    }
  }

  public function createServiceDataPrEntity($row) {
    if (!empty($row['u'])) {
      $label = $row['u'];
      $values['label'] = $label;

      if (strlen($label) > 255) {
        $values['label_long'] = $label;

        $label = Unicode::truncateBytes($label, 255);
        $values['label'] = $label;
      }
    }

    $values['duv'] = $row['Duv'];

    if (!empty($row['Mr70'])) {
      $price = (float)$row['Mr70'];
      $values['price'] = $price;
    }

    if (!empty($row['Du'])) {
      foreach ($row['Du'] as $value) {
        $values['du'][] = $value['Du'];
      }
    }

    if (!empty($row['demo83'])) {
      foreach ($row['demo83'] as $value) {
        $values['service'][] = $value['demo83'];
      }
    }

    $entity = $this->entityTypeManager->getStorage(self::ENTITY_TYPE)->create($values);
    $entity->save();

    return $entity;
  }

  public function updateServiceDataPrEntity($service_data_pr_entity, $row) {
    if (!empty($row['u'])) {
      $label = $row['u'];
      $service_data_pr_entity->set('label', $label);

      if (strlen($label) > 255) {
        $service_data_pr_entity->set('label_long', $label);

        $label = Unicode::truncateBytes($label, 255);
        $service_data_pr_entity->set('label', $label);
      }
    }

    if (!empty($row['Du'])) {
      foreach ($row['Du'] as $value) {
        $du[] = $value['Du'];
      }

      $service_data_pr_entity->set('du', $du);
    }

    if (!empty($row['demo83'])) {
      foreach ($row['demo83'] as $value) {
        $demo83[] = $value['demo83'];
      }

      $service_data_pr_entity->set('service', $demo83);
    }

    if (!empty($row['Mr70'])) {
      $price = (float)$row['Mr70'];
      $service_data_pr_entity->set('price', $price);
    }

    $service_data_pr_entity->save();

    return $service_data_pr_entity;
  }

  public function findServiceByDuv($duv) {
    $properties = ['duv' => $duv];
    $result = $this->storage()->loadByProperties($properties);

    if (!empty($result)) {
      return reset($result);
    }
  }

  public function storage() {
    return $this->entityTypeManager->getStorage(self::ENTITY_TYPE);
  }

}
