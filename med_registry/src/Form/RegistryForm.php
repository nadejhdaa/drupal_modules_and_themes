<?php

declare(strict_types=1);

namespace Drupal\med_registry\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\med_mis\MisData;
use Drupal\med_mis\Client\MisClient;
use Drupal\med_mis\Utility\Utility;
use Drupal\med_content\DoctorService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\med_registry\Ajax\SetUrlParamsCommand;
use Drupal\med_registry\Ajax\AddTimeToServicesCommand;
use Drupal\med_service\EncMisServiceHelper;
use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Provides a form to registry form.
 */
final class RegistryForm extends FormBase {

  const SPECIALTY_VID = 'specialty';

  const GET_SERVICES_TO_APPOINT_TIME = 60 * 60 * 6;

  const SERVICES_CHUNKS_COUNT = 30;

  const TAB_SPECIALIST = 'specialist';

  const TAB_SERVICE = 'service';

  const URL_PARAMS_BY_TABS = [
    'specialist' => [
      'fio',
      'specialty',
      'date',
    ],
    'service' => [
      'title',
      'service_specialty',
      'category',
      'selected_service_value',
    ],
  ];

  /**
   * The "mis_data" service.
   *
   * @var \Drupal\med_mis\MisData
   */
  protected $misData;

  /**
   * The "mis_client" service.
   *
   * @var \Drupal\med_mis\Client\MisClient
   */
  protected $misClient;

  /**
   * The "doctor_service" service.
   *
   * @var \Drupal\med_content\DoctorService
   */
  protected $doctorService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * The array with form data from MIS.
   *
   * @var array
   */
  protected $info;

  /**
   * The "med_service.med_mis_service_helper" service.
   *
   * @var \Drupal\med_service\EncMisServiceHelper
   */
  protected $medMisServiceHelper;

