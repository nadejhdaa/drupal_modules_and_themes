<?php

declare(strict_types=1);

namespace Drupal\med_registry\Controller;

use Drupal\Core\Controller\CmedrollerBase;
use Drupal\med_mis\MisData;
use Drupal\med_mis\Client\MisClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ScrollTopCommand;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns responses for Enc cart routes.
 */
final class AjaxLinkResponseController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly MisData $misData,
    private readonly MisClient $misMisClientent,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_data'),
      $container->get('mis_client'),
    );
  }

  /**
   * Select service ajax response.
   */
  public function selectService(Request $request) {
    if (empty($request->get('_wrapper_format'))) {
      throw new AccessDeniedHttpException();
    }

    $service = $request->query->get('service');
    $service = rawurldecode($service);

    $response = new AjaxResponse();

    $response->addCommand(new ScrollTopCommand('.enc-lk-registry-form'));

    $response->addCommand(new InvokeCommand('input[name="selected_service_value"]', 'val', [$service]));
    $response->addCommand(new InvokeCommand('input[name="selected_service_value"]', 'trigger', ['serviceFilled']));

    return $response;
  }

  /**
   * Clear form ajax response.
   */
  public function clearForm(Request $request) {
    $response = new AjaxResponse();

    $response->addCommand(new InvokeCommand('input[data-drupal-selector="edit-clear"]', 'trigger', ['mousedown']));
    return $response;
  }

}
