<?php

declare(strict_types=1);

namespace Drupal\demo_entity_and_queue;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the enc service data from getpr entity type.
 */
final class DemoEntityListBuilder extends EntityListBuilder {

  protected $formBuilder;

  /**
  * {@inheritdoc}
  */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
    $entity_type,
    $container->get('entity_type.manager')->getStorage($entity_type->id()),
    $container->get('form_builder')
    );
  }

  /**
  * Constructs a new EncServiceDataListBuilder object.
  *
  * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
  * The entity type term.
  * @param \Drupal\Core\Entity\EntityStorageInterface $storage
  * The entity storage class.
  * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
  * The url generator.
  */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, FormBuilderInterface $form_builder) {
    parent::__construct($entity_type, $storage);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['filter_form'] = $this->formBuilder->getForm('Drupal\demo_entity_and_queue\Form\DemoEntityFilterForm');
    $build += parent::render();

    $count = count($build['table']['#rows']);

    $page = \Drupal::request()->get('page');
    $limit = $this->limit;
    $start = $limit * $page;
    $end = $start + $count;

    $total = $this->getEntityListQuery()->count()->execute();
    $build['info'] = [
      '#type' => 'item',
      '#markup' => $this->t('Displaying @start - @end of @total', [
        '@start' => $start,
        '@end' => $end,
        '@total' => $total,
      ]),
      '#weight' => -1,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['duv'] = $this->t('Duv');
    $header['label'] = $this->t('Label');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\demo_entity_and_queue\DemoEntityInterface $entity */
    $row['id'] = $entity->id();
    $row['duv'] = $entity->get('duv')->value;
    $row['label'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery() : \Drupal\Core\Entity\Query\QueryInterface {
    $query = parent::getEntityListQuery(); // Start with the default query
    $request = \Drupal::request();

    if ($label = $request->query->get('label')) {

      $query->condition('label', '%' . $label . '%', 'LIKE');
    }

    return $query;
  }
}
