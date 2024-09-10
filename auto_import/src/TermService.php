<?php

declare(strict_types=1);

namespace Drupal\auto_import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\auto_content\Utility\Utility;

/**
 * @todo Add class description.
 */
final class TermService {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * Constructs a TermService object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Build entityQuery.
   *
   * @return Drupal\Core\Batch\BatchBuilder;
   * Objects with data of batch object.
   */
  public function entityQuery($vid = NULL) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->accessCheck(TRUE);
    if (!empty($vid)) {
      $query->condition('vid', $vid);
    }
    return $query;
  }

  /**
   * @todo Add method description.
   */
  public function storage() {
    return $this->entityTypeManager->getStorage('taxonomy_term');
  }

  /**
   * @todo Add method description.
   */
  public function getTid($term) {
    return !empty($term->tid->value) ? $term->tid->value : $term->id();
  }

  /**
   * @todo Add method description.
   */
  public function getParentId($term) {
    if (!empty($term->parents)) {
      return reset($term->parents) == '0' ? NULL : reset($term->parents);
    }
    if ($term->parent) {
      return $term->parent->target_id == '0' ? NULL : $term->parent->target_id;
    }
    return NULL;
  }

  /**
   * @todo Add method description.
   */
  public function getBrands() {
    $brands = [];
    $manager = $this->entityTypeManager->getStorage('taxonomy_term');
    $vid = Utility::VID_BRANDS;
    $brands = $manager->loadTree($vid, 0, 1, TRUE);
    return $brands;
  }

  /**
   * @todo Add method description.
   */
  public function getBrandsOptions() {
    $options = [];
    $brands = $this->getBrands();
    foreach ($brands as $brand) {
      $options[$brand->getName()] = $brand->getName();
    }
    return $options;
  }

  /**
   * @todo Add method description.
   */
  public function getTids($names, $vid) {
    $parent = $vid == 'auto' ? 0 : NULL;
    $tids = $this->findTidsByName($names, $vid, $parent);

    if ($vid == 'auto' && !empty($tids)) {
      foreach ($tids as $tid) {
        $chidren = $this->getChildrenTids($tid, $vid);
        $tids = array_merge($tids, $chidren);
      }
    }
    return $tids;
  }

  /**
   * @todo Add method description.
   */
  public function getTermHierarchy($vid) {
    $terms = $this->storage()->loadTree($vid, 0, NULL, FALSE);
    foreach ($terms as $term) {
      if ($term->depth == 0) {
        $parents[$term->tid] = $term->name;
      }
    }
    foreach ($terms as $term) {
      if ($term->depth == 0) {
        $hierarchy[$term->name]['name'] = $term->name;
        $hierarchy[$term->name]['tid'] = $term->tid;
      }
      else {
        $parent_tid = reset($term->parents);
        if (!empty($parents[$parent_tid])) {
          $parent_name = $parents[$parent_tid];

          $hierarchy[$parent_name]['children'][$term->name] = [
            'name' => $term->name,
            'tid' => $term->tid,
          ];
        }
      }
    }
    return $hierarchy;
  }

  /**
   * @todo Add method description.
   */
  public function getServicesTids($items, $vid) {
    $hierarchy = $this->getTermHierarchy($vid);
    foreach ($items as $num => $item) {
      // If it`s parent term.
      if ($item['parent'] == 0) {
        if (!empty($hierarchy[$item['name']])) {
          $tids[] = $hierarchy[$item['name']]['tid'];
        }
        else {
          $weight = !empty($item['weight']) ? $item['weight'] : NULL;
          $new_parent_service = $this->createTerm($item['name'], 0, $vid, $weight);
          $tids[] = $new_parent_service->id();
          $hierarchy[$new_parent_service->getName()] = [
            'name' => $new_parent_service->getName(),
            'tid' => $new_parent_service->id(),
            'children' => [],
          ];
        }
      }
      // If it`s children term.
      else {
        $parent_name = $item['parent'];
        // If parent term exists.
        if (!empty($hierarchy[$parent_name])) {
          if (!empty($hierarchy[$parent_name]['children'][$item['name']])) {
            $tids[] = $hierarchy[$parent_name]['children'][$item['name']]['tid'];
          }
          else {
            $new_service_parent_tid = $hierarchy[$parent_name]['tid'];
            $weight = !empty($item['weight']) ? $item['weight'] : NULL;
            $new_service = $this->createTerm($item['name'], $new_service_parent_tid, $vid, $weight);
            $tids[] = $new_service->id();
          }
        }
        // If parent term not exists need to create it.
        else {
          $weight = 0;
          foreach ($items as $search_parent_item) {
            if ($search_parent_item['name'] == $item['parent']) {
              $weight = $search_parent_item['weight'];
            }
          }
          $new_parent_service = $this->createTerm($item['parent'], 0, $vid, $weight);
          $tids[] = $new_parent_service->id();

          $exists_service_tids = $this->findTidsByName($name, $vid, $new_parent_service->id());
          if (!empty($exists_service_tids)) {
            $tids[] = reset($exists_service_tids);
          }
          else {
            $weight = !empty($item['weight']) ? $item['weight'] : NULL;
            $new_service = $this->createTerm($item['name'], $new_parent_service->id(), $vid, $weight);
            $tids[] = $new_service->id();
          }
        }
      }
    }
    return $tids;
  }

  /**
   * @todo Add method description.
   */
  public function createTerm($name, $parent, $vid, $weight) {
    $term_data = [
      'vid' => $vid,
      'name' => $name,
      'parent' => !empty($parent) ? $parent : 0,
    ];
    if (!empty($weight)) {
      $term_data['weight'] = $weight;
    }
    $new_term = Term::create($term_data);
    $new_term->enforceIsNew();
    $new_term->save();
    return $new_term;
  }

  /**
   * @todo Add method description.
   */
  public function findTidsByName($name, $vid, $parent = NULL) {
    $query = $this->entityQuery();
    $query->condition('vid', $vid);
    if (is_array($name)) {
      $query->condition('name', $name, 'IN');
    }
    else {
      $query->condition('name', $name);
    }

    if (!empty($parent)) {
      $query->condition('parent', $parent);
    }

    $tids = $query->execute();
    return $tids;
  }

  /**
   * @todo Add method description.
   */
  public function getChildrenTids($parent, $vid) {
    $query = $this->entityQuery();
    $query->condition('parent', $parent);
    $query->condition('vid', $vid);
    $tids = $query->execute();
    return $tids;
  }

  /**
   * Method description.
   */
  public function getParent($term) {
    $parent_id = $this->getParentId($term);
    return !empty($parent_id) ? Term::load($parent_id) : NULL;
  }

  /**
   * Method description.
   */
  public function getTree($vid) {
    $manager = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $manager->loadTree($vid, 0, NULL, TRUE);
    $tree = [];

    foreach ($terms as $key => $term) {
      if (empty($term->parent->target_id)) {
        $tree[$term->id()]['term'] = $term;
      }
      else {
        $tree[$term->parent->target_id]['children'][$term->id()] = $term;
      }
    }
    return $tree;
  }
}
