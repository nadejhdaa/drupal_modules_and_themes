<?php

namespace Drupal\med_registry\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\med_mis\Utility\Utility;
use Drupal\med_mis\Client\MisClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Doctor fio autocomplete response.
 */
class DoctorFioAutocomplete extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly MisClient $misClient,
    private readonly Connection $connection,
    private readonly CacheBackendInterface $cacheBackend,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_client'),
      $container->get('database'),
      $container->get('cache.default'),
    );
  }

  /**
   * Return autocomplete matches.
   */
  public function autocomplete(Request $request) {
    // Получаем текущий запрос автокомплита.
    $string = $request->query->get('q');
    $from = $request->get('from');
    $to = $request->get('to');

    $matches = [];

    if (!empty($string)) {
      $string = Xss::filter($string);
      $string = mb_strtolower($string);

      if (!empty($to)) {
        $from = !empty($from) ? date('Y-m-d', strtotime($from)) : date('Y-m-d');
        $to = date('Y-m-d', strtotime($to));

        $result = $this->loadDoctorsByDateFio($string, $from, $to);

        if (!empty($result)) {
          foreach ($result as $value) {
            $matches[] = ['value' => $value, 'label' => $value];
          }
        }
      }

      else {
        $cid_params = ['all'];
        $cid = Utility::buildCidString('getServicesToAppoint', $cid_params);

        if ($cache = $this->cacheBackend->get($cid)) {
          $response = $cache->data;
        }
        else {
          $response = $this->misClient->getServicesToAppoint();
          $this->cacheBackend->set($cid, $response, (time() + sUtility::GET_SERVICES_TO_APPOINT_TIME));
        }

        if (!empty($response['filters'])) {
          foreach ($response['filters'] as $filter) {
            if ($filter['code'] == 'specialist') {
              foreach ($filter['data'] as $item) {
                $title = mb_strtolower($item['title']);

                if (!str_contains($title, $string)) {
                  continue;
                }

                $matches[] = ['value' => $item['title'], 'label' => $item['title']];
              }
            }
          }
        }
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * Load doctor node by fio.
   */
  public function loadDoctorsByDateFio($string, $from = '', $to = '') {
    $fio = [];

    $query = $this->connection->select('med_mis_doctors_days__data', 'dd');
    $query->innerJoin('med_mis_doctors_days', 'd', 'd.id = dd.entity_id');
    $query->innerJoin('node__field_doc_mis_code', 'mc', 'd.specialist = mc.field_doc_mis_code_value');
    $query->innerJoin('node_field_data', 'nfd', 'nfd.nid = mc.entity_id');
    $query->fields('nfd', ['title']);
    $query->condition('nfd.title', '%' . $this->connection->escapeLike($string) . '%', 'LIKE');

    if (!empty($from)) {
      $query->condition('dd.data_date', $from, '>=');
    }

    if (!empty($to)) {
      $query->condition('dd.data_date', $to, '<=');
    }

    $query->condition('nfd.status', 1);
    $result = $query->execute()->fetchAllAssoc('title');

    if (!empty($result)) {
      foreach ($result as $row) {
        $fio[$row->title] = $row->title;
      }
    }

    return $fio;
  }

}
