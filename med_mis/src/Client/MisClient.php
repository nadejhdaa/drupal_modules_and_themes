<?php

declare(strict_types=1);

namespace Drupal\med_mis\Client;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\med_mis\Client\ClientBase; // phpcs ignore.
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Client to get data from MIS.
 */
final class MisClient {

  /**
   * The client debuf status.
   *
   * @var bool
   */
  protected $debugStatus;

  /**
   * Constructs a MisClient object.
   */
  public function __construct(
    private readonly SessionInterface $session,
    private readonly AccountProxyInterface $currentUser,
    private readonly ClientBase $clientBase,
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * Set debug on/off.
   */
  public function setDebug($on = FALSE) {
    $this->debugStatus = $on;
  }

  /**
   * Get auth/getAuthFields.
   *
   * Получить список полей для регистрации.
   *
   * @return array
   *   Auth fields.
   */
  public function getAuthFields() {
    $method = 'auth/getAuthFields';
    return $this->postRequest($method, $data = []);
  }

  /**
   * Get auth/userVerification.
   *
   * Авторизация пользователя.
   *
   * @return string
   *   Method name.
   */
  public function userVerification() {
    $method = 'auth/userVerification';
    return $this->postRequest($method, $data = []);
  }

  /**
   * Метод "getOKMUInfo".
   *
   * Возвращает данные по услугам организации с Mbu=0.
   *
   * @return array
   *   Response.
   */
  public function getOKMUInfo() {
    return $this->postRequest(__FUNCTION__, []);
  }

  /**
   * Метод "getServicesToAppoint".
   *
   * Список услуг для записи на приём.
   *
   * необязательные:
   *  paymentSource - источник финансирования
   *  specialist - код специалиста, на которого делается назначение
   *  category - категория услуги
   *
   * @return array
   *   Response.
   */
  public function getServicesToAppoint($params = []) {
    $params['paymentSource'] = $this->clientBase::DEFAULT_PAYMENT_SOURCE;
    $response = $this->postRequest(__FUNCTION__, $params);

    return $response;
  }

  /**
   * Метод "getSlotsToAppoint".
   *
   * Список услуг для записи на приём.
   *
   * обязательные:
   *  service - код услуги
   *  qqc244 - код специалиста от которого делается назначение
   *  qqc153 - код пациента.
   *
   * необязательные:
   *  paymentSource - источник финансирования
   *  specialist - код специалиста, на которого делается назначение
   *  date - дата, под которую получаем слоты
   *  dashboard - 1
   *
   * @return array
   *   Resturn response.
   */
  public function getSlotsToAppoint($params, $qqc153 = '') {
    $params['paymentSource'] = $this->clientBase::DEFAULT_PAYMENT_SOURCE;
    $response = $this->postRequest(__FUNCTION__, $params, $qqc153);
    return $response;
  }

  /**
   * Метод "verifyAddToCart".
   *
   * Проверка возможности добавления метода в корзину.
   *
   * обязательные:
   *  slot - объект слота расписания
   *  qqc153 - код пациента.
   *
   * @return bool
   *   True/false.
   */
  public function verifyAddToCart($slot) {
    $data = [
      'slot' => $slot,
    ];
    $response = $this->postRequest(__FUNCTION__, $data);

    return $response;
  }

  /**
   * Метод "addToCart".
   *
   * Поместить слот расписания(вся информация, нужная для назначения) в корзину.
   *
   * user - строка «login hash»
   * slot - объект слота расписания
   * qqc153 - код пациента. (только для авторизованного пользователя)
   */
  public function addToCart($slot) {
    $data = [
      'slot' => $slot,
      'user' => $this->currentUser->id(),
    ];

    $response = $this->postRequest(__FUNCTION__, $data);

    return $response;
  }

  /**
   * Метод "appointCart".
   *
   * Назначить коллекцию услуг(экземпляры объекта «slots») пациенту.
   * При успешном назначении метод вернет обратно объект «slots»,
   * экземпляры которого будут содержать новое поле -
   * коды получившихся назначений («qqc1860»).
   *
   * qqc153 - код пациента
   * объект slots - слоты расписания, и необходимые
   * для выполнения назначения параметры
   */
  public function appointCart($slots) {
    $data = [
      'slots' => $slots,
      'sendSample' => TRUE,
      'user' => $this->currentUser->id(),
    ];

    $response = $this->postRequest(__FUNCTION__, $data);
    return $response;
  }

  /**
   * Метод "findPatient".
   *
   * Поиск/создание пациента.
   *
   * RequiredParameters - {«параметр»:«значение»} -
   * поля для поиска/создания пациента
   * 'polOMS' - номер полиса ОМС «polOMS»:«1234567812345678»
   * 'pT' - номер мобильного телефона «pT»:«89998887766»
   * 'pF' - фамилия
   * 'pG' - имя
   * 'pH' - отчество
   */
  public function findPatient($params) {
    $response = $this->postRequest(__FUNCTION__, $params);

    return $response;
  }

  /**
   * Метод "get1860".
   *
   * Назначенные пациенту услуги.
   * «qqc153» - ID пациента
   * «unpaid» : «1» - только неоплаченные назначения.
   */
  public function get1860($qqc153, $unpaid = FALSE) {
    if (!empty($unpaid)) {
      $params = ['unpaid' => 1];
    }
    else {
      $params = [];
    }
    $response = $this->postRequest(__FUNCTION__, $params);

    if (is_array($response)) {
      $today = date('Y-m-d');
      $total = 0;

      if (!empty($response['rows'])) {
        foreach ($response['rows'] as $key => $item) {
          if (date('Y-m-d', strtotime($item['datAF'])) <= $today) {
            unset($response['rows'][$key]);
          }
          else {
            $total += $item['price'];
          }
        }

        $response['orders']['totalAmount'] = $total;
        $response['payment']['totalAmount'] = $total;
      }
    }

    return $response;
  }

  /**
   * Метод "deleteFromCart".
   *
   * Описание: удалить услугу из корзины.
   *
   * @param string $service
   *   ID услуги.
   * @param string $date
   *   Дата (слот назначения, «DD.MM.YYYY»).
   * @param string $time
   *   Время (слот назначения, «очередь»/«18:00-18:30»).
   * @param string $qqc244to
   *   Специалист, на кого назначили (слот назначения).
   *
   * @return bool
   *   TRUE/FALSE.
   */
  public function deleteFromCart($service, $date = '', $time = '', $qqc244to = '') {
    $params = [
      'service' => $service,
    ];
    if (!empty($date)) {
      $params['date'] = $date;
    }
    if (!empty($qqc244to)) {
      $params['qqc244to'] = $qqc244to;
    }
    if (!empty($time)) {
      $params['time'] = $time;
    }

    $result = $this->postRequest(__FUNCTION__, $params);
    return $result;
  }

  /**
   * Метод "getCart".
   *
   * Корзина пациента, с накопленными заказами.
   *
   *  Входные параметры обязательные:
   * - qqc153 - код пациента (только для авторизованного пользователя)
   * - user - строка «login hash»
   *
   * @return array
   *   Response from MIS.
   */
  public function getCart($qqc153 = '') {
    $params = !empty($qqc153) ? ['qqc153' => $qqc153] : [];
    $params['user'] = $this->currentUser->id();
    $result = $this->postRequest(__FUNCTION__, $params);
    return $result;
  }

  /**
   * Метод "delAppointment".
   *
   * Code qqc1860 - код назначения.
   *
   * @return array
   *   Response from MIS.
   */
  public function delAppointment($qqc1860) {
    $params['qqc1860'] = $qqc1860;
    $result = $this->postRequest(__FUNCTION__, $params);
    return $result;
  }

  /**
   * Метод "payByDeposit".
   *
   * Оплату услуг с внесенной ранее предоплаты.
   *
   * @param string $amount
   *   Сумма в рублях.
   * @param string $qqc1860
   *   ID назначения.
   *
   * @return array
   *   Response from MIS.
   */
  public function payByDeposit($amount, $qqc1860) {
    $params = [
      'amount' => $amount,
    ];

    if (is_array($qqc1860)) {
      $params['data'] = $qqc1860;
    }
    else {
      $params['qqc1860'] = $qqc1860;
    }

    $result = $this->postRequest(__FUNCTION__, $params);
    return $result;
  }

  /**
   * Метод "payment".
   *
   * Запрос на получение ссылки для оплаты назначения.
   *
   * @param string $qqc1860
   *   Qqc1860 Appointement ID.
   * @param string $qqc153
   *   User qqc153.
   * @param string $qqc153parent
   *   User qqc153parent.
   *
   * @return string
   *   Url to Sberbank payment page.
   */
  public function payment($qqc1860, $qqc153, $qqc153parent = '') {
    if (is_array($qqc1860)) {
      $params['data'] = $qqc1860;
    }
    else {
      $params['qqc1860'] = $qqc1860;
    }

    $params['qqc153'] = $qqc153;

    if (!empty($qqc153parent)) {
      $params['qqc153parent'] = $qqc153parent;
    }

    $params['email'] = $this->currentUser->getEmail();

    $result = $this->postRequest(__FUNCTION__, $params);
    return !empty($result['url']) ? $result['url'] : '';
  }

  /**
   * Метод "get293by186".
   *
   * Описание: Список выполненных услуг в медкарте.
   *
   * @param array $params
   *   Filter, eg ['filter' => 'Лабораторные_услуги'].
   *
   * @return array
   *   Response from MIS.
   */
  public function get293by186($params) {
    $result = $this->postRequest(__FUNCTION__, $params);
    return $result;
  }

  /**
   * Method "get293by186info".
   *
   * Медзаписи по конкретной услуге в медкарте.
   *
   * @param string $qqc186
   *   Code qqc186 (из метода get293by186).
   *
   * @return array
   *   Response from MIS.
   */
  public function get293by186info($qqc186) {
    $result = $this->postRequest(__FUNCTION__, ['qqc186' => $qqc186]);
    return $result;
  }

  /**
   * Метод "getAsgmtFile".
   *
   * Запрос данных файла прикрепленного к выполненой услуге.
   *
   * @param string $file_name
   *   Filename.
   * @param string $qqc293
   *   Qc293 Appointement List Item ID.
   *
   * @return array|mixed
   *   Response from MIS.
   */
  public function getAsgmtFile($file_name, $qqc293) {
    $result = $this->postRequest('getFile', ['fileName' => $file_name, 'qqc293' => $qqc293]);
    return $result;
  }

  /**
   * Метод "print293by186".
   *
   * Запрос на получение файла-результата для выполненой услуги
   *
   * @param string $id
   *   Qqc186 (ID выполнения услуги) или qqc293 (ID статуса).
   * @param string $type
   *   Название поля идентификатора - qqc186 или qqc293.
   *
   * @return array|mixed
   *   Response from MIS.
   */
  public function print293by186($id, $type = 'qqc186') {
    $result = $this->postRequest(__FUNCTION__, [$type => $id]);
    return isset($result['file']) ? $result : FALSE;
  }

  /**
   * Найти qqc153.
   */
  public function getQqc153() {
    return $this->clientBase->getQqc153();
  }

  /**
   * Метод "getAgreement".
   *
   * Описание: Возвращает информацию обо всех доступных согласиях.
   *
   * @param string $qqc153
   *   ID пациента.
   * @param string $email
   *   Email пациента.
   * @param string $type
   *   Тип соглашения, прим. "E", "J".
   *
   * @return array
   *   Response from MIS.
   */
  public function getAgreement($qqc153, $email, $type = '') {
    $type = !empty($type) ? $type : 'E';
    $params = [
      'qqc153' => $qqc153,
      'email' => $email,
      'type' => $type,
    ];
    $result = $this->postRequest(__FUNCTION__, $params);
    return $result;
  }

  /**
   * Метод "createAgreement".
   *
   * Запрос проверки на согласие обработки персональных данных.
   *
   * @param string $qqc153
   *   Qqc153 Patient ID.
   * @param string $email
   *   Email клиента.
   * @param string $type
   *   Тип соглашения.
   * @param string $action
   *   Создать.
   *
   * @return array|mixed
   *   Response from MIS.
   */
  public function createAgreement($qqc153, $email, $type = 'E', $action = 'create') {
    if (!empty($qqc153)) {
      $params = [
        'qqc153' => $qqc153,
        'type' => $type,
        // 'email' => $email,
      ];
    }

    $params['agreement'] = '1';
    $params['action'] = $action;

    return $this->postRequest(__FUNCTION__, $params);
  }

  /**
   * Метод "getContractAmount".
   *
   * Поиск действуюещего договора на оказание платных медицинских услуг.
   *
   * @param string $qqc153
   *   Qqc153 Patient ID.
   *
   * @return array|mixed
   *   Response from MIS.
   */
  public function getContractAmount($qqc153) {
    $params = [
      'qqc153' => $qqc153,
    ];
    return $this->postRequest(__FUNCTION__, $params);
  }

  /**
   * Метод "createContract".
   *
   * Описание: создание договора на оказание платных медицинских услуг.
   *
   * @param string $qqc153
   *   Qqc153 Patient ID.
   * @param string $qqc153_parent
   *   Qqc153parent Patient ID.
   *
   * @return array|mixed
   *   Response from MIS.
   */
  public function createContract($qqc153, $qqc153_parent = '') {
    $params = [
      'qqc153' => $qqc153,
      'qqc153parent' => !empty($qqc153_parent) ? $qqc153_parent : $qqc153,
    ];

    return $this->postRequest(__FUNCTION__, $params);
  }

  /**
   * Метод "getServiceDescription".
   *
   * Метод возвращает блок описания для услуги.
   *
   * @param string $service
   *   Service code.
   */
  public function getServiceDescription($service) {
    $result = $this->postRequest(__FUNCTION__, ['qqc83' => $service]);
    return $result;
  }

  /**
   * Метод "getInterdependent".
   *
   * Получение списка взаимозависимых лиц (родственников) для пациента.
   *
   * @param string $qqc153
   *   Qqc153 Patient ID.
   * @param string $relationList
   *   RelationList.
   * @param string $ageLimit
   *   Age limit.
   *
   * @return array|mixed
   *   Response from MIS.
   */
  public function getInterdependent($qqc153, $relationList = '', $ageLimit = '') {
    $params = ['qqc153' => $qqc153];
    if (!empty($relationList)) {
      $params['relationList'] = $relationList;
    }

    if (!empty($ageLimit)) {
      $params['ageLimit'] = $ageLimit;
    }

    $result = $this->postRequest(__FUNCTION__, $params);

    return !empty($result['interdependent']) ? $result['interdependent'] : $result;
  }

  /**
   * Метод "getFile" - открытие файла.
   *
   * @return array
   *   Response from MIS.
   */
  public function getFile($specialist) {
    $params = [
      'qqc244to' => $specialist,
    ];

    $result = $this->postRequest(__FUNCTION__, $params);

    return $result;
  }

  /**
   * Make POST request to REST-service from "client_base" service.
   *
   * @return array
   *   Response from MIS.
   */
  public function postRequest($method, $data = [], $qqc153_off = FALSE) {
    $client = $this->clientBase;
    $client->setDebug($this->debugStatus);
    $client->setQqc153($qqc153_off);

    $response = $client->postRequest($method, $data);
    if ($response) {
      if (!empty($response['success'])) {
        return !empty($response['data']) ? $response['data'] : $response['success'];
      }
      else {
        return $response;
      }
    }
    return FALSE;
  }

  /**
   * Метод "getFormInfo".
   *
   * Запрос cтруктуры и данных форм/дневников/анкет
   *
   * @param string $qqc370
   *   Qqc370 (Form ID) OR pAN (Description).
   * @param string $qqc1860
   *   Qqc1860 Appointment ID.
   * @param string $lastDairyInst
   *   LastDairyInst show files of journal for today.
   *
   * @return array|mixed
   *   Response from MIS.
   */
  final public function getFormInfo($qqc370, $qqc1860 = NULL, $lastDairyInst = NULL) {
    $params = [
      'qqc370' => $qqc370,
      'qqc1860' => $qqc1860,
      'lastDairyInst' => $lastDairyInst,
    ];

    $result = $this->postRequest('get370', $params);

    $info = $result['status'];
    $info['attachedFiles'] = !empty($result['attachedFiles']) ? $result['attachedFiles'] : [];
    return $info;
  }

}
