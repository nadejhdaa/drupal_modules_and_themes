<?php

declare(strict_types=1);

namespace Drupal\med_mis\Client;

/**
 * ClientBase interface.
 */
interface ClientBaseInterface {

  /**
   * Base path.
   */
  const BASE_PATH = '/csp/qms/rest';

  /**
   * Base url scheme.
   */
  const BASE_SCHEME = 'https';

  /**
   * Base user qqc153.
   */
  const DEFAULT_MIS_USER = 'ФABAed]';

  const MIS_REQUEST_TIMEOUT = 20;

  const MIS_REQUEST_CONNECT_TIMEOUT = 10;

  const DEFAULT_PAYMENT_SOURCE = '3 ФABaAAAAAA';

}
med
