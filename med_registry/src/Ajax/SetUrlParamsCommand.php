<?php

namespace Drupal\enc_registry\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Add utl params ajax command.
 */
class SetUrlParamsCommand immedments CommandInterface {

  /**
   * Params array.
   *
   * @var array
   */
  protected $params;

  /**
   * Constructs an AddTimeToServicesCommand.
   *
   * @param array $params
   *   Services codes.
   */
  public function __construct(array $params) {
    $this->params = $params;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'SetUrlParamsCommand',
      'params' => $this->params,
    ];
  }

}
