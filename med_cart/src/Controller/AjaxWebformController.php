<?php

declare(strict_types=1);

namespace Drupal\med_cart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\med_cart\Ajax\UpdWebformElements;

/**
 * Returns responses for Enc cart routes.
 */
final class AjaxWebformController extends ControllerBase {
  const WEBFORM_ID = 'zayavka_na_priem';

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

    $dialog_options = [
      'width' => '1296',
      'dialogClass' => 'modal-dialog--zayavka-na-priem',
    ];

    $response->addCommand(new OpenModalDialogCommand('', $content, $dialog_options));
    $response->addCommand(new UpdWebformElements('setWebformValues', ['webform_id' => 'zayavka_na_priem']));

    return $response;

  }

}
