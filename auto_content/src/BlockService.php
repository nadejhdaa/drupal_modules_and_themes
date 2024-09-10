<?php

declare(strict_types=1);

namespace Drupal\auto_content;

use Drupal\auto_import\FileService;
use Drupal\auto_import\TermService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * @todo Add class description.
 */
final class BlockService {

  /**
   * Constructs a BlockService object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileService $autoImportFileService,
    private readonly TermService $autoImportTermService,
    private readonly DatabaseFileUsageBackend $fileUsage,
    private readonly CacheBackendInterface $cacheData,
  ) {}


  public function getQuery($type = 'basic') {
    $query = $this->entityTypeManager->getStorage('block_content')->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('type', $type);
    $query->condition('status', 1);
    return $query;
  }

  public function getStorage() {
    return $this->entityTypeManager->getStorage('block_content');
  }


  public function getBlocksIdsByType($type) {
    $query = $this->getQuery($type);
    $ids = $query->execute();
    return $ids;
  }

  public function getOneIdByType($type) {
    $query = $this->getQuery($type);
    $query->range(0, 1);
    $ids = $query->execute();
    return !empty($ids) ? reset($ids) : NULL;
  }

  public function loadOneBlockByType($type) {
    $id = $this->getOneIdByType($type);
    if ($id) {
      return $this->getStorage()->load($id);
    }
  }

  public function getOneBlockUuid($type) {
    $block = $this->loadOneBlockByType($type);
    return !empty($block) ? $block->uuid() : NULL;
  }

  public function getBlockUuidByType($type) {
    $cid = 'front:' . $type . '_block_uuid';
    if ($cache = $this->cacheData->get($cid)) {
      $block_uuid = $cache->data;
    }
    if (empty($block_uuid)) {
      $block_uuid = $this->getOneBlockUuid($type);
      $this->cacheData->set($cid, $block_uuid);
    }
    return $block_uuid;
  }

}
