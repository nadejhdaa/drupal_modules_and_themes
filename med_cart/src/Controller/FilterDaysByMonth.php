<?php

declare(strict_types=1);

namespace Drupal\med_cart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\med_mis\MisData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Enc cart routes.
 */
final class FilterDaysByMonth extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly MisData $misData,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_data'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(Request $request, $specialist, $service) {
    $month_days = $request->get('month_days');
    if (!empty($month_days)) {
      foreach ($month_days as $key => $month_day) {
        $day_params = [
          'specialist' => $specialist,
          'service' => $service,
          'date' => date('Ymd', strtotime($month_day)),
        ];
        $slots = $this->misData->getDoctorSlots($day_params);
        if (count($slots) == 0) {
          unset($month_days[$key]);
        }
      }
    }

    return new JsonResponse([
      'month_days' => $month_days,
      'specialist' => $specialist,
      'status' => 200,
    ]);

  }

}
