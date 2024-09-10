<?php

namespace Drupal\auto_content\Utility;

use Drupal\taxonomy\Entity\Term;

/**
 * Utility functions specific to menu_item_extras.
 */
class Utility {

  const VID_BRANDS = 'auto';
  const VID_SERVICES = 'services';
  const VID_AREA = 'area';
  const VID_PRICE_ACCORDION = 'price';
  const MENU_AUTO_PARENT = 'Марка + модель';
  const MENU_AUTO_PARENT_WEIGHT = 0;
  const MENU_AREA_PARENT = 'Марка + округ';
  const MENU_AREA_PARENT_WEIGHT = 1;
  const NO_LINK = 'route:<nolink>';
  const NEW_NO_LINK = ['uri' => 'route:<nolink>'];
  const MENU_MAIN_NAME = 'structure';
  const NODE_TYPE_INTERSECTION = 'intersection';
  const NODE_TYPE_CAR_SERVICE = 'car_service';
  const NODE_TYPE_YANDEX_REVIEW = 'yandex_review';
  const AUDI_TID = 26;
  const BLOCK_FRONT_TOP_TEXT_ID = 6;

  const FIELDS = [
    'field_address_string',
    'field_opening_hours',
    'field_phone',
    'field_priority',
    'field_remote_id',
    'field_reviews',
    'field_worker_fio',
    'field_worker_position',
    'field_worker_text',
  ];

  /**
   * Create string from term name.
   *
   * @param entity $auto
   *   Entity for checking.
   */
  public static function createMenuName($auto) {
    $parent = !empty($auto->parent->target_id) ? Term::load($auto->parent->target_id) : $auto;
    $str = str_replace(' ', '_', $parent->getName());
    return mb_strtolower($str);
  }

  /**
   * Create string from term name.
   *
   * @param entity $service
   *   Entity for checking.
   */
  public static function getMenuLinkTitle($service) {
    return !empty($service->field_title->value) ? $service->field_title->value : $service->getName();
  }

  /**
   * Create string from node url.
   *
   * @param string $link
   *   Entity for checking.
   */
  public static function getVidFromTermName($name) {
    return str_replace(' ', '_', mb_strtolower($name));
  }

  /**
   * Create string from terms name.
   *
   * @param entity $mark
   *   Entity for checking.
   * @param entity $area
   *   Entity for checking.
   */
  public static function createAreaTitle($mark, $area) {
    return 'Ремонт ' . $mark->getName() . ' в ' . mb_strtoupper($area->getName());
  }

  /**
   * Create string from node url.
   *
   * @param entity $node
   *   Entity for checking.
   */
  public static function getLinkUrl($node) {
    return 'entity:node/' . $node->id();
  }

  /**
   * Create string from node url.
   *
   * @param entity $link
   *   Entity for checking.
   */
  public static function getParentUuid($link) {
    return 'menu_link_content:' . $link->uuid->value;
  }
}
