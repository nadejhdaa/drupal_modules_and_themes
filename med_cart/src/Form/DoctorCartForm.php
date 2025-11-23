<?php

declare(strict_types=1);

namespace Drupal\med_cart\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\med_mis\Client\MisClient;
use Drupal\med_content\DoctorService;
use Drupal\med_mis\Utility\Utility;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\med_mis\MisData;
use Drupal\med_cart\CartManager;
use Drupal\med_cart\SlotManager;
use Drupal\Core\Form\FormBuilder;
use Drupal\med_cart\Ajax\AddParamsToUrl;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\med_cart\ServiceManager;
use Drupal\med_lk\UserService;
use Drupal\med_service\EncMisServiceHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\med_service_data\ServiceSlotsMessage;

/**
 * Provides a ENC content form.
 */
final class DoctorCartForm extends FormBase {

  const AGREEMENT_TYPE_J = 'J';

  /**
   * The "mis_data" service.
   *
   * @var \Drupal\med_mis\MisData
   */
  protected $misData;

  /**
   * The "med_cart.cart_manager" service.
   *
   * @var \Drupal\med_cart\CartManager
   */
  protected $cartManager;

  /**
   * The "med_cart.slot_manager" service.
   *
   * @var \Drupal\med_cart\SlotManager
   */
  protected $slotManager;

  /**
   * The "mis_client" service.
   *
   * @var \Drupal\med_mis\Client\MisClient
   */
  protected $misClient;

  /**
   * The "med_cart.slot_manager" service.
   *
   * @var \Drupal\med_content\DoctorService
   */
  protected $doctorService;

  /**
   * The "cache.data" service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The mis services.
   *
   * @var array
   */
  protected $misServices;

  /**
   * The specialist code.
   *
   * @var string
   */
  protected $specialist;

  /**
   * The serivce code.
   *
   * @var string
   */
  protected $service;

  /**
   * The specialist FIO.
   *
   * @var string
   */
  protected $fio;

  /**
   * The service list.
   *
   * @var array
   */
  protected $services;

  /**
   * The services list for select element.
   *
   * @var array
   */
  protected $serviceOptions;

  /**
   * The days list.
   *
   * @var array
   */
  protected $daysSettings;

  /**
   * The slots list.
   *
   * @var array
   */
  protected $slots;

  /**
   * The "med_cart.service_manager" service.
   *
   * @var \Drupal\med_cart\ServiceManager
   */
  protected $serviceManager;

  /**
   * The "med_lk.user_service" service.
   *
   * @var \Drupal\med_lk\UserService
   */
  protected $userService;

