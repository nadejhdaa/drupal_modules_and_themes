<?php

declare(strict_types=1);

namespace Drupal\demo_cart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\demo_cart\Ajax\UpdWebformElements;

/**
 * Returns responses for Enc cart routes.
 */
final class AjaxWebformController extends ControllerBase {
  const WEBFORM_ID = 'test_id';

  /**
   * Builds the response.
   */
  public function loadRequestForm() {
    $response = new AjaxResponse();

    $persist = FALSE;
    $response->addCommand(new CloseModalDialogCommand($persist));

    $content = [
      '#type' => 'webform',
      '#webform' => self::WEBFORM_ID,
      '#default_data' => [],
    ];

    $response->addCommand(new OpenModalDialogCommand('', $content, ['width' => '753']));
    $response->addCommand(new UpdWebformElements('setWebformValues', ['webform_id' => self::WEBFORM_ID]));

    return $response;

  }

}
