<?php

declare(strict_types=1);

namespace Drupal\auto_content;

use Drupal\auto_import\TermService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\Core\Database\Connection;
use Drupal\auto_content\NodeService;
use Drupal\auto_content\Utility\Utility;

/**
 * @todo Add class description.
 */
final class IntersectionService extends NodeService {

  protected $nodeType = Utility::NODE_TYPE_INTERSECTION;

  /**
   * @todo Add method description.
   */
  public function createIntersection($node_data) {
    $node_data['type'] = $this->nodeType;
    $node = Node::create($node_data);
    $node->save();
    return $node;
  }

  public function findIntersectionFromTerm($auto, $service = NULL, $area = NULL) {
    $fields = [
      'field_auto' => $auto->id(),
    ];
    if (!empty($service)) {
      $fields['field_service'] = $service->id();
    }
    if (!empty($area)) {
      $fields['field_area'] = $area->id();
    }

    $nodes = $this->loadByFields($fields);
    return $nodes;
  }

  public function generateTitle($auto, $service = NULL, $area = NULL) {
    $words = [];
    if (empty($service)) {
      $words[] = 'Ремонт';
    }
    else {
      $words[] = $service->getName();
    }

    $parent = $this->termService->getParent($auto);
    if (empty($parent)) {
      $words[] = $auto->getName();
    }
    else {
      $words[] = $parent->getName();
      $words[] = $auto->getName();
    }

    if (!empty($area)) {
      $words[] = 'в ' . mb_strtoupper($area->getName());
    }

    $title = implode(' ', $words);

    return $title;
  }


  /**
   * @todo Add method description.
   */
  public function updateIntersectionFromTerms($node, $auto = NULL, $service = NULL, $area = NULL) {
    $title = $this->generateTitle($auto, $service, $area);
    $node->set('title', $title);

    if (!empty($auto)) {
      $node->set('field_auto', $this->termService->getTid($auto));
    }

    if (!empty($service)) {
      $node->set('field_service', $this->termService->getTid($service));
    }

    if (!empty($area)) {
      $node->set('field_area', $this->termService->getTid($area));
    }

    $node->save();
    return $node;
  }

  public function setIntersectionNodeDataFromRow($row) {
    $auto = $row['auto']['term'];

    $node_data['field_auto'] = $this->termService->getTid($auto);
    if (!empty($row['service']['term'])) {
      $service = $row['service']['term'];
      $node_data['field_service'] = $this->termService->getTid($service);
    }
    else {
      $service = NULL;
    }

    if (!empty($row['area']['term'])) {
      $area = $row['area']['term'];
      $node_data['field_area'] = $this->termService->getTid($area);
    }
    else {
      $area = NULL;
    }

    $title = $this->generateTitle($auto, $service, $area);
    $node_data['title'] = $title;

    $level = $this->genLevel($auto, $service);

    $node_data['field_level'] = $level;

    return $node_data;
  }

  public function createIntersectionFromTerms($auto, $service = NULL, $area = NULL) {
    $level = $this->genLevel($auto, $service);
    $title = $this->generateTitle($auto, $service, $area);
    $node_data = [
      'title' => $title,
      'field_level' => $level,
      'field_auto' => $auto->id(),
    ];

    if (!empty($service)) {
      $node_data['field_service'] = $service->id();
    }

    if (!empty($area)) {
      $node_data['field_area'] = $area->id();
    }

    $node = $this->createIntersection($node_data);
    return $node;
  }

  public function handleIntersection($auto, $service = NULL, $area = NULL) {
    $nodes = $this->findIntersectionFromTerm($auto, $service, $area);
    if (!empty($nodes)) {
      $node = $this->removeExtraNodes($nodes);
      $this->updateIntersectionFromTerms($node, $auto, $service, $area);
    }
    else {
      $node = $this->createIntersectionFromTerms($auto, $service, $area);
    }
    return $node;
  }

  public function findIntersectionFromMark($auto) {
    $fields = [
      'field_auto' => $auto->id(),
    ];
    $query = $this->getQuery();
    $query->condition('field_auto', [$fields['field_auto']], 'IN');

    $nids = $query->execute();
    if (!empty($nids)) {
      $nodes = Node::loadMultiple($nids);
      return $nodes;
    }
    return NULL;
  }

  public function findIntersectionsFromTerm($term) {
    $term_id = $term->id();
    $query = $this->connection->select('taxonomy_index', 'ti');
    $query->fields('ti', ['nid']);
    $query->condition('ti.tid', $term_id);
    $nids = $query->execute()->fetchCol();

    if (!empty($nids)) {
      $nodes = Node::loadMultiple($nids);
      return $nodes;
    }
    return NULL;
  }


}