  /**
   * The "med_service.med_mis_service_helper" service.
   *
   * @var Drupal\med_service\EncMisServiceHelper
   */
  protected $medMisServiceHelper;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The "med_service_data.service_slot_message" service.
   *
   * @var \Drupal\med_service_data\ServiceSlotsMessage
   */
  protected $serviceSlotsMessage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    MisData $mis_data,
    CartManager $cart_manager,
    SlotManager $slot_manager,
    MisClient $mis_client,
    DoctorService $doctor_service,
    FormBuilder $form_builder,
    CacheBackendInterface $cacheBackend,
    ServiceManager $service_manager,
    UserService $user_service,
    EncMisServiceHelper $med_mis_service_helper,
    EntityTypeManagerInterface $entity_type_manager,
    ServiceSlotsMessage $service_slot_message,
  ) {
    $this->misData = $mis_data;
    $this->cartManager = $cart_manager;
    $this->slotManager = $slot_manager;
    $this->misClient = $mis_client;
    $this->doctorService = $doctor_service;
    $this->formBuilder = $form_builder;
    $this->cacheBackend = $cacheBackend;
    $this->serviceManager = $service_manager;
    $this->userService = $user_service;
    $this->medMisServiceHelper = $med_mis_service_helper;
    $this->entityTypeManager = $entity_type_manager;
    $this->serviceSlotsMessage = $service_slot_message;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_data'),
      $container->get('med_cart.cart_manager'),
      $container->get('med_cart.slot_manager'),
      $container->get('mis_client'),
      $container->get('doctor_service'),
      $container->get('form_builder'),
      $container->get('cache.data'),
      $container->get('med_cart.service_manager'),
      $container->get('med_lk.user_service'),
      $container->get('med_service.med_mis_service_helper'),
      $container->get('entity_type.manager'),
      $container->get('med_service_data.service_slot_message'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'med_doctor_cart_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL): array {
    $input = $form_state->getUserInput();
    $form['#id'] = 'med-doctor-cart-form';

    $specialist = $this->getRequest()->get('specialist');

    $nid = !empty($nid) ? $nid : $this->getRequest()->get('nid');

    if (empty($specialist)) {
      if (!empty($nid)) {
        $specialist = $this->getSpecialistFromNid($nid);
      }
    }

    // If message about slots should be changed.
    $change_msg = FALSE;

    if (!empty($nid)) {
      $change_msg = $this->changeSlotsMessage($nid);

      $storage = $form_state->getStorage();
      $storage['nid'] = $nid;
      $storage['change_msg'] = $change_msg;
      $form_state->setStorage($storage);
    }

    if (!empty($specialist)) {
      $this->specialist = $specialist;

      $price = '';
      $specialist = '';
      $date = '';
      $this->service = !empty($input['service']) ? $input['service'] : $this->getRequest()->query->get('service');

      $this->misServices = $this->getMisServices($form_state);
      $this->getServiceOptions();

      $fio = $this->fio;

      $this->service = $this->service ?: reset($this->misServices)['value'];

      $prepare_info = $this->medMisServiceHelper->checkServicePrepareInfoByService($this->service);

      $form['prepare_info'] = [
        '#prefix' => '<div class="med-doctor-cart-form__prepare-info">',
        '#suffix' => '</div>',
        '#markup' => !empty($prepare_info['markup']) ? $prepare_info['markup'] : '',
      ];

      // Узнать цену услуги.
      $price = $this->getServicePrice($this->service);
      $form['nid'] = [
        '#type' => 'hidden',
        '#value' => !empty($nid) ? $nid : '',
      ];

      $form['fio'] = [
        '#type' => 'hidden',
        '#value' => $fio,
      ];

      $form['specialist'] = [
        '#type' => 'hidden',
        '#value' => $this->specialist,
      ];

      if (!empty($this->serviceOptions)) {
        $form['service'] = [
          '#options' => $this->serviceOptions,
          '#type' => 'select',
          '#title' => $this->t('Select a service to display the schedule'),
          '#default_value' => $this->service,
          '#ajax' => [
            'callback' => '::selectServiceAjaxCallback',
            'progress' => [
              'type' => 'throbber',
              'message' => $this->t('Shedule is loading...'),
            ],
          ],
        ];
      }
      else {
        $form['service'] = [
          '#type' => 'item',
          '#markup' => '',
        ];
      }

      $form['price_markup'] = [
        '#type' => 'markup',
        '#theme' => 'service_price',
        '#price' => $price,
        '#value' => Utility::RUB,
        '#label' => $this->t('Price'),
        '#service' => $this->service,
      ];

      $form['qqc244to'] = [
        '#type' => 'hidden',
        '#value' => $this->specialist,
      ];

      $form['price'] = [
        '#type' => 'hidden',
        '#value' => $price,
      ];

      // Doctors calendar.
      $days = $this->getDoctorDaysForService($this->service);

      if (!empty($days)) {
        $this->setServiceDays($days, $this->service);

        $form['#attached']['drupalSettings']['days'] = $this->daysSettings;

        $first_day = reset($days);

        if (!$form_state->isRebuilding()) {
          $date = $first_day;
        }
        else {
          $date = !empty($input['date']) ? $input['date'] : $first_day;
        }

        $storage = $form_state->getStorage();

        $slots = $this->getSlotsByDate($date);
        $storage[$this->service][date('d.m.Y', strtotime($date))] = $slots;

        $form_state->setStorage($storage);

        // Nearest date-time.
        $first_slot = reset($slots);
        $nearest_date_time = $this->buildNearestDateTime($first_day, $first_slot);

        // Slot address.
        $slot_address = $this->slotManager->getSlotAddress($first_slot);

        $form_date_title = $this->t('Available for appointment dates');
      }

      // Если дней приема нет, добавить в ссылку на вебформу свободной заявки
      // параметры врача.
      else {
        $date = '';
        $slots = [];
        $nearest_date_time = '';
        $slot_address = '';

        $form['empty'] = [
          '#type' => '#value',
          '#value' => 'empty',
        ];
        $form_date_title = $this->t('Available dates are absent');
      }

      $form['slot_address'] = [
        '#type' => 'markup',
        '#markup' => $slot_address,
        '#prefix' => '<div class="med-doctor-cart-form__slot-address">',
        '#suffix' => '</div>',
      ];

      $form['nearest_date_time'] = [
        '#type' => 'markup',
        '#markup' => $nearest_date_time,
      ];

      $date = $this->getDateFromDaySlots($slots);
      $form_date_title = $this->buildDateElementTitle($days);

      $available_date = empty($date) ? $date : date('d.m.Y', strtotime($date));
      if (!empty($input['date'])) {
        $available_date = $input['date'];
      }

      $avialable_title = $this->buildAvailableTitle($date, $change_msg);

      $form['date_datepicker'] = [
        '#type' => 'markup',
        '#markup' => '<div id="doctor-datepicker"></div>',
        '#prefix' => '<div class="med-doctor-cart-form__doctor-datepicker-wrapper doctor-datepicker-wrapper">',
        '#suffix' => '</div>',
      ];

      $form['date'] = [
        '#prefix' => '<div class="med-doctor-cart-form__doctor-date-wrapper doctor-date-wrapper">',
        '#suffix' => '</div>',
      ];

      $form['available_title'] = [
        '#type' => 'item',
      ];

      $form['time_display'] = [
        '#prefix' => '<div class="med-doctor-cart-form__doctor-time-wrapper doctor-time-wrapper">',
        '#suffix' => '</div>',
      ];

      $form['time_timepicker'] = [
        '#type' => 'markup',
        '#theme' => 'med_timepicker',
        '#slots' => $slots,
        '#date' => $date . ' ' . $this->service,
      ];

      // Slot fields.
      $slot_fields = $this->slotManager->getSlotFields();
      foreach ($slot_fields as $field_name) {
        $form[$field_name] = [
          '#type' => 'hidden',
          '#prefix' => '<div class="' . $field_name . '-wrapper">',
          '#suffix' => '</div>',
        ];
      }

      $form['date'] = [
        '#type' => 'textfield',
        '#title' => $form_date_title,
        '#default_value' => $available_date,
        '#ajax' => [
          'callback' => '::selectDateAjaxCallback',
          'event' => 'change',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Shedule is loading...'),
          ],
        ],
        '#attributes' => [
          'class' => ['hidden'],
          'size' => 30,
        ],
        '#prefix' => '<div class="med-doctor-cart-form__doctor-date-wrapper doctor-date-wrapper">',
        '#suffix' => '</div>',
      ];

      $form['available_title']['#markup'] = $avialable_title;

      $form['time_display'] = [
        '#type' => 'textfield',
        '#title' => $avialable_title,
        '#attributes' => [
          'size' => 20,
          'class' => ['hidden'],
        ],
        '#ajax' => [
          'callback' => '::selectTimeAjaxCallback',
          'event' => 'change',
        ],
        '#prefix' => '<div class="med-doctor-cart-form__doctor-time-wrapper doctor-time-wrapper">',
        '#suffix' => '</div>',
      ];

      if (!empty($slots)) {
        $form['time_timepicker'] = [
          '#type' => 'markup',
          '#theme' => 'med_timepicker',
          '#slots' => $slots,
          '#date' => $date . ' ' . $this->service,
        ];
      }

      else {
        $webform = Utility::getWebform();
        $url = $webform->toUrl();

        $service_name = !empty($this->services[$this->service]) ? $this->services[$this->service] : reset($this->services);

        $url->setOptions(['query' => ['services' => $service_name]]);
      }

      $form['agreement'] = [
        '#prefix' => '<div class="agreement-j-wrapper">',
        '#suffix' => '</div>',
      ];

      $agreement_j_element = $this->buildAgreementTypeJElement();
      if ($agreement_j_element) {
        $form['agreement'] = array_merge($form['agreement'], $agreement_j_element);
      }

      $form['actions'] = [
        '#type' => 'actions',
        '#attributes' => [
          'class' => [!empty($date) ? 'visible' : 'hidden'],
        ],
        'appoint' => [
          '#type' => 'submit',
          '#value' => $this->buildSubmitText($date),
          '#attributes' => [
            'disabled' => 1,
          ],
          '#ajax' => [
            'callback' => '::submitAppointmentAjaxCallback',
          ],
        ],
        'add_to_cart' => [
          '#type' => 'submit',
          '#value' => $this->t('Add to cart'),
          '#ajax' => [
            'callback' => '::submitAddToCartAjaxCallback',
          ],
          '#attributes' => [
            'disabled' => 1,
          ],
          '#prefix' => '<div class="med-doctor-cart-form__add-to-cart">',
          '#suffix' => '</div>',
        ],
      ];
    }

    else {
      $form['not_found'] = [
        '#type' => 'item',
        '#markup' => $this->t('Not found data'),
        '#prefix' => '<div class="not-found-msg">',
        '#suffix' => '</div>',
      ];
    }

    $form['#theme'] = 'doctor_cart_form';

    if (!empty($nid)) {
      $form = $this->setDoctorNodeFieldsValues($form, $nid);
    }

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#attached']['library'][] = 'med_cart/doctor_cart_form';
    $form['#attached']['library'][] = 'med_cart/appointment_dialog';
    $form['#attached']['library'][] = 'med_cart/upd_webform_ajax_command';
    $form['#attached']['library'][] = 'med_cart/add_params_to_url_ajax_command';

    return $form;
  }

  /**
   * Add doctor node fields values into form.
   */
  public function setDoctorNodeFieldsValues(array &$form, $nid) {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    // Doctor regalia.
    if ($node->hasField('field_doc_regalia') && !$node->get('field_doc_regalia')->isEmpty()) {
      $items =  $node->get('field_doc_regalia')->getValue();
      foreach ($items as $item) {
        $form['#doc_regalia'][] = $item['value'];
      }
    }
    // Doctor post.
    if ($node->hasField('field_doc_post') && !$node->get('field_doc_post')->isEmpty()) {
      $items =  $node->get('field_doc_post')->getValue();
      foreach ($items as $item) {
        $form['#doc_post'][] = $item['value'];
      }
    }
    // Doctor specialty.
    if ($node->hasField('field_doc_specialty') && !$node->get('field_doc_specialty')->isEmpty()) {
      $items =  $node->get('field_doc_specialty')->getValue();

      foreach ($items as $item) {
        $taxonomy_term = $this->entityTypeManager->getStorage('taxonomy_term')->load($item['target_id']);
        $form['#doc_specialty'][] = $taxonomy_term->getName();
      }
    }

    return $form;
  }

  /**
   * Check if message about no slots should be changed.
   */
  public function emptySlotsMsg($service_name, $nid = NULL) {
    $change_msg = FALSE;
    if (!empty($nid)) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      $change_msg = $node->get('field_no_slots_change')->isEmpty() ? TRUE : FALSE;
    }
    return $change_msg;
  }

  /**
   * Ajax callback on select service.
   */
  public function selectServiceAjaxCallback(array &$form, FormStateInterface &$form_state) {
    $response = new AjaxResponse();

    $form_selector = $this->getFormSelector();

    $input = $form_state->getUserInput();
    $this->service = $input['service'];

    $params = $this->getBaseParams();

    if ($this->getRouteMatch()->getRouteName() !== 'med_registry.load_doctor_cart_form') {
      // $response->addCommand(new AddParamsToUrl($params));
    }

    $days = $this->getDoctorDaysForService($this->service);

    $this->daysSettings[$this->service] = $days;
    $this->setServiceDays($days, $this->service);

    $settings = [
      'days' => $this->daysSettings,
    ];
    $merge = TRUE;
    $response->addCommand(new SettingsCommand($settings, $merge));

    $price = $this->getPrice($this->misServices, $this->service);

    $form['price_markup']['#price'] = $price;
    $date_default_value = '';
    $nearest_date_time = '';

    // If days exists.
    if (!empty($days)) {
      $first_day = date('d.m.Y');
      $date_default_value = $first_day;

      $slots = $this->getSlotsByDate($first_day);

      $storage = $form_state->getStorage();
      $storage[$this->service][$first_day] = $slots;
      $form_state->setStorage($storage);

      // Nearest date-time.
      $first_slot = reset($slots);
      $nearest_date_time = $this->buildNearestDateTime($first_day, $first_slot);

      // Slot address.
      $slot_address = $this->slotManager->getSlotAddress($first_slot);

      $response->addCommand(new HtmlCommand("{$form_selector} div[data-specialist='" . $this->specialist . "']", $nearest_date_time));
      $response->addCommand(new InvokeCommand("{$form_selector} div[data-drupal-selector='edit-actions']", 'removeClass', ['hidden']));

      if ($this->getRouteMatch()->getRouteName() == 'entity.node.canonical') {
        $price_node = str_replace(' ', '', $price);
        $nearest_date_time_node = date('d.m.Y', strtotime($first_day));

        $parent_selector = 'article.node .doc-order--item';
        $selector = $parent_selector . ' h4[data="service-price"]';
        $response->addCommand(new HtmlCommand($selector, $price_node . ' ₽'));

        $selector = $parent_selector . ' h4[data="closest-slot"]';
        $response->addCommand(new HtmlCommand($selector, $nearest_date_time_node));

        if (!empty($slot_address)) {
          $response->addCommand(new HtmlCommand('.med-doctor-cart-form__slot-address', $slot_address));
        }
        else {
          $response->addCommand(new HtmlCommand('.med-doctor-cart-form__slot-address', ''));
        }

        $response->addCommand(new InvokeCommand('body', 'removeClass', ['doctor-service-no-slots']));
      }
    }
    // If days aren`t exists.
    else {
      $slots = [];
      $first_day = '';
      $nearest_date_time = '';

      $webform = Utility::getWebform();
      $url = $webform->toUrl();

      $services_str = !empty($input['fio']) ? $input['fio'] : '';

      if (!empty($input['service'])) {
        $services_str .= ' ' . $this->serviceOptions[$this->service];
      }

      $url->setOptions(['query' => ['services' => $services_str]]);

      $response->addCommand(new HtmlCommand("{$form_selector} div[data-specialist='" . $this->specialist . "']", ''));
      $response->addCommand(new InvokeCommand("{$form_selector} div[data-drupal-selector='edit-actions']", 'addClass', ['hidden']));
      $response->addCommand(new HtmlCommand('.med-doctor-cart-form__slot-address', ''));

      if ($this->getRouteMatch()->getRouteName() == 'entity.node.canonical') {
        $parent_selector = 'article.node .doc-order--item';

        $selector = $parent_selector . ' h4[data="service-price"]';
        $response->addCommand(new HtmlCommand($selector, ''));

        $selector = $parent_selector . ' h4[data="closest-slot"]';
        $response->addCommand(new HtmlCommand($selector, ''));

        $response->addCommand(new InvokeCommand('body', 'addClass', ['doctor-service-no-slots']));
      }
    }

    $agreement_j_element = $this->buildAgreementTypeJElement();

    if ($agreement_j_element) {
      $form['agreement'] = $agreement_j_element;
      $response->addCommand(new HtmlCommand('.agreement-j-wrapper', $form['agreement_j']));
    }

    $check_type_info = $this->medMisServiceHelper->checkServicePrepareInfoByService($this->service);
    $response->addCommand(new HtmlCommand('.med-doctor-cart-form__prepare-info', !empty($check_type_info['markup']) ? $check_type_info['markup'] : ''));

    $form['date']['#default_value'] = $date_default_value;
    $form['date']['#title'] = $this->buildDateElementTitle($days);

    $response->addCommand(new ReplaceCommand("{$form_selector} .doctor-date-wrapper", $form['date']));
    $response->addCommand(new ReplaceCommand("{$form_selector} .doctor-datepicker-wrapper", $form['date_datepicker']));

    $storage = $form_state->getStorage($storage);
    $change_msg = !empty($storage['change_msg']) ? $storage['change_msg'] : FALSE;

    $available_title = $this->buildAvailableTitle('', $change_msg);
    $form['time_display']['#title'] = $available_title;
    $form['time_display']['#value'] = '';
    $response->addCommand(new ReplaceCommand("{$form_selector} .doctor-time-wrapper", $form['time_display']));

    $response->addCommand(new ReplaceCommand("{$form_selector} div[data='service-price']", $form['price_markup']));

    $form['nearest_date_time']['#markup'] = $nearest_date_time;
    $response->addCommand(new HtmlCommand("{$form_selector} div[data-specialist='" . $this->specialist . "']", $form['nearest_date_time']));

    $form['time_timepicker']['#slots'] = $slots;
    $form['time_timepicker']['#date'] = $first_day . ' ' . $this->service;

    $response->addCommand(new ReplaceCommand("{$form_selector} .doctor-timepicker-wrapper", $form['time_timepicker']));

    $search_date = !empty($date_default_value) ? date('Y-m-d', strtotime($date_default_value)) : date('Y-m-d');
    if (in_array($search_date, $days)) {
      $form['actions']['appoint']['#value'] = $this->buildSubmitText($date_default_value);
      $response->addCommand(new ReplaceCommand("{$form_selector} input[data-drupal-selector='edit-appoint']", $form['actions']['appoint']));
    }

    $response->addCommand(new InvokeCommand(NULL, 'setDefaultDateAjaxCallback', [$date_default_value]));

    return $response;
  }

  /**
   * Select date Ajax callback.
   */
  public function selectDateAjaxCallback(array &$form, FormStateInterface &$form_state) {
    $input = $form_state->getUserInput();
    $form_selector = $this->getFormSelector();

    $date = $input['date'];
    $slots = $this->getSlotsByDate($date);

    $storage = $form_state->getStorage();
    $storage[$this->service][$date] = $slots;
    $form_state->setStorage($storage);

    $form['time_timepicker']['#slots'] = $slots;

    $form['time_display']['#default_value'] = '';
    $form['time_display']['#value'] = '';

    $form['time_display']['#title'] = $this->buildAvailableTitle($date, $this->changeMessage);

    $response = new AjaxResponse();

    if (!empty($date)) {
      $response->addCommand(new HtmlCommand("{$form_selector} .service-date-title", $date));
      $response->addCommand(new ReplaceCommand("{$form_selector} .doctor-time-wrapper", $form['time_display']));
      $response->addCommand(new ReplaceCommand("{$form_selector} .doctor-timepicker-wrapper", $form['time_timepicker']));

      $form['actions']['appoint']['#value'] = $this->buildSubmitText($date);
      $response->addCommand(new ReplaceCommand("{$form_selector} input[data-drupal-selector='edit-appoint']", $form['actions']['appoint']));
      $response->addCommand(new ReplaceCommand("{$form_selector} .med-doctor-cart-form__add-to-cart", $form['actions']['add_to_cart']));
    }

    return $response;
  }

  /**
   * Select time ajax callback.
   */
  public function selectTimeAjaxCallback(array &$form, FormStateInterface &$form_state) {
    $input = $form_state->getUserInput();
    $time = $input['time_display'];

    $date = $input['date'];
    $qqc244to = $input['qqc244to'];

    if (!empty($qqc244to)) {
      $response = new AjaxResponse();

      $storage = $form_state->getStorage();

      $slots = !empty($storage[$this->service][$date]) ? $storage[$this->service][$date] : $this->slots;
      if (!empty($slots)) {
        $active_slot = $slots[$time];
      }

      // Update slot fields.
      $slot_fields = $this->slotManager->getSlotFields();
      foreach ($slot_fields as $slot_field) {
        if (!empty($active_slot[$slot_field])) {
          $form[$slot_field]['#value'] = $active_slot[$slot_field];

          $form_selector = 'form[data-drupal-selector="med-doctor-cart-form"]';
          $element_selector = $form_selector . ' .' . $slot_field . '-wrapper';

          $response->addCommand(new HtmlCommand($element_selector, $form[$slot_field]));
        }
      }

      // Type "J" consent exists check.
      $type_j_exists = TRUE;
      if (!empty($form['agreement_j']) && empty($input['agreement_j'])) {
        $type_j_exists = FALSE;
      }

      if (!empty($date) && !empty($time) && $type_j_exists) {
        $form['actions']['appoint']['#attributes']['disabled'] = FALSE;
        $form['actions']['appoint']['#value'] = $this->buildSubmitText($date, $time);
        $response->addCommand(new ReplaceCommand('input[data-drupal-selector="edit-appoint"]', $form['actions']['appoint']));

        $form['actions']['add_to_cart']['#attributes']['disabled'] = FALSE;
        $response->addCommand(new ReplaceCommand("{$form_selector} .med-doctor-cart-form__add-to-cart", $form['actions']['add_to_cart']));
      }

      return $response;
    }
  }

  /**
   * Create agreement click button ajax callback.
   */
  public function createAgreementTypeJAjaxCallback(array &$form, FormStateInterface $form_state) { // phpcs:ignore
    $agreement_type_j = $this->userService->createAgreement(self::AGREEMENT_TYPE_J);

    $response = new AjaxResponse();
    if ($agreement_type_j) {
      $response->addCommand(new RemoveCommand('.agreement-j-wrapper'));
    }

    return $response;
  }

  /**
   * Add to cart ajax callback.
   */
  public function submitAddToCartAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $success = FALSE;
    $count = 0;

    $input = $form_state->getUserInput();
    $slot = $this->getSlotFromUserInput($input);

    // Anonymous user.
    if ($this->currentUser()->isAnonymous()) {
      $result = $this->cartManager->addToCart($slot);

      $count = !empty($result['count']) ? $result['count'] : '';
      $success = !empty($result['success']) ? TRUE : FALSE;
    }

    // Authorized user.
    else {
      $verify = $this->misClient->verifyAddToCart($slot);

      if ($verify) {
        $add_to_cart = $this->misClient->addToCart($slot);

        if ($add_to_cart) {
          $success = TRUE;

          // Update cart items count number.
          $get_cart_response = $this->misClient->getCart();
          $count = $get_cart_response['slotsTotal'];
        }
      }
    }

    // Check addtional service should add to cart (eg "Взятие крови из вены").
    $need_additional_service = $this->medMisServiceHelper->needAdditionalService($slot);

    if (!empty($need_additional_service)) {
      $add_additional_service = TRUE;
    }

    if ($count > 0) {
      $response->addCommand(new HtmlCommand('.block-med-cart-top .count', $count));
      $response->addCommand(new InvokeCommand('.block-med-cart-top .count', 'addClass', ['count--positive']));

      if (!empty($need_additional_service)) {
        if ($count > 1) {
          foreach ($get_cart_response['slots'] as $slot) {
            if ($slot['service'] == $need_additional_service) {
              $add_additional_service = FALSE;
              break;
            }
          }
        }
      }
    }

    // Add additional service to cart.
    if (!empty($add_additional_service)) {

      // If user is anonymous check if additional service is exists.
      if ($this->currentUser()->isAnonymous()) {
        $cart_items = $this->cartManager->cartItems();
        foreach ($cart_items as $cart_item) {
          if ($cart_item['service'] == $add_additional_service) {
            $add_additional_service_do = FALSE;
            break;
          }
        }
      }
      // Also if user is authorized check if additional serice is exists.
      else {
        $result = $this->misClient->getCart();
        foreach ($result['slots'] as $slot) {
          if ($slot['service'] == $add_additional_service) {
            $add_additional_service_do = FALSE;
            break;
          }
        }
      }

      if (!empty($add_additional_service_do)) {
        $additional_slot = $this->serviceManager->getAdditionalServiceSlot();
      }
    }

    // Подготовить модальное окно.
    $title = $success ? $this->t('The slot has been successfully added to the cart') : $this->t('An error has occurred');
    $type = $success ? 'success' : 'error';

    $slot_address = $this->slotManager->getSlotAddress($slot);
    if (!empty($slot_address)) {
      $slot['address'] = $slot_address;
    }

    $modal_content = [
      '#theme' => 'dialog_add_to_cart',
      '#slot' => $slot,
      '#type' => $type,
      '#additional_slot' => !empty($additional_slot) ? $additional_slot : FALSE,
    ];

    $dialog_options = [
      'width' => '1296',
      'dialogClass' => 'modal-dialog--doctor-cart-form',
    ];

    $response->addCommand(new CloseModalDialogCommand(TRUE));
    $response->addCommand(new OpenModalDialogCommand($title, $modal_content, $dialog_options));

    return $response;
  }

  /**
   * Appoint slot ajax callbck.
   */
  public function submitAppointmentAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $input = $form_state->getUserInput();

    $slot = $this->getSlotFromUserInput($input);

    if ($this->currentUser()->isAnonymous()) {
      $params['slot'] = $slot;

      $slot_data = [
        $slot['pAz'],
        '"' . $slot['title'] . '"',
        $slot['date'] . ', "' . $slot['time'] . '"',
      ];

      $slot_str = implode('. ', $slot_data);

      $params['slot_str'] = $slot_str;

      $params['fio'] = $input['fio'];

      $params['user_is_logged_in'] = $this->currentUser()->isAnonymous() ? FALSE : TRUE;

      $slot_address = $this->slotManager->getSlotAddress($slot);
      if (!empty($slot_address)) {
        $params['address'] = $slot_address;
      }

      $modal_content = [
        '#theme' => 'dialog_appointment',
        '#params' => $params,
      ];

      $response->addCommand(new OpenModalDialogCommand('', $modal_content, ['width' => '753']));
      $response->addCommand(new SettingsCommand(['params' => $params], TRUE));
    }
    // If user is authorized.
    else {
      $result = $this->misClient->verifyAddToCart($slot);

      if ($result) {
        $msg = '';
        $title = '';

        $add_to_cart = $this->misClient->addToCart($slot);

        if ($add_to_cart) {

          $appoint_cart = $this->misClient->appointCart([$slot]);

          if (!empty($appoint_cart['slots'])) {
            $title = $this->t('Appointment completed successfully');
            $msg = $this->t('Your appointment has been successfully completed');

            $type = 'success';
          }
          else {
            $title = $this->t('An error has occurred');
            $msg = $appoint_cart['error'];
            $type = 'error';
          }
        }

        $slot_address = $this->slotManager->getSlotAddress($slot);
        if (!empty($slot_address)) {
          $slot['address'] = $slot_address;
        }

        $modal_content = [
          '#theme' => 'dialog_appointment_submitted',
          '#slot' => $slot,
          '#msg' => $msg,
          '#type' => $type,
        ];

        $response->addCommand(new OpenModalDialogCommand($title, $modal_content, ['width' => '753']));
      }
    }
    return $response;
  }

  /**
   * Get slots from user input.
   */
  public function getSlotFromUserInput($input) {
    $slot = [];
    $slot_fields = $this->slotManager->getSlotFields();

    foreach ($slot_fields as $slot_field) {
      if (!empty($input[$slot_field])) {
        $slot[$slot_field] = $input[$slot_field];
      }
    }

    if (!empty($input['qqc244to'])) {
      $slot['qqc244to'] = $input['qqc244to'];
    }

    $slot['qqc244'] = $input['specialist'];
    $slot['date'] = $input['date'];
    $slot['time'] = $input['time'];
    $slot['price'] = $input['price'];
    $slot['service'] = $input['service'];
    $slot['paymentSource'] = Utility::DEFAULT_PAYMENT_SOURCE;

    if ($this->slotManager->slotIsDistant($slot)) {
      unset($slot['room']);
    }

    return $slot;
  }

  /**
   * Get serivces options.
   */
  public function getServiceOptions() {
    $options = [];
    $doctor_services = [];
    if (!empty($this->misServices)) {
      foreach ($this->misServices as $service) {
        $service_title = $service['title'];
        if (!empty($service['@XHMr7tar'])) {
          $service_title .= ' - ' . number_format(intval($service['@XHMr7tar']), 0, '.', ' ') . ' р.';
        }
        $options[$service['value']] = $service_title;
        $doctor_services[$service['value']] = $service['title'];
      }
    }

    $this->services = $doctor_services;
    $this->serviceOptions = $options;
    return $options;
  }

  /**
   * Get date from day slots.
   */
  public function getDateFromDaySlots($slots) {
    if (!empty($slots)) {
      return !empty(reset($slots)['date']) ? reset($slots)['date'] : '';
    }
    return '';
  }

  /**
   * Get price from selected service.
   */
  public function getPrice($services, $selected_service) {
    if (!empty($services)) {
      foreach ($services as $service) {
        if ($service['value'] == $selected_service) {
          if (!empty($service['@XHMr7tar'])) {
            return number_format(intval($service['@XHMr7tar']), 0, ',', ' ');
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Build price list.
   */
  public function buildPriceList($mis_services) {
    $price_list = [];
    foreach ($mis_services as $service) {
      $price_list[$service['value']] = intval($service['@XHMr7tar']);
    }
    return $price_list;
  }

  /**
   * Build nearest date and time string.
   */
  public function buildNearestDateTime($first_day, $first_slot) {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId(); //phpcs:ignore
    $date = new DrupalDateTime($first_day, 'UTC', ['langcode' => $language]);

    $parts[] = $date->format('d F');
    $parts[] = $date->format('D');

    if (!empty($first_slot['time'])) {
      $time = explode('-', $first_slot['time']);
      $parts[] = reset($time);
    }
    return implode(', ', $parts);
  }

  /**
   * Build agreement type checkbox element.
   */
  public function buildAgreementTypeJElement() { //phpcs:ignore
    $element = FALSE;

    $service_type = $this->medMisServiceHelper->checkServiceTypeByService($this->service);
    $is_tmk = $service_type == 'med' ? FALSE : TRUE;
    $agreement_type_j = $this->userService->getAgreement(self::AGREEMENT_TYPE_J);

    if (empty($agreement_type_j) && $is_tmk) {
      $element = [
        '#type' => 'checkbox',
        '#title' => $this->t('The service requires consent type "J"'),
        '#ajax' => [
          'callback' => '::createAgreementTypeJAjaxCallback',
        ],
      ];
    }
    return $element;
  }

  /**
   * Build value for submit button.
   */
  public function buildSubmitText($date = '', $time = '') {
    if (empty($date) && empty($time)) {
      return $this->t('Make an appointment');
    }

    elseif (empty($time) && !empty($date)) {
      return $this->t('Make an appointment for @date', ['@date' => $date]);
    }

    elseif (empty($date)&& !empty($time)) {
      return $this->t('Make an appointment');
    }

    return $this->t('Make an appointment for @date at @time', ['@date' => $date, '@time' => $time]);
  }

  /**
   * Find field_doc_mis_code value.
   */
  public function getSpecialistFromNid($nid) {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    if ($node) {
      $this->specialist = $this->doctorService->getSpecialistCode($node);
      $this->fio = $node->label();

      return $this->specialist;
    }
  }

  /**
   * Get MIS services.
   */
  public function getMisServices($form_state) {
    if (!empty($form_state->getValue('mis_services'))) {
      $mis_services = $form_state->getValue('mis_services');
    }
    else {
      $result = $this->misClient->getServicesToAppoint(['specialist' => $this->specialist]);
      $mis_services = !empty($result['services']) ? $result['services'] : [];
      $form_state->setValue('mis_services', $mis_services);

      foreach ($result['filters'] as $filter) {
        if ($filter['code'] == 'specialist') {
          $this->fio = $filter['value']['title'];
        }
      }
    }

    $this->misServices = $mis_services;
    return $mis_services;
  }

  /**
   * Get service price.
   */
  public function getServicePrice($service) {
    $price_list = $this->buildPriceList($this->misServices);
    return $price_list[$service];
  }

  /**
   * Get doctors for service.
   */
  public function getDoctorDaysForService($service) {
    $params = $this->getBaseParams();

    $result = $this->misClient->getSlotsToAppoint($params);

    if (!empty($result['slots'])) {

      $start_date = date('Y-m-d');

      $days = $result['slots'];

      foreach ($days as $date => $day) {
        $formatted_date = date('Y-m-d', strtotime($date));
        if ($formatted_date < $start_date) {
          unset($days[$date]);
        }
        else {
          $days[$date] = date('Y-m-d', strtotime($date));
        }
      }
    }
    else {
      $days = [];
    }

    sort($days);
    return $days;
  }

  /**
   * Build params list.
   */
  public function getBaseParams() {
    $params = [
      'specialist' => $this->specialist,
      'service' => $this->service,
    ];
    if (!empty($this->getRequest()->query->get('nid'))) {
      $params['nid'] = $this->getRequest()->query->get('nid');
    }
    return $params;
  }

  /**
   * Set service days.
   */
  public function setServiceDays($days, $service) {
    $this->daysSettings[$service] = $days;
  }

  /**
   * Get slots by date.
   */
  public function getSlotsByDate($date) {
    $params = $this->getBaseParams();
    $params['date'] = date('Ymd', strtotime($date));

    $slots = $this->misData->getDoctorSlotsByDate($params);
    $this->misData->setDebug(FALSE);
    $this->slots = $slots;

    return $slots;
  }

  /**
   * Build available title from date.
   */
  public function buildAvailableTitle($date = '', $change_msg = FALSE) {
    $search_date = !empty($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
    $service = $this->service;

    // If is possible slots are exists.
    if (!empty($this->daysSettings) && !empty($this->daysSettings[$service])) {

      // There are slots.
      if (in_array($search_date, $this->daysSettings[$service])) {
        $available_date = date('d.m.Y', strtotime($search_date));
        $avialable_title[] = '<div>' . $this->t('Available timeslots to appoint on') . ' ' . $available_date . '</div>';

        if ($change_msg) {
          $altered_msg_str = $this->serviceSlotsMessage->getAlternativeMsgThereAreSlots($service);

          if (!empty($altered_msg_str)) {
            $avialable_title[] = '<div class="msg-altered">' . $altered_msg_str . '</div>';
          }
        }

        return implode('', $avialable_title);
      }
    }

    // There is no free slots.
    $avialable_title[] = empty($date) ? '<div>' . $this->t('There is no free time for appoint today.') . '</div>' : '<div>' . $this->t('There is no free time for appoint on @date.', ['@date' => date('d.m.Y', strtotime($date))]) . '</div>';
    if ($change_msg) {
      $altered_msg_str = $this->serviceSlotsMessage->getAlternativeMsgNoSlots($service);
      if (!empty($altered_msg_str)) {
        $avialable_title[] = '<div class="msg-altered">' . $altered_msg_str . '</div>';
      }
    }

    return implode('', $avialable_title);
  }

  /**
   * Build title from days.
   */
  public function buildDateElementTitle($days) {
    return empty($days) ? $this->t('There are no available dates') : $this->t('Available for appointment dates');
  }

  /**
   * Build slots list.
   */
  public function setSlots($slots) {
    $this->slots = $slots;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Get form class string.
   *
   * @return string
   *   The css class value.
   */
  public function getFormSelector() {
    return 'form.' . Html::cleanCssIdentifier($this->getFormId());
  }

  /**
   * Get form class string.
   *
   * @return string
   *   The css class value.
   */
  public function changeSlotsMessage($nid) {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    return !$node->get('field_no_slots_change')->isEmpty() ? ($node->field_no_slots_change->value == 0 ? FALSE : TRUE) : FALSE;
  }

}
