<?php

declare(strict_types=1);

namespace Drupal\auto_import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * @todo Add class description.
 */
final class YandexReview {

  /**
   * Constructs a YandexReview object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @todo Add method description.
   */
  public function getStorage() {
    return $this->entityTypeManager->getStorage('node');
  }

  /**
   * @todo Add method description.
   */
  public function updNode($node, $data, $car_service_nid) {
    $node->set('title', $data['title']);
    $node->set('field_remote_id', $data['field_remote_id']);
    $node->set('body', !empty($data['body']) ? strip_tags($data['body']) : '');
    $node->set('field_date', $data['field_date']);
    $node->set('field_rating_number', $data['field_rating_number']);
    $node->set('field_rating', $data['field_rating']);
    $node->set('field_car_service', $car_service_nid);
    $node->save();
    return $node;
  }

  /**
   * @todo Add method description.
   */
  public function createNode($data, $nid) {
    $node_data = [
      'type'  => 'yandex_review',
      'title' => $data['title'],
      'field_remote_id' => $data['field_remote_id'],
      'body' => $data['body'],
      'field_date' => $data['field_date'],
      'field_rating_number' => $data['field_rating_number'],
      'field_rating' => $data['field_rating'],
      'field_car_service' => $nid,
    ];
    if (!empty($data['field_remote_id'])) {
      $node_data['field_remote_id'] = $data['field_remote_id'];
    }

    $node = Node::create($node_data);
    $node->save();
    return $node;
  }

  /**
   * Load yandex reviews from contact node.
   */
  public function getContatReviews($nid) {
    $properties = [
      'type' => 'yandex_review',
      'field_car_service' => $nid,
    ];

    $nodes = $this->getStorage()->loadByProperties($properties);
    return $nodes;
  }

  /**
   * @todo Add method description.
   */
  public function findReview($data, $nid) {
    $properties = [
      'type' => 'yandex_review',
      'field_remote_id' => $data['field_remote_id'],
    ];

    $nodes = $this->getStorage()->loadByProperties($properties);
    if (!empty($nodes)) {
      if (count($nodes) > 1) {
        $review_node = array_shift($nodes);
        foreach ($nodes as $node) {
          $node->delete();
        }
      }
      else {
        $review_node = reset($nodes);
      }
      return $review_node;
    }
    return FALSE;
  }

  /**
   * @todo Add method description.
   */
  public function handleReviewData($data, $nid) {
    $node = $this->findReview($data, $nid);
    if (!empty($node)) {
      $node = $this->updNode($node, $data, $nid);
      return $node;
    }
    else {
      $node = $this->createNode($data, $nid);
      return $node;
    }
    return TRUE;
  }

}
