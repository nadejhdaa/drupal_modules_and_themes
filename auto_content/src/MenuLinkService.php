<?php

declare(strict_types=1);

namespace Drupal\auto_content;

use Drupal\auto_content\IntersectionService;
use Drupal\auto_import\TermService;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\auto_content\Utility\Utility;

/**
 * @todo Add class description.
 */
final class MenuLinkService {

  /**
   * Constructs a MenuLinkService object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TermService $termService,
    private readonly IntersectionService $intersection,
    private readonly MenuLinkManagerInterface $menuLinkManager,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {}

  /**
    * {@inheritdoc}
    */
  public function getStorage() {
    return $this->entityTypeManager->getStorage('menu_link_content');
  }

  /**
    * {@inheritdoc}
    */
  public function getQuery() {
    $query = $this->getStorage()->getQuery();
    $query->accessCheck(TRUE);
    return $query;
  }

  /**
    * {@inheritdoc}
    */
  public function findOneByFields($fields) {
    $menu_links = $this->findByFields($fields);
    if (!empty($menu_links)) {
      $menu_link = $this->removeExtraEntities($menu_links);
      return $menu_link;
    }
    return;
  }

  /**
    * {@inheritdoc}
    */
  public function findByFields($fields) {
    $query = $this->getQuery();
    $query->condition('menu_name', $fields['menu_name']);

    if (!empty($fields['title'])) {
      $query->condition('title', $fields['title']);
    }

    if (!empty($fields['link'])) {
      $query->condition('link', $fields['link']);
    }

    if (!empty($fields['parent']) && $fields['parent'] == NULL) {
      $query->notExists('parent');
    }

    if (!empty($fields['field_auto'])) {
      $query->condition('field_auto', $fields['field_auto']);
    }

    if (!empty($fields['field_service'])) {
      if ($fields['field_service'] == 'notExists') {
        $query->notExists('field_service');
      }
      else {
        $query->condition('field_service', $fields['field_service']);
      }
    }

    $ids = $query->execute();

    if (!empty($ids)) {
      $menu_links = MenuLinkContent::loadMultiple($ids);
      return $menu_links;
    }
    return NULL;
  }

  /**
    * {@inheritdoc}
    */
  public function findOneByProperties($properties) {
    $menu_links = $this->findByProperties($properties);
    if (!empty($menu_links)) {
      return $this->removeExtraEntities($menu_links);
    }
    return;
  }

  /**
    * {@inheritdoc}
    */
  public function findByProperties($properties) {
    $menu_links = $this->getStorage()->loadByProperties($properties);
    return $menu_links;
  }

