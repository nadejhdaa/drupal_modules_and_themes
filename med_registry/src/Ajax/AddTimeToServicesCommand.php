<?php

namespace Drupal\med_registry\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Call "AddTimeToServicesCommand" to selectors custom Ajax Callback.
 */
class AddTimeToServicesCommand implements CommandInterface {

  /**
   * The selector name.
   *
   * @var string
   */
  protected $selector;

  /**
   * The services array.
   *
   * @var array
   */
  protected $services;

  /**
   * Constructs an AddTimeToServicesCommand.
   *
   * @param string $selector
   *   Selector name.
   * @param array $services
   *   Services codes.
   */
  public function __construct(string $selector, array $services) {
    $this->selector = $selector;
    $this->services = $services;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'AddTimeToServicesCommand',
      'selector' => $this->selector,
      'services' => $this->services,

    ];
  }

}
