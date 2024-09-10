<?php

declare(strict_types=1);

namespace Drupal\auto_import\Client;

/**
 * @todo Add interface description.
 */
interface ClientBaseInterface {

  /**
   * Scrf token path.
   */
  const TOKEN_PATH = '/session/token';

  /**
   * User login path.
   */
  const LOGIN_PATH = '/user/login';

  /**
   * User logout path.
   */
  const LOGOUT_PATH = '/user/logout';


}
