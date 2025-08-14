<?php

namespace Drupal\demo_service_data;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\demo_mis\Utility\Utility;

/**
 * Defines a class for building markup for comment links on a commented entity.
 *
 * Comment links include 'log in to post new comment', 'add new comment' etc.
 */
final class ServiceDataManager {

  const ENTITY_TYPE = 'demo_service_data';
  const IS_DISTANT = ['Демо Демо2'];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CommentLinkBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get "demo" from service code.
   *
   * @param string $service
   *   The service code.
   */
  public function getPuFromServiceCode($service) {
    $entity = $this->findServiceEntityByCode($service);
    return $this->getPuFromServiceEntity($entity);
  }

  /**
   * Get "demo" from service entity.
   *
   * @param \Drupal\demo_service_data\Entity\DemoServiceData $entity
   *   The service code.
   */
  public function getPuFromServiceEntity($entity) {
    $demo = [];

    if (!$entity->get('demo')->isEmpty()) {
      $demo[] = $entity->demo->value;
    }

    if (!$entity->get('demo1')->isEmpty()) {
      $demo[] = $entity->demo1->value;
    }

    return implode(' ', $demo);
  }

  /**
   * Find "demo_service_data" entity by "service" value.
   *
   * @param string $service
   *   The service code.
   */
  public function findServiceEntityByCode($service) {
    $result = $this->entityTypeManager->getStorage(self::ENTITY_TYPE)->loadByProperties([
      'service' => $service,
    ]);
    if (!empty($result)) {
      foreach ($result as $entity) {
        if ($entity->service->value === $service) {
          return $entity;
        }
      }
    }

    return FALSE;
  }

  /**
   * Find "demo_service_data" entity by "service" value.
   *
   * @param string $service
   *   The service code.
   */
  public function serviceIsDistant($service) {
    $demo = $this->getPuFromServiceCode($service);
    return $this->serviceIsDistantByPu($demo);
  }


  /**
   * Check service is distant by "demo".
   *
   * @param string $demo
   *   The imploded demo codes.
   */
  public function serviceIsDistantByPu($demo) {
    return in_array($demo, self::IS_DISTANT);
  }

}
