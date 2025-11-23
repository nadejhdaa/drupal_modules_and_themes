<?php

declare(strict_types=1);

namespace Drupal\med_cart\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Controller\ControllerBase;

/**
 * Ajax links controller.
 */
final class AjaxLinkController extends ControllerBase {

  /**
   * Update slots time items.
   */
  public function selectTimeAjaxResponse($qqc244to, $time) {
    $time_exploded = explode('-', $time);
    $time_start = reset($time_exploded);

    $response = new AjaxResponse();

    $response->addCommand(new InvokeCommand('a.time-slot', 'removeClass', ['selected']));
    $response->addCommand(new InvokeCommand('a[data-slot="' . $qqc244to . '-' . $time_start . '"]', 'addClass', ['selected']));

    $response->addCommand(new InvokeCommand('input[name="qqc244to"]', 'val', [$qqc244to]));
    $response->addCommand(new InvokeCommand('input[name="time_display"]', 'val', [$time_start]));
    $response->addCommand(new InvokeCommand('input[name="time_display"]', 'trigger', ['change']));
    return $response;
  }

}
