<?php

declare(strict_types=1);

namespace Drupal\auto_content;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\auto_import\TermService;
use Drupal\node\Entity\Node;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * @todo Add class description.
 */
abstract class NodeService {

  protected $nodeType;

  protected $entityTypeManager;

  protected $connection;

  protected $termService;

  protected $configFactory;

  /**
   * Constructs a NodeService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $db_connection,
    TermService $term_service,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $db_connection;
    $this->termService = $term_service;
    $this->configFactory = $config_factory;
  }

  /**
   * Get config.
   */
  public function configAutoImport() {
    return $this->configFactory->get('auto_import.import');
  }

  /**
   * {@inheritdoc}
   */
   public function getStorage() {
     return $this->entityTypeManager->getStorage('node');
   }

   /**
    * @todo Add method description.
    */
   public function getQuery() {
     $query = $this->getStorage()->getQuery();
     $query->accessCheck(TRUE);
     $query->condition('type', $this->nodeType);
     return $query;
   }

   /**
    * @todo Add method description.
    */
   public function loadByFields($fields) {
     $nids = $this->getIdsByFields($fields);
     if (!empty($nids)) {
       $nodes = Node::loadMultiple($nids);
       return $nodes;
     }
     return NULL;
   }

   /**
    * @todo Add method description.
    */
   public function loadOneByFields($fields) {
     $nodes = $this->loadByFields($fields);
     if (!empty($nodes)) {
       return $this->removeExtraNodes($nodes);
     }
   }

   /**
    * @todo Add method description.
    */
   public function getIdsByFields($fields) {
     $query = $this->getQuery();

     foreach ($fields as $fieldname => $value) {
       if ($value != 'notExists') {
         $query->condition($fieldname, [$value], 'IN');
       }
       else {
         $query->notExists($fieldname);
       }
     }

     $nids = $query->execute();
     return $nids;
   }

   /**
    * Load contacts nodes by properties.
    */
   public function loadByProperties($fields) {
     $nids = $this->getIdsByFields($fields);
     if (!empty($nids)) {
       return $this->getStorage()->loadMultiple($nids);
     }
     return [];
   }

   /**
    * @todo Add method description.
    */
   public function getRandomNids($count) {
     $query = $this->getQuery();
     $nids = $query->execute();

     if (count($nids)) {
       shuffle($nids);
       $nids = array_splice($nids, 0, $count);
     }
     return $nids;
   }

   public function removeExtraNodes($nodes) {
     // Remove extra nodes.
     if (!empty($nodes)) {
       $node = array_shift($nodes);
       if (count($nodes) > 0) {
         foreach ($nodes as $node_delete) {
           $node_delete->delete();
         }
       }
       return $node;
     }
   }

   public function genLevel($auto, $service = NULL) {
     $level = 'mark';
     if (!empty($service)) {
       $level = 'service';
     }
     else {
       if (!empty($this->termService->getParentId($auto))) {
         $level = 'model';
       }
     }
     return $level;
   }

   /**
    * Find car_service node by field_remote_id.
    */
   public function findByRemoteId($remote_id) {
     $nodes = $this->getStorage()
       ->loadByProperties([
         'type' => $this->nodeType,
         'field_remote_id' => $remote_id,
     ]);

     return !empty($nodes) ? reset($nodes) : FALSE;
   }

   public function test() {
     dsm($this->nodeType);
   }

}