  /**
   * The "cache.default" service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The "database" service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * RegistryForm constructor.
   *
   * @param \Drupal\med_mis\MisData $mis_data
   *   The mis_data service.
   * @param \Drupal\med_mis\Client\MisClient $mis_client
   *   The mis_client service.
   * @param \Drupal\med_content\DoctorService $doctor_service
   *   The doctor service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\med_service\EncMisServiceHelper $med_mis_service_helper
   *   The "med_service.med_mis_service_helper" service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The "cache.default" service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The "database" service.
   */
  public function __construct(
    MisData $mis_data,
    MisClient $mis_client,
    DoctorService $doctor_service,
    EntityTypeManagerInterface $entity_type_manager,
    EncMisServiceHelper $med_mis_service_helper,
    CacheBackendInterface $cache_backend,
    Connection $connection,
  ) {
    $this->misData = $mis_data;
    $this->misClient = $mis_client;
    $this->doctorService = $doctor_service;
    $this->entityTypeManager = $entity_type_manager;
    $this->medMisServiceHelper = $med_mis_service_helper;
    $this->cacheBackend = $cache_backend;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_data'),
      $container->get('mis_client'),
      $container->get('doctor_service'),
      $container->get('entity_type.manager'),
      $container->get('med_service.med_mis_service_helper'),
      $container->get('cache.default'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'med_lk_registry_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $input_params = $this->getUserInputParams($form_state);

    $tab_default = !empty($this->getQuery('tab')) ? $this->getQuery('tab') : (!empty($input_params['tab']) ? $input_params['tab'] : self::TAB_SPECIALIST);
    $tab = $tab_default == self::TAB_SERVICE ? $tab_default : self::TAB_SPECIALIST;

    if ($tab !== $tab_default) {
      $this->redirectOnDefaultTab();
    }

    $info = $this->getInfo($form_state);

    if (!empty($input_params['specialist'])) {
      if (!empty($info['doctors'][$input_params['specialist']])) {
        $input_params['fio'] = $info['doctors'][$input_params['specialist']]['fio'];
      }
      else {
        // If specialist parameter is fake.
        $this->redirectOnDefaultTab(['tab' => $tab]);
      }
    }

    $specialty_options = $info['specialty'];

    $form['#id'] = 'med-lk-registry-form';

    // Form tabs.
    $form['tab'] = [
      '#type' => 'radios',
      '#options' => [
        'specialist' => $this->t('Select a specialist'),
        'service' => $this->t('Select a service'),
      ],
      '#default_value' => $tab,
      '#attributes' => [
        'class' => ['registry-tab', 'hidden'],
      ],
      '#ajax' => [
        'callback' => '::clearFormAjaxCallback',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Please wait...'),
        ],
      ],
    ];

    // Doctor select filters.
    $form['specialist_filters'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="tab"]' => ['value' => 'specialist'],
        ],
      ],
    ];

    // Doctor filter by "fio".
    $form['specialist_filters']['fio'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'placeholder' => $this->t('Search by name...'),
      ],
      '#autocomplete_route_name' => 'med_registry.specialist_fio_autocomplete',
      '#default_value' => !empty($input_params['fio']) ? $input_params['fio'] : '',
      '#ajax' => [
        'callback' => '::updateFormAjaxCallback',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Loading data...'),
        ],
        'event' => 'change autocompleteclose',
      ],
      '#autocomplete_route_parameters' => [
        'from' => !empty($input_params['from']) ? $input_params['from'] : '',
        'to' => !empty($input_params['to']) ? $input_params['to'] : '',
      ],
      '#states' => [
        'visible' => [
          ':input[name="tab"]' => ['value' => 'specialist'],
        ],
      ],
    ];

    // Doctor filter by "field_doc_specialty".
    $form['specialist_filters']['specialty'] = [
      '#type' => 'select',
      '#options' => $specialty_options,
      '#empty_option' => $this->t('Specialization'),
      '#empty_value' => 'all',
      '#default_value' => !empty($input_params['specialty']) ? $input_params['specialty'] : 'all',
      '#ajax' => [
        'callback' => '::updateFormAjaxCallback',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Loading data...'),
        ],
        'event' => 'change',
      ],
      '#states' => [
        'visible' => [
          ':input[name="tab"]' => ['value' => 'specialist'],
        ],
      ],
    ];

    if (!empty($input_params['fio'])) {
      $form['specialist_filters']['specialty']['#attributes']['disabled'] = '1';
    }

    // Doctor filter by dates.
    $date_default_value = '';
    if (!empty($input_params['from']) && !empty($input_params['to']) && empty($input_params['fio'])) {
      $date_default_value = $input_params['from'] . '-' . $input_params['to'];
    }
    if (!empty($input_params['fio'])) {
      $date_default_value = '';
    }

    $form['specialist_filters']['date'] = [
      '#type' => 'textfield',
      '#default_value' => $date_default_value,

      '#attributes' => [
        'placeholder' => $this->t('Select dates'),
        'class' => ['date-range-picker'],
      ],
      '#states' => [
        'visible' => [
          ':input[name="tab"]' => ['value' => 'specialist'],
        ],
      ],
    ];

    if (!empty($input_params['from']) && !empty($input_params['to'])) {
      $form['specialist_filters']['date']['#default_value'] = $input_params['from'] . ' - ' . $input_params['to'];
    }

    if (!empty($input_params['fio'])) {
      $form['specialist_filters']['date']['#attributes']['disabled'] = '1';
    }

    // Sort doctors by "fio" value.
    uasort($info['doctors'], fn($a, $b) => $a['fio'] <=> $b['fio']);

    // Sort services by "title" value.
    uasort($info['services'], fn($a, $b) => $a['title'] <=> $b['title']);

    // Build ul from doctor items with foctor info and link.
    if (!empty($info['doctors'])) {
      foreach ($info['doctors'] as $doctor_item) {
        $doctor_items[] = [
          '#theme' => 'med_mis_doctor_item',
          '#doctor_item' => $doctor_item,
        ];
      }
    }

    // Selected doctors if selected service exists.
    $form['selected_doctors'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="tab"]' => ['value' => 'specialist'],
        ],
      ],
    ];

    $form['selected_doctors']['doctors'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="selected-doctors__doctors-list row">{% for item in items %} <div class="selected-doctors__item col-lg-6">{{ item }}</div> {% endfor %}</div>',
      '#context' => [
        'items' => !empty($doctor_items) ? $doctor_items : [],
      ],
    ];

    // Select services filters.
    $form['service_filters'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="tab"]' => ['value' => 'service'],
        ],
      ],
    ];

    if (!empty($input_params['service'])) {
      $selected_service = $input_params['service'];
      if (!empty($info['services'][$selected_service])) {
        $input_params['title'] = $info['services'][$selected_service]['title'];
      }
      // If service parameter is fake.
      else {
        $this->redirectOnDefaultTab(['tab' => $tab]);
      }
    }

    $form['service_filters']['title'] = [
      '#type' => 'textfield',
      '#maxlength' => 256,
      '#attributes' => [
        'placeholder' => $this->t('Search by service title...'),
      ],
      '#autocomplete_route_name' => 'med_registry.service_title_autocomplete',
      '#default_value' => !empty($input_params['title']) ? $input_params['title'] : '',
      '#ajax' => [
        'callback' => '::updateFormAjaxCallback',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Loading data...'),
        ],
        'event' => 'autocompleteclose',
      ],
      '#states' => [
        'visible' => [
          ':input[name="tab"]' => ['value' => 'service'],
        ],
      ],
    ];

    // Select service by doctor specialty.
    $form['service_filters']['service_specialty'] = [
      '#type' => 'select',
      '#options' => $specialty_options,
      '#empty_option' => $this->t('Specialization'),
      '#default_value' => !empty($input_params['service_specialty']) ? $input_params['service_specialty'] : 'all',
      '#ajax' => [
        'callback' => '::updateFormAjaxCallback',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Loading data...'),
        ],
        'event' => 'change',
      ],
      '#states' => [
        'visible' => [
          ':input[name="tab"]' => ['value' => 'service'],
        ],
      ],
    ];

    if (!empty($input_params['title'])) {
      $form['service_filters']['service_specialty']['#attributes']['disabled'] = 1;
    }

    // Select service by category.
    if (!empty($input_params['category'])) {
      if (empty($info['category'][$input_params['category']])) {
        $this->redirectOnDefaultTab($options = ['tab' => $tab]);
      }
    }
    $form['service_filters']['category'] = [
      '#type' => 'select',
      '#options' => $info['category'],
      '#empty_option' => $this->t('Category'),
      '#empty_value' => 'all',
      '#default_value' => !empty($input_params['category']) ? $input_params['category'] : 'all',
      '#ajax' => [
        'callback' => '::updateFormAjaxCallback',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Loading data...'),
        ],
        'event' => 'change',
      ],
      '#states' => [
        'visible' => [
          ':input[name="tab"]' => ['value' => 'service'],
        ],
      ],
    ];

    if (!empty($input_params['title'])) {
      $form['service_filters']['category']['#attributes']['disabled'] = 1;
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#attributes' => [
          'class' => ['registry-submit', 'hidden'],
        ],
        '#ajax' => [
          'callback' => '::updateFormAjaxCallback',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Loading data...'),
          ],
        ],
      ],
      'clear' => [
        '#type' => 'submit',
        '#value' => $this->t('Clear filters'),
        '#ajax' => [
          'callback' => '::clearFormAjaxCallback',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Please wait...'),
          ],
        ],
        '#executes_submit_callback' => FALSE,
      ],
    ];

    $form['selected_services'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'selected-services__wrapper',
        ],
      ],
      '#states' => [
        'visible' => [
          ':input[name="tab"]' => ['value' => 'service'],
        ],
      ],
    ];

    $form['selected_services']['selected_service_value'] = [
      '#type' => 'textfield',
      '#ajax' => [
        'callback' => '::selectServiceAjaxCallback',
        'event' => 'serviceFilled',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Please wait...'),
        ],
      ],
      '#attributes' => [
        'class' => ['hidden'],
      ],
      '#default_value' => !empty($input_params['selected_service_value']) ? $input_params['selected_service_value'] : '',
      '#value' => !empty($input_params['selected_service_value']) ? $input_params['selected_service_value'] : '',
    ];

    $form['selected_services']['info'] = [
      '#prefix' => '<div class="selected-services__prepare-service-info">',
      '#suffix' => '</div>',
    ];

    $form['selected_services']['selected_service_markup'] = [
      '#prefix' => '<div class="selected-services__selected-service-markup">',
      '#suffix' => '</div>',
    ];

    $form['selected_services']['service_doctors'] = [
      '#prefix' => '<div class="selected-services__service-doctors">',
      '#suffix' => '</div>',
    ];

    if (count($info['services']) == 1) {
      $selected_service = reset($info['services'])['value'];
      $service_info = $this->info['services'][$selected_service];

      $form['selected_services']['info']['#markup'] = $this->medMisServiceHelper->checkServicePrepareInfoByService($selected_service);

      $form['selected_services']['selected_service_markup']['#theme'] = 'med_mis_service_item';
      $form['selected_services']['selected_service_markup']['#selected'] = TRUE;
      $form['selected_services']['selected_service_markup']['#service_item'] = $service_info;
      $form['service_filters']['title']['#default_value'] = $service_info['title'];

      $doctor_items = $this->getDoctorItemsFromService($selected_service);

      $form['selected_services']['service_doctors'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="selected-services__doctors-list row">{% for item in items %} <div class="selected-services__item col-lg-6">{{ item }}</div> {% endfor %}</div>',
        '#context' => [
          'items' => $doctor_items,
        ],
      ];
    }

    if (count($info['services']) > 1) {
      $services_chunks = array_chunk($info['services'], self::SERVICES_CHUNKS_COUNT, TRUE);

      foreach ($services_chunks[0] as $service_item) {
        $services_links[] = [
          '#theme' => 'med_mis_service_item',
          '#service_item' => $service_item,
          '#selected' => FALSE,
        ];
      }

      $form['selected_services']['services'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="selected-doctors_services-list row">{% for item in items %} <div class="selected-services__item col-lg-6">{{ item }}</div> {% endfor %}</div>',
        '#context' => [
          'items' => $services_links,
        ],
      ];

      if (count($services_chunks) > 1) {
        $form['selected_services']['load_more'] = [
          '#type' => 'button',
          '#value' => $this->t('Load more'),
          '#prefix' => '<div class="selected-services__load-more d-flex justify-content-center">',
          '#suffix' => '</div>',
          '#ajax' => [
            'callback' => '::loadMoreServicesAjaxCallback',
          ],
          '#executes_submit_callback' => FALSE,
          '#attributes' => [
            'class' => [
              'action',
              'action--small',
              'action--wider',
              'action--inline',
            ],
          ],
        ];
      }
    }

    $form['#theme'] = 'registry_form';

    // $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#attached']['library'][] = 'med_registry/registry_form';
    $form['#attached']['library'][] = 'med_registry/click_command';
    $form['#attached']['library'][] = 'med_registry/add_time_to_services_ajax_command';
    $form['#attached']['library'][] = 'med_cart/add_params_to_url_ajax_command';
    $form['#attached']['library'][] = 'med_registry/set_url_params_ajax_command';

    return $form;
  }

  /**
   * Build user input params to search.
   */
  public function getUserInputParams($form_state) {
    $input_params = [];
    $input = $form_state->getUserInput();

    if (!empty($input['tab'])) {
      $input_params['tab'] = $input['tab'];
    }

    if (!empty($input['date'])) {
      $dates = explode('-', $input['date']);
      $input_params['from'] = trim($dates[0]);

      if (!empty($dates[1])) {
        $input_params['to'] = trim($dates[1]);
      }

      $input_params['date'] = $input['date'];
    }

    if (!empty($input['fio'])) {
      $input_params['fio'] = $input['fio'];
    }

    if (!empty($this->getQuery('specialist'))) {
      $input_params['specialist'] = $this->getQuery('specialist');
    }

    if (!empty($input['specialty']) && $input['specialty'] !== 'all') {
      $input_params['specialty'] = $input['specialty'];
    }

    if (!empty($this->getQuery('service_specialty'))) {
      $input_params['service_specialty'] = $this->getQuery('service_specialty');
    }

    if (!empty($input['service_specialty'])) {
      if ($input['service_specialty'] !== 'all') {
        $input_params['service_specialty'] = $input['service_specialty'];
      }
      else {
        unset($input_params['service_specialty']);
      }
    }

    if (!empty($input['services'])) {
      $input_params['services'] = $input['services'];
    }

    if (!empty($input['title'])) {
      $input_params['title'] = $input['title'];
    }

    if (!empty($this->getQuery('service'))) {
      $input_params['service'] = $this->getQuery('service');
    }

    if (!empty($this->getQuery('category'))) {
      $input_params['category'] = $this->getQuery('category');
    }

    if (!empty($input['category'])) {
      if ($input['category'] != 'all') {
        $input_params['category'] = $input['category'];
      }
      else {
        unset($input_params['category']);
      }
    }

    if (!empty($input['selected_service_value'])) {
      $input_params['selected_service_value'] = $input['selected_service_value'];
    }
    else {
      if (!empty($this->getQuery('service'))) {
        $input_params['selected_service_value'] = $this->getQuery('service');
      }
    }

    if (!empty($this->getQuery('from'))) {
      $from = $this->getQuery('from');
      if ($from == date('d.m.Y')) {
        $from = date('d.m.Y', strtotime('tomorrow'));
      }
      $input_params['from'] = $from;
    }

    if (!empty($this->getQuery('to'))) {
      $input_params['to'] = $this->getQuery('to');
    }

    $trigger = $form_state->getTriggeringElement();

    if (!empty($trigger['#ajax']['callback'])) {

      if ($trigger['#ajax']['callback'] == '::clearFormAjaxCallback') {
        foreach ($input_params as $key => $value) {
          if ($key !== 'tab') {
            unset($input_params[$key]);
          }
        }
      }

      elseif ($trigger['#ajax']['callback'] == '::loadMoreServicesAjaxCallback') {
        unset($input_params['service']);
      }
    }

    return $input_params;
  }

  /**
   * Build form info.
   */
  public function getInfo($form_state) {
    $input_params = $this->getUserInputParams($form_state);

    $services_dates_all = [];
    if (!empty($input_params['service'])) {
      $selected_service = $input_params['service'];
    }

    $info = [
      'fio' => [],
      'specialty' => [],
      'doctors' => [],
      'dates' => [],
      'nids' => [],
      'category' => [],
      'services' => [],
      'services_options' => [],
    ];

    // Terms array from "specialty" vocabulary.
    $specialty_options = $this->getSpecialtyOptions();

    // Get getServicesToAppoint result array.
    $cid = Utility::buildCidString('getServicesToAppoint', ['all']);

    if ($cache = $this->cacheBackend->get($cid)) {
      $response = $cache->data;
    }
    else {
      $response = $this->misClient->getServicesToAppoint([]);
      $this->cacheBackend->set($cid, $response, (time() + self::GET_SERVICES_TO_APPOINT_TIME));
    }

    if (!empty($response['filters'])) {
      // Get from DB data_services.
      $local_data = $this->getSpecialistData($input_params);

      $specialist_dates_all = !empty($local_data['specialist_dates']) ? $local_data['specialist_dates'] : [];
      $specialist_dates_by_service_all = !empty($local_data['specialist_dates_by_service']) ? $local_data['specialist_dates_by_service'] : [];

      foreach ($response['filters'] as $filter) {

        if ($filter['code'] == 'category') {
          foreach ($filter['data'] as $value) {
            $info['category'][$value['value']] = str_replace('_', ' ', $value['title']);
          }
        }

        if ($filter['code'] == 'specialist') {
          foreach ($filter['data'] as $value) {
            $add = TRUE;

            // Define "specialist" code value.
            $specialist = $value['value'];
            $fio = $value['title'];

            $doctor_label = [
              'fio' => $fio,
              'specialist' => $specialist,
            ];

            // Doctor days.
            if (!empty($specialist_dates_all[$specialist])) {
              $doctor_label['specialist_dates'] = $specialist_dates_all[$specialist];
              $doctor_label['specialist_dates_by_service'] = $specialist_dates_by_service_all[$specialist];

              if (!empty($selected_service)) {
                if (!empty($doctor_label['specialist_dates_by_service'][$selected_service])) {
                  $specialist_closest_date = min($doctor_label['specialist_dates_by_service'][$selected_service]);
                  $doctor_label['closest_day'] = strtotime($specialist_closest_date);
                }
              }
              else {
                $specialist_closest_date = min($doctor_label['specialist_dates']);
                $doctor_label['closest_day'] = strtotime($specialist_closest_date);
              }

              if (!empty($specialist_closest_date)) {
                foreach ($doctor_label['specialist_dates_by_service'] as $service_code => $service_dates) {
                  foreach ($service_dates as $service_date) {
                    if ($service_date == $specialist_closest_date) {
                      $doctor_label['service_with_slot'] = $service_code;
                      break;
                    }
                  }
                }
              }
            }

            if (isset($specialist_dates_by_service_all[$specialist])) {
              $services_dates_all += $specialist_dates_by_service_all[$specialist];
            }

            if (!empty($input_params['from']) && !empty($input_params['to'])) {
              if (empty($doctor_label['specialist_dates']) && empty($input_params['fio'])) {
                $add = FALSE;
              }
            }

            if (!empty($input_params['specialist']) && empty($input_params['fio'])) {
              if ($specialist <> $input_params['specialist']) {
                $add = FALSE;
              }
            }

            if (!empty($input_params['fio'])) {
              if ($input_params['fio'] !== $fio) {
                $add = FALSE;
              }
            }

            // Load doctor node.
            $doctor_node = $this->doctorService->loadDoctorByMisCode($specialist);

            // If doctor node is exists.
            if (!empty($doctor_node) && $doctor_node->isPublished()) {
              $specialty_tids = $this->getSpecialtyTids($doctor_node);

              if (!empty($specialty_tids)) {
                foreach ($specialty_tids as $specialty_tid) {
                  $info['specialty'][$specialty_tid] = $specialty_options[$specialty_tid];
                }

                if (!empty($input_params['specialty'])) {
                  if (!in_array($input_params['specialty'], $specialty_tids)) {
                    $add = FALSE;
                  }
                }
              }

              if (!empty($input_params['fio'])) {
                if ($input_params['fio'] != $fio) {
                  $add = FALSE;
                }
              }

              if (!empty($input_params['from']) || !empty($input_params['to'])) {
                if (empty($doctor_label['specialist_dates']) && empty($input_params['fio'])) {
                  $add = FALSE;
                }
              }

              // If doctor item will add to $info array.
              if ($add) {
                if (!empty($input_params['fio'])) {
                  if ($input_params['fio'] !== $fio) {
                    $add = FALSE;
                  }
                }
                $doctor_label += $this->getDoctorNodeInfo($doctor_node, $specialty_options);
                $info['doctors'][$specialist] = $doctor_label;
              }
            }

            // If doctor node isn`t exists use specialist info from MIS.
            else {
              if (!empty($input_params['fio'])) {
                if ($input_params['fio'] !== $fio) {
                  $add = FALSE;
                }
              }

              if (!empty($input_params['specialty']) || !empty($input_params['service_specialty'])) {
                $add = FALSE;
              }

              if ($add) {
                $doctor_label['post'] = $value['description'];
                $info['doctors'][$specialist] = $doctor_label;
              }
            }

            if (!empty($input_params['selected_service_value']) && isset($info['doctors'][$specialist])) {
              $info['doctors'][$specialist]['selected_service'] = $input_params['selected_service_value'];
            }

            // If searched FIO is not empty, find closest time.
            if (!empty($input_params['fio'])) {
              if ($input_params['fio'] == $fio) {
                $params = [
                  'specialist' => $specialist,
                ];

                $closest_slot = $this->findClosestSlot($params);

                if (!empty($closest_slot['date'])) {
                  $info['doctors'][$specialist]['closest_day'] = strtotime($closest_slot['date']);
                }
                if (!empty($closest_slot['time'])) {
                  $time_exploded = explode('-', $closest_slot['time']);
                  $info['doctors'][$specialist]['closest_time'] = reset($time_exploded);
                }
                if (!empty($closest_slot['service_with_slot'])) {
                  $info['doctors'][$specialist]['service_with_slot'] = $closest_slot['service_with_slot'];
                }
              }
            }
          }
        }
      }
    }

    if (!empty($input_params['service_specialty'])) {
      $services_codes_by_doc_specialty_tid = $this->doctorService->getServiceCodesByDocSpecialty($input_params['service_specialty']);
    }

    foreach ($response['services'] as $service_item) {
      $service_code = $service_item['value'];
      $services_for_storage[$service_code] = $service_item;

      if (!empty($input_params['service']) && empty($input_params['title'])) {
        if ($service_code !== $input_params['service']) {
          continue;
        }
      }

      if (!empty($input_params['title'])) {
        if ($input_params['title'] !== $service_item['title']) {
          continue;
        }
      }

      if (!empty($input_params['category'])) {
        if ($service_item['category'] != $input_params['category']) {
          continue;
        }
      }

      if (!empty($input_params['service_specialty']) && !empty($services_codes_by_doc_specialty_tid)) {
        if (!in_array($service_code, $services_codes_by_doc_specialty_tid)) {
          continue;
        }
      }

      if (isset($services_dates_all[$service_code])) {
        $closest_date = min($services_dates_all[$service_code]);
        $service_item['closest_date'] = strtotime($closest_date);
      }

      $info['services_options'][$service_code] = $service_item['title'];
      $info['services'][$service_code] = $service_item;
    }

    $storage = $form_state->getStorage();
    if (empty($storage['services'])) {
      $storage['services'] = $services_for_storage;
      $form_state->setStorage($storage);
    }

    // Sort specialty alphabetically.
    asort($info['specialty']);

    // Sort services options alphabetically.
    asort($info['services_options']);

    $this->info = $info;
    return $info;
  }

  /**
   * Find closest slot by date and time.
   */
  public function findClosestSlot($params) {
    $data = [
      'time' => '',
      'date' => '',
    ];
    $result = $this->misClient->getSlotsToAppoint($params);

    $today = date('d.m.Y');

    if (!empty($result['slots'])) {
      foreach ($result['slots'] as $date => $item) {
        if ($date >= $today) {
          $params['date'] = date('Ymd', strtotime($date));
          break;
        }
      }

      $result = $this->misClient->getSlotsToAppoint($params);

      if (!empty($result['slots'])) {
        $first_day = reset($result['slots']);
        if (!empty($first_day['slot'])) {
          $first_slot = reset($first_day['slot']);

          $data['date'] = $first_slot['date'];
          $data['time'] = $first_slot['time'];
          $data['service_with_slot'] = $first_slot['service'];
        }
      }
    }

    return $data;
  }

  /**
   * Load more services Ajax response.
   *
   * @param mixed $form
   *   \Drupal\Core\Form\FormBase.
   * @param mixed $form_state
   *   \Drupal\Core\Form\FormStateInterface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Return services items.
   */
  public function loadMoreServicesAjaxCallback(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');

    $response = new AjaxResponse();

    $services = $this->info['services'];
    $services_codes = [];

    $services_chunks = array_chunk($services, self::SERVICES_CHUNKS_COUNT, TRUE);

    $count = count($services_chunks);

    if ($step <= $count - 1) {
      foreach ($services_chunks[$step] as $service_item) {
        $services_links[] = [
          '#theme' => 'med_mis_service_item',
          '#service_item' => $service_item,
          '#selected' => FALSE,
        ];

        if (!empty($service_item['closest_date'])) {
          $services_codes[] = $service_item['value'];
        }
      }

      if (!empty($services_links)) {
        $build = [
          '#theme' => 'med_mis_service_items',
          '#services_links' => $services_links,
        ];

        $response->addCommand(new AppendCommand('.selected-doctors_services-list', $build));
        $response->addCommand(new AddTimeToServicesCommand('.selected-doctors_services-list', $services_codes));
      }

      if ($step == $count - 1) {
        $response->addCommand(new HtmlCommand('.selected-services__load-more', ''));
      }
    }

    return $response;
  }

  /**
   * Find "field_doc_specialty" term tids from doctor node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Doctor node.
   *
   * @return array
   *   Term ids array.
   */
  public function getSpecialtyTids($node) {
    $specialty_tids = [];

    if ($node->hasField('field_doc_specialty') && !$node->get('field_doc_specialty')->isEmpty()) {
      foreach ($node->get('field_doc_specialty')->getValue() as $item) {
        $specialty_tids[] = $item['target_id'];
      }
    }

    return $specialty_tids;
  }

  /**
   * Get doctor days from BD from/to.
   */
  public function getSpecialistData($input_params) {
    $specialist_data = [];

    $query = $this->connection->select('med_mis_doctors_days__data', 'dd');
    $query->innerJoin('med_mis_doctors_days', 'd', 'dd.entity_id = d.id');
    $query->fields('d', ['specialist']);
    $query->fields('dd', ['data_service', 'data_date']);

    $from_input = !empty($input_params['from']) ? strtotime($input_params['from']) : '';
    $from = !empty($from_input) ? date('Y-m-d', strtotime($input_params['from'])) : date('Y-m-d', strtotime('tomorrow'));
    $query->condition('dd.data_date', $from, '>=');

    if (!empty($input_params['to'])) {
      $to_input = strtotime($input_params['to']);

      if (!empty($to_input)) {
        $to = date('Y-m-d', strtotime($input_params['to']));
        $query->condition('dd.data_date', $to, '<=');
      }
    }

    if (!empty($input_params['fio']) && $input_params['fio'] !== 'all') {
      $query->condition('d.specialist', $input_params['fio']);
    }

    if (!empty($input_params['specialty']) && $input_params['specialty'] !== 'all') {
      $query->innerJoin('node__field_doc_mis_code', 'mc', 'mc.field_doc_mis_code_value = d.specialist');
      $query->innerJoin('node__field_doc_specialty', 's', 'mc.entity_id = s.entity_id');
      $query->condition('s.field_doc_specialty_target_id', $input_params['specialty']);
    }

    $result = $query->execute()->fetchAll();

    if (!empty($result)) {
      foreach ($result as $item) {
        $specialist_data['specialist_dates_by_service'][$item->specialist][$item->data_service][] = $item->data_date;
        $specialist_data['specialist_dates'][$item->specialist][] = $item->data_date;
      }
    }

    return $specialist_data;
  }

  /**
   * Get info for docto item display.
   *
   * @param \Drupal\node\Node $node
   *   The node.
   * @param array $specialty_options
   *   The terms ids from "specialty" vocabulary.
   *
   * @return array
   *   An array suitable for \Drupal\Core\Render\RendererInterface::render().
   */
  public function getDoctorNodeInfo($node, $specialty_options) {
    $data = [];
    $data['fio'] = $node->label();
    $data['nid'] = $node->id();

    if (!$node->get('field_doc_photo')->isEmpty()) {
      $data['img'] = $this->doctorService->getDoctorImgUri($node);
    }

    if (!$node->get('field_doc_post')->isEmpty()) {
      foreach ($node->get('field_doc_post')->getValue() as $item) {
        $data['post'][] = $item['value'];
      }
    }

    if (!$node->get('field_doc_specialty')->isEmpty()) {
      foreach ($node->get('field_doc_specialty')->getValue() as $item) {
        $data['specialty'][] = $specialty_options[$item['target_id']];
      }
    }

    if (!$node->get('field_doc_regalia')->isEmpty()) {
      foreach ($node->get('field_doc_regalia')->getValue() as $item) {
        $data['regalia'][] = $item['value'];
      }
    }

    return $data;
  }

  /**
   * Get terms from "specialty" vid.
   *
   * @return array
   *   Terms array.
   */
  public function getSpecialtyOptions() {
    $options = [];
    $tree = $this->getSpecialtyVid();

    foreach ($tree as $term) {
      $options[$term->tid] = $term->name;
    }
    return $options;
  }

  /**
   * Clean all form ajax callbak.
   */
  public function clearFormAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $input = $form_state->getUserInput();

    foreach ($input as $key => $value) {
      if ($key !== 'tab') {
        unset($input[$key]);
      }
    }
    $form_state->setUserInput($input);
    $form_state->setStorage([]);

    $form['tab']['#default_value'] = $input['tab'];
    $form['specialist_filters']['fio']['#value'] = '';
    $form['specialist_filters']['fio']['#default_value'] = '';
    $form['specialist_filters']['date']['#value'] = '';
    $form['service_filters']['title']['#value'] = '';
    $form['specialist_filters']['specialty']['#value'] = 'all';
    $form['specialist_filters']['selected_service_value']['#value'] = '';
    $form['specialist_filters']['selected_service_value']['#default_value'] = '';
    $form['service_filters']['service_specialty']['#value'] = 'all';
    $form['service_filters']['category']['#value'] = 'all';

    $this->getRequest()->query->remove('service');

    $this->info = $this->getInfo($form_state);

    $form_state->cleanValues();
    $form_state->setRebuild();

    $response->addCommand(new ReplaceCommand('form.med-lk-registry-form', $form));

    $url_input_params = $this->prepareInputParamsForUrl($form_state);

    $response->addCommand(new SetUrlParamsCommand($url_input_params));

    return $response;
  }

  /**
   * Select doctors from service code.
   */
  public function getDoctorItemsFromService($selected_service) {
    $doctor_items = [];
    $params['service'] = $selected_service;
    $result = $this->misClient->getSlotsToAppoint($params);

    if (!empty($result['filters'])) {
      foreach ($result['filters'] as $filters) {
        if ($filters['code'] == 'specialist') {
          foreach ($filters['data'] as $item) {
            $specialist = $item['value'];

            if (!empty($this->info['doctors'][$specialist])) {
              $doctor_label = $this->info['doctors'][$specialist];
            }

            $doctor_label['selected_service'] = $selected_service;

            $doctor_items[$specialist] = [
              '#theme' => 'med_mis_doctor_item',
              '#doctor_item' => $doctor_label,
            ];
          }
        }
      }
    }

    return $doctor_items;
  }

  /**
   * Update all form ajax callbak.
   */
  public function selectServiceAjaxCallback(array &$form, FormStateInterface $form_state) {
    $selected_service = $form_state->getUserInput()['selected_service_value'];

    $storage = $form_state->getStorage();

    $service_info = [];
    if (!empty($storage['services'][$selected_service])) {
      $service_info = $storage['services'][$selected_service];
    }

    $form['selected_services']['info']['#markup'] = $this->medMisServiceHelper->checkServicePrepareInfoByService($selected_service);

    $form['selected_services']['selected_service_markup']['#theme'] = 'med_mis_service_item';
    $form['selected_services']['selected_service_markup']['#selected'] = TRUE;
    $form['selected_services']['selected_service_markup']['#service_item'] = $service_info;
    $form['service_filters']['title']['#value'] = $service_info['title'];

    // Get doctors with selected service.
    $doctor_items = $this->getDoctorItemsFromService($selected_service);

    $form['selected_services']['service_doctors'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="selected-services__doctors-list row">{% for item in items %} <div class="selected-services__item col-lg-6">{{ item }}</div> {% endfor %}</div>',
      '#context' => [
        'items' => $doctor_items,
      ],
    ];

    $response = new AjaxResponse();

    $response->addCommand(new ReplaceCommand('.selected-services__prepare-service-info', $form['selected_services']['info']));

    $response->addCommand(new ReplaceCommand('.selected-services__selected-service-markup', $form['selected_services']['selected_service_markup']));
    $response->addCommand(new HtmlCommand('.selected-services__service-doctors', $form['selected_services']['service_doctors']));
    $response->addCommand(new HtmlCommand('.selected-services__services-list', ''));
    $response->addCommand(new HtmlCommand('.selected-services__load-more', ''));
    $response->addCommand(new ReplaceCommand('.form-item-title', $form['service_filters']['title']));

    $response->addCommand(new AddTimeToServicesCommand('.selected-doctors_services-list', [$selected_service]));

    $url_input_params = $this->prepareInputParamsForUrl($form_state);

    $response->addCommand(new SetUrlParamsCommand($url_input_params));

    return $response;
  }

  /**
   * Get taxonomy tree from 'specialty' vocabulary.
   */
  public function getSpecialtyVid() {
    return $this->entityTypeManager->getStorage('taxonomy_term')->loadTree(self::SPECIALTY_VID, 0, 1);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $trigger = $form_state->getTriggeringElement();
    if (!empty($trigger['#ajax']['callback']) && $trigger['#ajax']['callback'] == '::loadMoreServicesAjaxCallback') {

      $form_state->set('step', $form_state->get('step') + 1);
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Update all form ajax callbak.
   */
  public function updateFormAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $response->addCommand(new ReplaceCommand($this->getFormSelector(), $form));

    $url_input_params = $this->prepareInputParamsForUrl($form_state);
    $response->addCommand(new SetUrlParamsCommand($url_input_params));

    return $response;
  }

  /**
   * Get input params from get parameters.
   */
  public function prepareInputParamsForUrl($form_state) {
    $url_input_params = $this->getUserInputParams($form_state);

    $active_tab = $url_input_params['tab'];
    $url_params = self::URL_PARAMS_BY_TABS[$active_tab];

    foreach ($url_input_params as $key => $value) {
      if ($key !== 'tab' && !in_array($key, $url_params)) {
        unset($url_input_params[$key]);
      }

      if ($key == 'selected_service_value') {
        $url_input_params['service'] = $value;
        unset($url_input_params[$key]);
      }
    }

    if (!empty($url_input_params['fio'])) {
      if (!empty($this->info['doctors'])) {
        $url_input_params['specialist'] = reset($this->info['doctors'])['specialist'];
      }
      unset($url_input_params['fio']);
    }

    if (!empty($url_input_params['title'])) {
      if (!empty($this->info['services'])) {
        $url_input_params['service'] = reset($this->info['services'])['value'];
      }
      unset($url_input_params['title']);
    }

    if (!empty($url_input_params['date'])) {
      $dates = explode('-', $url_input_params['date']);
      if (!empty($dates)) {
        foreach ($dates as $key => $date) {
          $dates[$key] = trim($date);
        }

        $url_input_params['from'] = !empty($dates[0]) ? $dates[0] : date('d.m.Y');
        $url_input_params['to'] = !empty($dates[1]) ? $dates[1] : date('d.m.Y', strtotime(' +2month'));
      }
      unset($url_input_params['date']);
    }

    if (empty($url_input_params['tab'])) {
      $url_input_params['tab'] = $active_tab;
    }

    return $url_input_params;
  }

  /**
   * Get query parameter by it`s name.
   *
   * @param string $parameter_name
   *   The parameter name.
   *
   * @return string
   *   The parameter value.
   */
  public function getQuery($parameter_name) {
    return $this->getRequest()->query->get($parameter_name);
  }

  /**
   * Redirect on default route.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object.
   */
  public function redirectOnDefaultTab($options = []) {
    $route = $this->getRouteMatch()->getRouteName();
    if (!empty($options)) {
      $redirect = $this->redirect($route, $options);
    }
    else {
      $redirect = $this->redirect($route);
    }
    $redirect->send();
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

}
