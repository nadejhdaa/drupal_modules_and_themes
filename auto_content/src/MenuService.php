<?php

declare(strict_types=1);

namespace Drupal\auto_content;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Menu\MenuLinkManager;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\auto_import\TermService;
use Drupal\auto_content\IntersectionService;
use Drupal\auto_content\MenuLinkService;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\auto_content\Utility\Utility;

/**
 * @todo Add class description.
 */
final class MenuService {

  /**
   * Constructs a MenuService object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TermService $termService,
    private readonly IntersectionService $intersection,
    private readonly MenuLinkManager $menuLinkManager,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly MenuLinkService $menuLinkService,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function checkMenuItem($auto, $service = NULL, $area = NULL, $node = NULL) {
    // Take care about new mark menu.
    if (!empty($service)) {
      $mark = !empty($auto->parent->target_id) ? Term::load($auto->parent->target_id) : $auto;
      $menu = $this->handleMarkServicesMenu($mark);
    }
    $menu_link_content = $this->handleMenuLinkByTerms($auto, $service, $area, $node);
  }

  /**
   * {@inheritdoc}
   */
  public function handleMenuLinkByTerms($auto, $service = NULL, $area = NULL, $node = NULL) {
    if (!empty($node)) {
      $menu_link = $this->handleLinkWithNode($node, $auto, $service, $area);
    }
    else {
      $menu_link = $this->handleLinkWithoutNode($auto, $service);
    }
    return $menu_link;
  }

