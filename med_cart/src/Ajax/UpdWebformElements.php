<?php

namespace Drupal\med_cart\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Update webform elements custom ajac command functions.
 */
class UpdWebformElements implements CommandInterface {

  /**
   * Webform id.
   *
   * @var string
   */
  protected $webformId;

  /**
   * Constructs an AddJsCommand.
   *
   * @param string $webform_id
   *   A webform id.
   */
  public function __construct($webform_id) {
    $this->webformId = $webform_id;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'updWebform',
      'webform_id' => 'zayavka_na_priem',
    ];
  }

}
