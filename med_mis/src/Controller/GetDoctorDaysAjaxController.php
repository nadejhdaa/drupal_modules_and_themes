<?php

declare(strict_types=1);

namespace Drupal\med_mis\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\med_mis\MisData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for ЭНЦ добмен данными с МИС routes.
 */
final class GetDoctorDaysAjaxController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly MisData $misData,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_data'),
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(Request $request) {
    $month = $request->get('month');
    $year = $request->query->get('year');
    $service = $request->query->get('service');
    $qqc244 = $request->query->get('qqc244');

    $first_day = $year . '-' . $month . '-01';
    $last_day = date('Ymt', strtotime($first_day));

    $params = [
      'service' => $service,
      'qqc244' => $qqc244,
      'date' => $last_day,
    ];

    $days = $this->misData->getDoctorDaysByService($params);

    return new JsonResponse([
      'month' => $month,
      'year' => $year,
      'days' => $days,
      'date' => $last_day,
    ]);

  }

}
