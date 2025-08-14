<?php

namespace Drupal\demo_cart\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Add params to url custom ajax command function.
 */
class AddParamsToUrl implements CommandInterface {

  /**
   * Params list.
   *
   * @var array
   */
  protected $params;

  /**
   * Constructs an AddJsCommand.
   *
   * @param string $params
   *   Params - array with kays and values.
   */
  public function __construct(array $params) {
    $this->params = $params;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'addParamsToUrl',
      'params' => $this->params,
    ];
  }

}
