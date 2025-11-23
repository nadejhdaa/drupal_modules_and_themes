<?php

namespace Drupal\med_cart\Utility;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Link;

/**
 * Class to store useful constant variables and methods.
 */
class Utility {


  /**
   * Get slot address from id.
   */
  public static function getSlotAddress($room, $html = FALSE) {
    foreach (self::ENC_ADDRESS as $id => $address) {
      if (strpos($room, $id) !== FALSE) {
        return $html ? $address : str_replace('<br>', ', ', $address);
      }
    }
    return '';
  }

  /**
   * Build empty cart message markup.
   */
  public static function buildEmptyCartMarkup() {
    $options = [
      'attributes' => [
        'class' => ['button'],
      ],
    ];

    $registry_link = Link::createFromRoute(t('Go to the selection of services for appointment'), 'med_lk.user_registry', [], $options);
    $registry_link = $registry_link->toString();

    return new TranslatableMarkup('The cart is empty. @registry_link', [
      '@registry_link' => $registry_link,
    ]);
  }

}
