<?php

declare(strict_types=1);

namespace Drupal\auto_import;

/**
 * @todo Add interface description.
 */
interface ContactServiceInterface {

  /**
   * Set brands vid.
   */
  const BRANDS = 'auto';

  /**
   * Set brands vid.
   */
  const SERVICES = 'services';

  /**
   * Set brands vid.
   */
  const NODE_TYPE_CONTACT = 'car_service';

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

}