  /**
   * {@inheritdoc}
   */
  public function findOneByNode($node) {
    $menu_links = $this->findByNode($node);
    if (!empty($menu_links)) {
      $menu_link = $this->removeExtraEntities($menu_links);
      return $menu_link;
    }
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function findByNode($node) {
    $menu_link_contents = [];
    $result = $this->menuLinkManager->loadLinksByRoute('entity.node.canonical', ['node' => $node->id()]);
    if (!empty($result)) {
      $uuids = array_keys($result);
      foreach ($uuids as $uuid) {
        $uuid = str_replace('menu_link_content:', '', $uuid);
        $menu_link_contents[] = $this->entityRepository->loadEntityByUuid('menu_link_content', $uuid);
      }
    }
    return $menu_link_contents;
  }

  /**
    * {@inheritdoc}
    */
  public function removeExtraEntities($entities) {
    // Remove extra entites.
    if (!empty($entities)) {
      $entity = array_shift($entities);
      if (count($entities) > 0) {
        foreach ($entities as $entity_to_delete) {
          $entity_to_delete->delete();
        }
      }
      return $entity;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function handleServiceParentLink($super_parent_link, $mark, $service, $menu_name) {
    $fields = [
      'menu_name' => $menu_name,
      'field_auto' => $mark->id(),
      'field_service' => $service->id(),
      'parent' => Utility::getParentUuid($super_parent_link),
    ];
    $menu_link = $this->findOneByFields($fields);
    if (empty($menu_link)) {
      $menu_link = $this->createServiceParentLink($mark, $service, $menu_name, $super_parent_link);
    }
    else {
      $properties = [
        'field_service' => $service->id(),
        'field_auto' => $mark->id(),
      ];
      $this->updateLink($menu_link, $properties);
    }
    return $menu_link;
  }

  /**
   * {@inheritdoc}
   */
  public function createServiceParentLink($mark, $service, $menu_name, $super_parent_link) {
    $new_link = [
      'title' => Utility::getMenuLinkTitle($service),
      'link' => Utility::NEW_NO_LINK,
      'menu_name' => $menu_name,
      'weight' => $service->getWeight(),
      'description' => $service->getName(),
      'parent' => Utility::getParentUuid($super_parent_link),
      'field_auto' => $mark->id(),
      'field_service' => $service->id(),
    ];

    $menu_link = $this->createMenuLink($new_link);
    return $menu_link;
  }

  /**
   * {@inheritdoc}
   */
  public function createMenuLink($data) {
    $data['expanded'] = !empty($data['expanded']) ? $data['expanded'] : TRUE;
    $menu_link = MenuLinkContent::create($data);
    $menu_link->save();
    return $menu_link;
  }

  /**
   * {@inheritdoc}
   */
  public function createMenuLinkForNode($node) {
    $auto_tid = !empty($node->field_auto->target_id) ? $node->field_auto->target_id : NULL;
    if (!empty($auto_tid)) {
      $auto = Term::load($auto_tid);
    }
    $service_tid = !empty($node->field_service->target_id) ? $node->field_service->target_id : NULL;
    $area_tid = !empty($node->field_area->target_id) ? $node->field_area->target_id : NULL;

    // Auto link.
    if (empty($service_tid) && empty($area_tid)) {
      $menu_name = Utility::MENU_MAIN_NAME;
      $link = Utility::getLinkUrl($node);

      // Если это марка.
      if (empty($auto->parent->target_id)) {
        $mark = $auto;
        $mark_link = $this->createMarkLink($menu_name, $mark, $node);

        $level_link = $this->findLevelLink($mark_link, $auto);
        if (empty($level_link)) {
          $level_link = $this->createLevelLink($mark_link, $auto);
        }
        return $level_link;
      }

      // Если это модель.
      else {
        $parent = Term::load($auto->parent->target_id);
        $level = $this->intersection->genLevel($parent);
        $fields = [
          'field_auto' => $parent->id(),
          'field_level' => $level,
        ];
        $mark_node = $this->intersection->loadOneByFields($fields);
        $mark_link = $this->findOneByNode($mark_node);

        $level_link = $this->findLevelLink($mark_link, $parent);
        $properties = [
          'parent' => Utility::getParentUuid($mark_link),
          'description' => $parent->getName(),
        ];
        $level_link = !empty($level_link) ? $this->updateLink($level_link, $properties) : $this->createLevelLink($mark_link, $parent);

        $new_model_link = [
          'title' => $auto->getName(),
          'menu_name' => $menu_name,
          'link' => $link,
          'weight' => $auto->getWeight(),
          'description' => $auto->getName(),
          'parent' => Utility::getParentUuid($level_link),
        ];
        $model_link = $this->createMenuLink($new_model_link);
        return $model_link;
      }
    }

    // Create Service link.
    if (!empty($service_tid) && empty($area_tid)) {
      $menu_name = Utility::createMenuName($auto);
      $menu = $this->entityTypeManager->getStorage('menu')->load($menu_name);
      if (empty($menu)) {
        $menu_service = \Drupal::service('auto_content.menu_service');
        $menu = $menu_service->createMarkServicesMenu($auto);
      }

      // Найти марку.
      $mark = !empty($auto->parent->target_id) ? Term::load($auto->parent->target_id) : $auto;
      $service = Term::load($service_tid);

      $super_parent_link = $this->handleServiceSuperParentLink($mark, $menu_name, $service);

      // Если услуга НЕ родительская.
      if (!empty($service->parent->target_id)) {
        $parent_service = Term::load($service->parent->target_id);
        $parent_link = $this->handleServiceParentLink($super_parent_link, $mark, $parent_service, $menu_name);

        $new_link = [
          'title' => Utility::getMenuLinkTitle($service),
          'menu_name' => $menu_name,
          'link' => Utility::getLinkUrl($node),
          'weight' => $service->getWeight(),
          'description' => $service->getName(),
          'parent' => Utility::getParentUuid($parent_link),
          'field_auto' => $auto->id(),
          'field_service' => $service->id(),
        ];
        $service_link = $this->createMenuLink($new_link);
        return $service_link;
      }

      return NULL;
    }

    // Area tid.
    if (!empty($area_tid)) {
      $mark = Term::load($auto_tid);
      $area = Term::load($area_tid);
      $mark_node = $this->intersection->handleIntersection($mark);
      $mark_link = $this->findOneByNode($mark_node);
      $mark_link = !empty($mark_link) ? $mark_link : $this->createMarkLink(Utility::MENU_MAIN_NAME, $mark, $mark_node);

      // Level link.
      $level_link = $this->findLevelLink($mark_link, $mark, $area);
      $level_link = !empty($level_link) ? $level_link : $this->createLevelLink($mark_link, $mark, $area);

      // Area link.
      $new_link = [
        'menu_name' => Utility::MENU_MAIN_NAME,
        'title' => mb_strtoupper($area->getName()),
        'description' => Utility::createAreaTitle($mark, $area),
        'link' => Utility::getLinkUrl($node),
        'parent' => Utility::getParentUuid($level_link),
      ];
      $area_link = $this->createMenuLink($new_link);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function createMarkLink($menu_name, $mark, $node) {
    $new_link = [
      'title' => $mark->getName(),
      'menu_name' => $menu_name,
      'link' => Utility::getLinkUrl($node),
      'weight' => $mark->getWeight(),
      'description' => $mark->getName(),
    ];
    return $this->createMenuLink($new_link);
  }

  /**
   * {@inheritdoc}
   */
  public function createLevelLink($mark_link, $mark, $area = NULL) {
    $new_link = [
      'menu_name' => Utility::MENU_MAIN_NAME,
      'title' => !empty($area) ? Utility::MENU_AREA_PARENT : Utility::MENU_AUTO_PARENT,
      'description' => $mark->getName(),
      'link' => Utility::NEW_NO_LINK,
      'parent' => Utility::getParentUuid($mark_link),
      'weight' => !empty($area) ? Utility::MENU_AREA_PARENT_WEIGHT : Utility::MENU_AUTO_PARENT_WEIGHT,
    ];
    return $this->createMenuLink($new_link);
  }

  /**
   * {@inheritdoc}
   */
  public function findLevelLink($mark_link, $mark, $area = NULL) {
    $properties = [
      'parent' => Utility::getParentUuid($mark_link),
      'title' => empty($area) ? Utility::MENU_AUTO_PARENT : Utility::MENU_AREA_PARENT,
      'menu_name' => Utility::MENU_MAIN_NAME,
      'link' => Utility::NO_LINK,
    ];
    return $this->findOneByProperties($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink($link, $properties) {
    if (!empty($properties['title'])) $link->set('title', $properties['title']);
    if (!empty($properties['parent'])) $link->set('parent', $properties['parent']);
    if (!empty($properties['description'])) $link->set('description', $properties['description']);
    if (!empty($properties['field_auto'])) $link->set('field_auto', $properties['field_auto']);
    if (!empty($properties['field_service'])) $link->set('field_service', $properties['field_service']);
    $link->save();
    return $link;
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkUrl($node) {
    return 'entity:node/' . $node->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getParentUuid($link) {
    return 'menu_link_content:' . $link->uuid->value;
  }

  /**
   * {@inheritdoc}
   */
  public function handleServiceSuperParentLink($mark, $menu_name, $service) {
    $parent_fields = [
      'title' => $mark->getName(),
      'menu_name' => $menu_name,
      'link' => Utility::NO_LINK,
      // 'field_service' => 'notExists',
      'parent' => NULL,
    ];

    $super_parent_link = $this->findOneByFields($parent_fields);
    if (empty($super_parent_link)) {
      $super_parent_link = $this->createServiceSuperParentLink($mark, $menu_name, $service);
    }
    else {
      $super_parent_link->set('field_service', NULL);
      $super_parent_link->set('field_auto', $mark->id());
      $super_parent_link->save();
    }
    return $super_parent_link;
  }

  /**
   * {@inheritdoc}
   */
  public function createServiceSuperParentLink($mark, $menu_name, $service) {
    $data = [
      'title' => $mark->getName(),
      'link' => Utility::NEW_NO_LINK,
      'menu_name' => $menu_name,
      'expanded' => TRUE,
      'weight' => $mark->getWeight(),
      'description' => $mark->getName(),
      'field_auto' => $mark->id(),
      'field_service' => $service->id(),
    ];
    return $this->createMenuLink($data);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLinkItems($marka_item) {
    if (!empty($marka_item->subtree)) {
      foreach ($marka_item->subtree as $subtree_item) {
        $this->deleteLinkItems($subtree_item);
      }
    }
    $uuid = $marka_item->link->getDerivativeId();
    $menu_link = $this->entityRepository->loadEntityByUuid('menu_link_content', $uuid);
    $menu_link->delete();
  }

}
