<?php

declare(strict_types=1);

namespace Drupal\med_mis\Utility;

/**
 * Filter slots by pu.
 */
final class MisFilter {

  /**
   * Filter slots by type.
   *
   * @param array $slots
   *   Slots array.
   *
   * @return array
   *   Filtered slots array.
   */
  final public static function filterTkslots($slots) {
    $filters = \Drupal::config('med_mis.slot_filter_settings')->get('filters');

    foreach ($slots as $slot) {
      $pu = self::getPu($slot);

      foreach ($filters as $filter) {
        if ($pu == $filter['pu']) {
          $selected_filter = $filter;
          break;
        }
      }

      if (empty($selected_filter)) {
        foreach ($filters as $filter) {
          if (empty($filter['pu'])) {
            $selected_filter = $filter;
            break;
          }
        }
      }

      $filter = $selected_filter['filter'];

      if (count($filter) == 5) {
        $available = !isset($slot['tses'])
            ||
          in_array($slot['tses'], $filter)
            ||
          (isset($slot['tsesAdded']) && in_array($slot['tsesAdded'], $filter));
      }

      else {
        $available = !isset($slot['tses'])
            ||
          (
          in_array($slot['tses'], $filter)
            ||
          (isset($slot['tsesAdded']) && in_array($slot['tsesAdded'], $filter))
        );
      }

      if (!$available) {
        unset($slots[$i]);
      }
    }

    return $slots;
  }

  /**
   * Get "pu" from slot.
   *
   * @param array $slot
   *   Slot data.
   *
   * @return string
   *   String with pu of slot.
   */
  final public static function getPu($slot) {
    $pus = [];

    if (!empty($slot)) {
      foreach ($slot as $key => $value) {
        if ($key == 'pu' || (substr($key, 0, 2) == 'pu' && is_numeric(substr($key, 2, 1)))) {
          $pus[] = $value;
        }
      }
    }

    return implode(' ', $pus);
  }

}
