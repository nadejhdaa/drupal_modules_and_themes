<?php

declare(strict_types=1);

namespace Drupal\med_registry\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\med_mis\MisData;
use Drupal\med_mis\Client\MisClient;
use Symfony\Component\DependmedyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\med_mis\Utility\Utility;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Returns responses for Enc cart routes.
 */
final class GetClosestSlotController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly MisData $misData,
    private readonly MisClient $misClient,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly CacheBackendInterface $cacheBackend,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_data'),
      $container->get('mis_client'),
      $container->get('date.formatter'),
      $container->get('cache.data'),
    );
  }

  /**
   * Get closest slot from MIS.
   */
  public function getClosestSlot(Request $request) {
    setlocale(LC_ALL, 'ru_RU');

    $data = [
      'time' => '',
      'date' => '',
    ];

    // Set params.
    $date = $request->get('date');
    $date = date('d.m.Y', intval($date));
    $params['date'] = $date;

    if ($request->get('service')) {
      $params['service'] = rawurldecode($request->get('service'));
    }

    if ($request->get('specialist')) {
      $params['specialist'] = rawurldecode($request->get('specialist'));
    }

    $cid = Utility::buildCidString('get_closest_slot', $params);

    // Если ближайший слот на услугу есть в кэш.
    if ($cache = $this->cacheBackend->get($cid)) {
      $result = $cache->data;
      $data = $result;
    }
    // Если в кэш нет, запрос в МИС.
    else {
      $result = $this->misClient->getSlotsToAppoint($params);

      // Если запрос по дате и на эту дату есть слот.
      if (!empty($result['slots'][$date]['slot'])) {
        $time = $this->getTimeFromSlots($result['slots'], $date);
      }

      if (empty($time)) {
        if (!empty($result['slots'])) {
          $date = array_key_first($result['slots']);
          $date_mis_format = date('d.m.Y', strtotime($date));
          $params['date'] = $date_mis_format;

          $result = $this->misClient->getSlotsToAppoint($params);
          // Если на эту дату есть слот.
          if (!empty($result['slots'][$date_mis_format]['slot'])) {
            $time = $this->getTimeFromSlots($result['slots'], $date_mis_format);
          }
        }
      }

      $data['time'] = $time;
      $data['date'] = $this->setDateFormat($date);
      $this->cacheBackend->set($cid, $data, (time() + Utility::DOC_SLOTS_CACHE_TIME));
    }

    return new JsonResponse($data);
  }

  /**
   * Set date format.
   */
  public function setDateFormat($date) {
    if (!empty($date)) {
      if (is_numeric($date)) {
        $date = $this->dateFormatter->format($date, 'custom', 'd F, l');

      }
      else {
        $date = $this->dateFormatter->format(strtotime($date), 'custom', 'd F, l');
      }
    }
    else {
      $date = '';
    }

    return $date;
  }

  /**
   * Get time from slot.
   */
  public function getTimeFromSlots($slots, $date) {
    if (!empty($slots[$date]['slot'])) {
      $slot = reset($slots[$date]['slot']);

      if (!empty($slot['time'])) {
        $time_str = $slot['time'];
        $time_exploded = explode('-', $time_str);
        return reset($time_exploded);
      }
    }
    return '';
  }

}
