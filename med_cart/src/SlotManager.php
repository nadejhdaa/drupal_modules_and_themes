<?php

declare(strict_types=1);

namespace Drupal\med_cart;

use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\med_cart\Utility\Utility;

/**
 * Class to work with slots in session for anonymous.
 */
final class SlotManager {

  const PU_DISTANT = [
    'Дистанционное_консультирование',
    'Телемедицина',
    'Заочная_консультация',
    'Первичные_ТМК',
  ];

  const PU_ENC = ['Консультации_в_ЛПУ'];

  /**
   * Constructs a SlotManager object.
   */
  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly SessionInterface $session,
  ) {}

  /**
   * Define slot fields.
   */
  public function getSlotFields() {
    return [
      'addToCart',
      'info',
      'pAz',
      'pu',
      'pu1',
      'puR',
      'room',
      'scheduleEnd',
      'scheduleStart',
      'title',
      'type1860',
      'time',
    ];
  }

  /**
   * Get slot address from "room" parameter in slot.
   */
  public function getSlotAddress($slot) {
    if (!$this->slotIsDistant($slot) && !empty($slot['room'])) {
      return Utility::getSlotAddress($slot['room']);
    }

    return '';
  }

  /**
   * Check if slot is distant.
   */
  public function slotIsDistant($slot) {
    $pu = $this->getPuArray($slot);
    $intersect = array_intersect(self::PU_DISTANT, $pu);

    return empty($intersect) ? FALSE : TRUE;
  }

  /**
   * Get "pu" values.
   */
  public function getPuArray($slot) {
    $pu = [];
    foreach ($slot as $key => $value) {
      if (strpos($key, 'pu') !== FALSE) {
        $pu[] = $value;
      }
    }
    return $pu;
  }

}
