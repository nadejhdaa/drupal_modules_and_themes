<?php

declare(strict_types=1);

namespace Drupal\med_cart;

use Drupal\med_mis\Utility\Utility;
use Drupal\med_mis\Client\MisClient;
use Drupal\med_service\EncMisServiceHelper;

/**
 * Service manager to work with service from MIS.
 */
final class ServiceManager {

  /**
   * Constructs a SlotManager object.
   */
  public function __construct(
    private readonly MisClient $misClient,
    private readonly EncMisServiceHelper $medMisServiceHelper,
  ) {}

  /**
   * Add additional service slot (eg "blood analysis") for some services.
   */
  public function getAdditionalServiceSlot() {
    $additional_service = Utility::ADDITIONAL_SERVICE;

    $result = $this->misClient->getSlotsToAppoint([
      'service' => $additional_service,
    ]);

    if (!empty($result['slots'])) {
      $today = date('d.m.Y');

      foreach ($result['slots'] as $date_key => $slot) {

        if ($date_key > $today) {
          $date = $date_key;
          break;
        }
      }
    }

    if (!empty($result['slots'])) {

      $result = $this->misClient->getSlotsToAppoint([
        'service' => $additional_service,
        'date' => $date,
      ]);

      if (!empty($result['slots'][$date]['slot'])) {
        $slot = reset($result['slots'][$date]['slot']);
        return $slot;
      }
    }

    return NULL;
  }

}
