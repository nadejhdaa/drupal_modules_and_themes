<?php

declare(strict_types=1);

namespace Drupal\auto_content;

use Drupal\auto_import\TermService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\auto_content\Utility\Utility;

/**
 * @todo Add class description.
 */
final class YandexReviewService extends NodeService {

  protected $nodeType = Utility::NODE_TYPE_YANDEX_REVIEW;

  public function getReviewsData($node) {
    $data = [];
    $nodes = $this->getReviewsByNid($node->id());
    if (!empty($nodes)) {
      foreach ($nodes as $nid => $rev_node) {
        if (!$rev_node->get('field_rating')->isEmpty()) {
          $data[$nid] = [
            'rate' => $rev_node->field_rating->value,
            'text' => $rev_node->body->value,
          ];
        }

      }
    }
    return $data;
  }

  public function getReviewsByNid($nid) {
    $fields = [
      'field_car_service' => $nid,
    ];
    $nodes = $this->loadByFields($fields);
    return $nodes;
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
      'type'  => $this->nodeType,
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
      'type' => $this->nodeType,
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
      'type' => $this->nodeType,
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
