<?php

namespace Drupal\med_mis\Utility;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class to store useful constant variables and methods.
 */
class Utility {

  use StringTranslationTrait;

  const RUB = '₽';

  const DEFAULT_PAYMENT_SOURCE = '3 ФABaAAAAAA';

  // Время хранения слотов врача в кэше.
  const DOC_SLOTS_CACHE_TIME = 60 * 2;

  // Время хранения дней врача в кэше.
  const DOC_DAYS_CACHE_TIME = 60 * 60 * 6;

  // Время хранения корзины пользователя в кэше.
  const CART_CACHE_TIME = 60 * 60 * 6;

  const USER_AGREEMENT_CACHE_TIME = 60 * 60 * 48;

  const GET_SERVICES_TO_APPOINT_TIME = 60 * 60 * 6;

  const TERMS_OF_USE_NID = 32101;

  const AGREEMENT_NID = 32102;

  const PREPARE_NID = 39975;

  const PREPARE_NID_LK = 45203;

  const USER_NO_CONTRACT = 'The user does not have a contract';

  const MAIN_DOMAIN = 'endocrincentr_ru';

  const WEBFORM_ORDER = 'zayavka_na_priem';

  const ADDRESS_AKADEM = 'Moscow \n Dmitry Ulyanov St., 11 \n Metro Akademicheskaya';

  const ADDRESS_KASHIRSKAYA = 'Moscow \n Moskvorechye St., 1 \n Kashirskaya Metro Station';

  const NEED_ADDITIONAL_SERVICE_SPECIALIST_FIO = 'Онлайн-запись на анализы';

  const NEED_ADDITIONAL_SERVICE_SPECIALIST_CODE = ['ФABdAABAbAA'];

  const NEED_ADDITIONAL_SERVICE_SERVICES = ['ФABAAAAIACAAX', 'ФABAAAAIACAAc', 'ФABAABAABFADO'];

  // Additional service code "Дистанционная консультация...".
  const ADDITIONAL_SERVICE = 'ФABAABAAAWAAL';

  const CART_MSG = 'There are unordered services in the cart';

  const APP_MSG = 'Attention! All appointments created through the Personal Account must be paid within 20 minutes, otherwise they will be cancelled.';

  // Alternativr messages.
  const ENC_PHONE = '+7 495 500-00-90';

  /**
   * Get first ENC address.
   */
  final public static function getAddress1() {
    return nl2br(t('Moscow \n Dmitry Ulyanov St., 11 \n Metro Akademicheskaya'));
  }

  /**
   * Get second ENC address.
   */
  final public static function getAddress2() {
    return nl2br(t('Moscow \n Moskvorechye St., 1 \n Kashirskaya Metro Station'));
  }

  /**
   * Build cache name.
   *
   * @param string $function_name
   *   Name of the function to store for.
   * @param array $params
   *   Array fo params.
   */
  final public static function buildCidString($function_name, $params) {
    $cid = '';

    foreach ($params as $value) {
      if (is_string($value)) {
        $cid_parts[] = base64_encode($value);
      }
    }

    $cid = $function_name . implode('_', $cid_parts);

    return $cid;
  }

  /**
   * Check if current domain is LK subdomain.
   */
  final public static function isLkDomain() {
    $active_domain = \Drupal::service('domain.negotiator')->getActiveId();
    // If is LK check path to avoid looped redirect.
    return $active_domain !== self::MAIN_DOMAIN ?: FALSE;
  }

  /**
   * Load main domain entity.
   */
  final public static function getMainDomain() {
    return \Drupal::entityTypeManager()->getStorage('domain')->loadDefaultDomain();
  }

  /**
   * Load lk domain entity.
   */
  final public static function getLkDomain() {
    $all_domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultipleSorted();

    if (!empty($all_domains)) {
      return end($all_domains);
    }
  }

  /**
   * Load "zayavka_na_priem" webform.
   */
  public static function getWebform() {
    $storage = \Drupal::entityTypeManager()->getStorage('webform');
    $webforms = $storage->loadByProperties(['id' => self::WEBFORM_ORDER]);

    if (!empty($webforms)) {
      return reset($webforms);
    }

    return FALSE;
  }

  /**
   * Check if date/now is holiday.
   */
  public static function isHoliday($date = '') {
    $time = !empty($date) ? strtotime($date) : time();

    $holidays = \Drupal::state()->get('med_holidays', []);
    if (empty($holidays)) {
      $holidays = _med_mis_get_holdays();
    }

    if (!empty($holidays)) {
      return in_array(date('Y-m-d', $time), $holidays);
    }

    return date('w', $time) > 5;
  }

  /**
   * Check to hide/show callback link.
   */
  public static function hideCallbackLink($time = '') {
    $time = !empty($time) ? $time : time();
    $time += 60 * 60 * 2;
    $date = date('Y-m-d', $time);dsm($date);
    return self::isHoliday($date);
  }

}