  /**
   * {@inheritdoc}
   */
  public function handleLinkWithoutNode($auto, $service) {
    $mark = !empty($auto->parent->target_id) ? Term::load($auto->parent->target_id) : $auto;
    $menu_name = Utility::createMenuName($mark);

    // Service.
    if (!empty($service)) {
      // Сначала главная Ссылка на марку.
      $super_parent_link = $this->menuLinkService->handleServiceSuperParentLink($mark, $menu_name, $service);
      // Если это родительский, то найти/создать его.
      if (empty($service->parent->target_id)) {
        $parent_link = $this->menuLinkService->handleServiceParentLink($super_parent_link, $mark, $service, $menu_name);
        return $parent_link;
      }
      // Если это младший, то найти/создать его.
      else {
        $parent_service = Term::load($service->parent->target_id);
        $parent_link = $this->menuLinkService->handleServiceParentLink($super_parent_link, $mark, $parent_service, $menu_name);
        $menu_link = $this->handleServiceLinkNoLink($mark, $service, $menu_name, $parent_link);
        return $menu_link;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handleServiceLinkNoLink($mark, $service, $menu_name, $parent_link) {
    $fields = [
      'menu_name' => $menu_name,
      'field_auto' => $mark->id(),
      'field_service' => $service->id(),
      'parent' => $this->menuLinkService->getParentUuid($parent_link),
    ];

    $menu_link = $this->menuLinkService->findOneByFields($fields);
    if (empty($menu_link)) {
      $menu_link = $this->createServiceLinkNoLink($mark, $service, $menu_name, $parent_link);
    }

    return $menu_link;
  }

  /**
   * {@inheritdoc}
   */
  public function createServiceLinkNoLink($mark, $service, $menu_name, $parent_link) {
    $new_link_data = [
      'title' => Utility::getMenuLinkTitle($service),
      'link' => Utility::NEW_NO_LINK,
      'menu_name' => $menu_name,
      'expanded' => TRUE,
      'weight' => $service->getWeight(),
      'description' => $service->getName(),
      'parent' => $this->menuLinkService->getParentUuid($parent_link),
      'field_auto' => $mark->id(),
      'field_service' => $service->id(),
    ];
    return $this->menuLinkService->createMenuLink($new_link_data);
  }

  /**
   * {@inheritdoc}
   */
  public function handleLinkWithNode($node, $auto, $service = NULL, $area = NULL) {
    $menu_link = $this->menuLinkService->findOneByNode($node);

    if (empty($menu_link)) {
      $menu_link = $this->menuLinkService->createMenuLinkForNode($node);
    }
    else {
      $menu_link = $this->updateMenuLink($menu_link, $auto, $service, $area);
    }
    return $menu_link;
  }

  /**
   * {@inheritdoc}
   */
  public function findByAutoAndService($mark, $service, $menu_name, $super_parent_link = NULL) {
    $properties = [
      'menu_name' => $menu_name,
      'field_service' => $service->id(),
      'field_auto' => $mark->id(),
    ];
    if (!empty($super_parent_link)) {
      $properties['parent'] = $this->menuLinkService->getParentUuid($super_parent_link);
    }
    $menu_link = $this->menuLinkService->findOneByProperties($properties);
    return $menu_link;
  }

  /**
   * {@inheritdoc}
   */
  public function updateMenuLink($menu_link, $auto, $service = NULL, $area = NULL) {
    if (!empty($auto->parent->target_id)) {
      $mark = Term::load($auto->parent->target_id);
      $model = $auto;
    }
    else {
      $mark = $auto;
    }

    // Auto.
    if (empty($service) && empty($area)) {
      $mark_node = $this->intersection->handleIntersection($mark);
      $mark_link = $this->menuLinkService->findOneByNode($mark_node);

      // Mark link.
      if (empty($mark_link)) {
        $mark_link = $this->menuLinkService->createMarkLink(Utility::MENU_MAIN, $mark, $mark_node);
      }
      else {
        $mark_link->set('weight', $mark->getWeight());
        $mark_link->set('parent', NULL);
        $mark_link->save();
      }

      // Level link.
      $level_link = $this->menuLinkService->findLevelLink($mark_link, $mark, $area);

      if (!empty($level_link)) {
        $level_link->set('parent', $this->menuLinkService->getParentUuid($mark_link));
        $level_link->save();
      }
      else {
        $level_link = $this->menuLinkService->createLevelLink($mark_link, $mark, $area);
      }

      // Если это модель.
      if (!empty($auto->parent->target_id)) {
        $model_node = $this->intersection->handleIntersection($auto);
        $model_link = $this->menuLinkService->findOneByNode($model_node);
        if (!empty($model_link)) {
          $model_link->set('weight', $auto->getWeight());
          $model_link->set('parent', $this->menuLinkService->getParentUuid($level_link));
          $model_link->save();
        }
        else {
          $new_link_data = [
            'menu_name' => Utility::MENU_MAIN,
            'title' => $auto->getName(),
            'weight' => $auto->getWeight(),
            'description' => $auto->getName(),
            'link' => 'entity:node/' . $model_node->id(),
            'parent' => $this->menuLinkService->getParentUuid($level_link),
          ];
          $model_link = $this->menuLinkService->createMenuLink($new_link_data);
        }
        return $model_link;
      }
      else {
        return $mark_link;
      }
    }

    // Area.
    if (!empty($area)) {
      $mark_node = $this->intersection->handleIntersection($mark);
      $mark_link = $this->menuLinkService->findOneByNode($mark_node);
      $level_link = $this->menuLinkService->findLevelLink($mark_link, $mark, $area);

      $menu_link->set('description', Utility::createAreaTitle($mark, $area));
      $menu_link->set('title', mb_strtoupper($area->getName()));
      $menu_link->set('weight', $area->getWeight());
      $menu_link->set('parent', $this->menuLinkService->getParentUuid($level_link));
      $menu_link->save();
      return $menu_link;
    }

    // Service.
    if (!empty($service)) {
      $menu_name = $menu_link->getMenuName();
      $super_parent_link = $this->menuLinkService->handleServiceSuperParentLink($mark, $menu_name, $service);

      if (empty($service->parent->target_id)) {
        $menu_link->set('parent', $this->menuLinkService->getParentUuid($super_parent_link));
      }
      else {
        $parent_service = Term::load($service->parent->target_id);
        $parent_link = $this->handleServiceLinkNoLink($mark, $parent_service, $menu_name, $super_parent_link);
        $menu_link->set('parent', $this->menuLinkService->getParentUuid($parent_link));
      }

      $menu_link->set('title', Utility::getMenuLinkTitle($service));
      $menu_link->set('description', $service->getName());
      $menu_link->set('weight', $service->getWeight());
      $menu_link->set('field_auto', $mark->id());
      $menu_link->set('field_service', $service->id());
      $menu_link->save();
      return $menu_link;
    }
    return NULL;
  }

  public function handleMarkServicesMenu($mark) {
    $menu_name = Utility::createMenuName($mark);
    $menu = $this->entityTypeManager->getStorage('menu')->load($menu_name);
    if (empty($menu)) {
      $menu = $this->createMarkServicesMenu($mark);
    }
    return $menu;
  }

  /**
   * {@inheritdoc}
   */
  public function createMarkServicesMenu($mark) {
    $menu_name = Utility::createMenuName($mark);
    $data = [
      'id' => $menu_name,
      'label' => $mark->getName(),
      'description' => $this->createDescription($mark),
    ];
    $menu = $this->entityTypeManager->getStorage('menu')->create($data);
    $menu->save();

    $fields = ['field_auto', 'field_service'];

    foreach ($fields as $field) {
      FieldConfig::create([
        'field_name' => $field,
        'entity_type' => 'menu_link_content',
        'bundle' => $menu_name,
        'label' => ucfirst(str_replace('field_', '', $field)),
      ])->save();

      // Manage form display
      $form_display = $this->entityDisplayRepository->getFormDisplay('menu_link_content', $menu_name);
      $form_display = $form_display->setComponent($field, ['type' => 'entity_reference_autocomplete']);
      $form_display->save();

      // Manage view display
      $view_display = $this->entityDisplayRepository->getViewDisplay('menu_link_content', $menu_name);
      $view_display->setComponent($field, ['type' => 'entity_reference_label']);
      $view_display->save();
    }
    return $menu;
  }


  /**
   * {@inheritdoc}
   */
  public function deleteMenu($menu_name) {
    $menu = $this->entityTypeManager->getStorage('menu')->load($menu_name);
    if (!empty($menu)) {
      $menu->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createDescription($auto) {
    return $auto->getName() . ' services menu';
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLinkItems($marka_item) {
    if (!empty($marka_item->subtree)) {
      foreach ($marka_item->subtree as $subtree_item) {
        $this->deleteLinkItems($subtree_item);
      }
      $marka_item->link->delete();
    }
  }

}
