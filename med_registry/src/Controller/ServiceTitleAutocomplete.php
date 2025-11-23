<?php

namespace Drupal\med_registry\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\med_mis\Utility\Utility;
use Drupal\med_mis\Client\Mimedient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Search service by title matches.
 */
class ServiceTitleAutocomplete extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly MisClient $misClient,
    private readonly CacheBackendInterface $cacheBackend,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_client'),
      $container->get('cache.default'),
    );
  }

  /**
   * Return service title matches.
   */
  public function autocomplete(Request $request) {
    // Получаем текущий запрос автокомплита.
    $string = $request->query->get('q');

    $matches = [];

    if (!empty($string)) {
      $string = Xss::filter($string);
      $string = mb_strtolower($string);

      $cid_params = ['all'];
      $cid = Utility::buildCidString('getServicesToAppoint', $cid_params);

      if ($cache = $this->cacheBackend->get($cid)) {
        $response = $cache->data;
      }
      else {
        $response = $this->misClient->getServicesToAppoint();
        $this->cacheBackend->set($cid, $response, (time() + sUtility::GET_SERVICES_TO_APPOINT_TIME));
      }

      if (!empty($response['services'])) {
        foreach ($response['services'] as $value) {
          $title = mb_strtolower($value['title']);

          if (!str_contains($title, $string)) {
            continue;
          }

          $matches[] = ['value' => $value['title'], 'label' => $value['title']];
        }
      }
    }

    return new JsonResponse($matches);
  }

}
