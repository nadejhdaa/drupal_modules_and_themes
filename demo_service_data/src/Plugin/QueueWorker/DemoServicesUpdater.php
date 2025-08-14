<?php

declare(strict_types=1);

namespace Drupal\demo_service_data\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'demo_service_data_mis_services_updater' queue worker.
 */
#[QueueWorker(
  id: 'demo_service_data_mis_services_updater',
  title: new TranslatableMarkup('Demo services updater'),
  cron: ['time' => 60],
)]
final class DemoServicesUpdater extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  const ENTITY_TYPE = 'demo_service_data';

  /**
   * Constructs a new DemoServicesUpdater instance.
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
  public function processItem($data): void {
    $service = $data['demo'];
    $label = $data['u'];

    $service_data_entity = $this->findServiceByCodeAndLabel($service, $label);

    if (!empty($service_data_entity)) {
      $this->updateServiceDataEntity($service_data_entity, $data);
    }
    else {
      $this->createServiceDataEntity($data);
    }
  }

  /**
   * Search exists entity by service code.
   */
  public function findServiceByCodeAndLabel($service, $label) {
    $result = $this->entityTypeManager->getStorage(self::ENTITY_TYPE)->loadByProperties([
      'service' => $service,
      'label' => $label,
    ]);

    if (!empty($result)) {
      return reset($result);
    }
  }

  /**
   * Update exists entity.
   */
  public function updateServiceDataEntity($service_data_entity, $data) {
    $service_data_entity->set('label', $data['u']);
    $service_data_entity->set('status', 1);
    $service_data_entity->set('service', $data['demo']);

    if (!empty($data['demo'])) {
      $service_data_entity->set('demo', $data['demo']);
    }

    if (!empty($data['demo1'])) {
      $service_data_entity->set('demo1', $data['demo1']);
    }

    if (!empty($data['DemoDu'])) {
      $service_data_entity->set('demo_du', $data['DemoDu']);
    }

    $service_data_entity->save();

    return $service_data_entity;
  }

  /**
   * Create new entity.
   */
  public function createServiceDataEntity($data) {
    $values = [
      'label' => $data['u'],
      'service' => $data['demo'],
      'status' => 1,
    ];

    if (!empty($data['demo'])) {
      $values['demo'] = $data['demo'];
    }

    if (!empty($data['demo1'])) {
      $values['demo1'] = $data['demo1'];
    }

    if (!empty($data['DemoDu'])) {
      $values['demo_du'] = $data['DemoDu'];
    }

    $entity = $this->entityTypeManager->getStorage(self::ENTITY_TYPE)->create($values);
    $entity->save();

    return $entity;
  }

}
