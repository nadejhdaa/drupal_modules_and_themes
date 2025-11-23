<?php

declare(strict_types=1);

namespace Drupal\med_mis;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\med_mis\Client\MisClient;
use Drupal\med_mis\Utility\Utility;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\med_mis\Utility\MisFilter;

/**
 * Get data from MIS.
 */
final class MisData {

  /**
   * Constructs a MisData object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly CacheBackendInterface $cacheBackend,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly MessengerInterface $messenger,
    private readonly RendererInterface $renderer,
    private readonly MisClient $misClient,
    private readonly SessionInterface $session,
  ) {}

  /**
   * Set debug to MIS queries.
   */
  public function setDebug($on = FALSE) {
    $this->misClient->setDebug($on);
  }

  /**
   * Мы храним кэш расписания специалиста по 6 часов.
   */
  public function getAllDoctorDaysCached($specialist) {
    $doctor_days = [];

    $params['specialist'] = $specialist;
    $cid = Utility::buildCidString('doctor_days', $params);

    // Если данные есть в кэше.
    if ($cache = $this->cacheBackend->get($cid)) {
      $doctor_days = $cache->data;
    }
    // Если нет, то получаем их из МИС.
    else {
      $doctor_days = $this->getAllDoctorDays($specialist);
      $this->cacheBackend->set($cid, $doctor_days, (time() + Utility::DOC_DAYS_CACHE_TIME));
    }

    return $doctor_days;
  }

  /**
   * Получим все дни приема врача из МИС.
   */
  public function getAllDoctorDays($specialist) {
    $doctor_days = [];
    $params['specialist'] = $specialist;

    // Получить услуги специалиста.
    $result = $this->misClient->getServicesToAppoint($params);

    if (!empty($result['services'])) {
      $params['dashboard'] = 1;

      foreach ($result['services'] as $service_data) {
        $service = $service_data['value'];
        $doctor_days[$service] = [];

        $params['service'] = $service;

        // Запрос "getSlotsToAppoint". Получить дни по каждой услуге.
        $result = $this->misClient->getSlotsToAppoint($params);

        if (!empty($result['specialist'])) {
          $reset = reset($result['specialist']);
          $doctor_days[$service] = !empty($reset['slots']) ? array_keys($reset['slots']) : [];
        }
      }med
    }

    return $doctor_days;
  }

  /**
   * Получить и закешировать слоты по услуге и дате.
   */
  public function getSlotsByDate($service, $specialist, $date) {
    $slots = [];

    $params['service'] = $service;
    $params['specialist'] = $specialist;

    $params['date'] = date('Ymd', strtotime($date));

    $cid = Utility::buildCidString('slots_by_date', $params);

    if ($cache = $this->cacheBackend->get($cid)) {
      $slots = $cache->data;
    }

    else {
      $response = $this->misClient->getSlotsToAppoint($params);

      if (!empty($response['slots'])) {
        $response_date_format = date('d.m.Y', strtotime($date));

        if (!empty($response['slots'][$response_date_format]['slot'])) {
          $slots = $response['slots'][$response_date_format]['slot'];
          $expiration = time() + Utility::DOC_SLOTS_CACHE_TIME;
          $this->cacheBackend->set($cid, $slots, $expiration);
        }
      }
    }

    return $slots;
  }

  /**
   * Get doctor slots by service.
   */
  public function getDoctorDaysByService($service, $specialist = '') {
    $params['service'] = $service;
    if (!empty($specialist)) {
      $params['specialist'] = $specialist;
    }

    $days = [];
    $cid = Utility::buildCidString(__FUNCTION__, $params);

    if ($cache = $this->cacheBackend->get($cid)) {
      $days = $cache->data;
    }
    else {
      $params['dashboard'] = 1;

      $data = $this->misClient->getSlotsToAppoint($params);

      if (!empty($data['specialist'])) {
        $data_specialist = reset($data['specialist']);

        if (!empty($data_specialist['slots'])) {

          $current_month_year = date('m.Y');
          foreach ($data_specialist['slots'] as $date => $slots) {
            if (substr($date, 3, 7) == $current_month_year) {
              $day_params = $params;
              $day_params['date'] = date('Ymd', strtotime($date));
              unset($day_params['dashboard']);

              $slots = $this->getDoctorSlots($day_params);
              if (!empty($slots)) {
                $days[] = date('Y-m-d', strtotime($date));
              }
            }
            else {
              $days[] = date('Y-m-d', strtotime($date));
            }
          }
        }
      }

      sort($days);

      $expiration = time() + (60 * 60 * 6);
      $this->cacheBackend->set($cid, $days, $expiration);
    }

    return $days;
  }

  /**
   * Get doctor slots from MIS/cache.
   */
  public function getDoctorSlots($params) {
    $slots = [];

    $cid = Utility::buildCidString(__FUNCTION__, $params);

    if ($cache = $this->cacheBackend->get($cid)) {
      $slots = $cache->data;
    }
    else {

      $result = $this->misClient->getSlotsToAppoint($params);

      if (!empty($params['date'])) {
        $date = date('d.m.Y', strtotime($params['date']));

        if (!empty($result['slots']) && !empty($result['slots'][$date]['slot'])) {
          $slots = $result['slots'][$date]['slot'];
          // Filter slots.
          $slots = MisFilter::filterTkslots($slots);
        }

        $expiration = time() + Utility::DOC_SLOTS_CACHE_TIME;
        $this->cacheBackend->set($cid, $slots, $expiration);
      }
    }
    return $slots;
  }

  /**
   * Get doctor slots by date.
   */
  public function getDoctorSlotsByDate($params) {
    $slots = $this->getDoctorSlots($params);
    $slots_by_time = [];
    foreach ($slots as $key => $slot) {
      if (!empty($slot['time'])) {
        $time = explode('-', $slot['time']);
        $start_time = reset($time);
        $slots_by_time[$start_time] = $slot;
      }
      else {
        $slots_by_time[$key] = $slot;
      }
    }
    return $slots_by_time;
  }

}
