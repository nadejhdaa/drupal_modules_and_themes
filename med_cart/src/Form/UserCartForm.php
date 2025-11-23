<?php

declare(strict_types=1);

namespace Drupal\med_cart\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\med_mis\Client\MisClient;
use Drupal\med_mis\Utility\Utility;
use Drupal\med_cart\SlotManager;
use Drupal\med_cart\Utility\Utility as CartUtility;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\med_service\EncMisServiceHelper;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Element;
use Drupal\med_lk\UserService;

/**
 * Provides an authorized user cart.
 */
final class UserCartForm extends FormBase implements CartFormInterface {

  /**
   * The "mis_client" service.
   *
   * @var \Drupal\med_mis\Client\MisClient
   */
  protected $misClient;

  /**
   * The "cache.data" service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The "med_service.med_mis_service_helper" service.
   *
   * @var \Drupal\med_service\EncMisServiceHelper
   */
  protected $medMisServiceHelper;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The slot manager.
   *
   * @var \Drupal\med_cart\SlotManager
   */
  protected $slotManager;

  /**
   * The 'med_lk.user_service' service.
   *
   * @var \Drupal\med_lk\UserService
   */
  protected $userService;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'med_lk_cart_form';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    MisClient $mis_client,
    CacheBackendInterface $cache_backend,
    EncMisServiceHelper $med_mis_service_helper,
    RendererInterface $renderer,
    SlotManager $slot_manager,
    UserService $user_service,
  ) {
    $this->misClient = $mis_client;
    $this->cacheBackend = $cache_backend;
    $this->medMisServiceHelper = $med_mis_service_helper;
    $this->renderer = $renderer;
    $this->slotManager = $slot_manager;
    $this->userService = $user_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_client'),
      $container->get('cache.data'),
      $container->get('med_service.med_mis_service_helper'),
      $container->get('renderer'),
      $container->get('med_cart.slot_manager'),
      $container->get('med_lk.user_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#id'] = '->med-lk-user-cart-form';
    $input = $form_state->getUserInput();

    $qqc153 = $this->userService->getQqc153();

    $result = $this->misClient->getCart($qqc153);

    $slots = !empty($result['slots']) ? $result['slots'] : [];

    if (!empty($slots)) {
      $form['search'] = [
        '#type' => 'textfield',
        '#attributes' => [
          'placeholder' => $this->t('Quick search'),
        ],
        '#autocomplete_route_name' => 'med_cart.cart_service_search_autocomplete',
        '#ajax' => [
          'callback' => '::updateFormAjaxCallback',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Loading data...'),
          ],
          'event' => 'autocompleteclose',
        ],
      ];

      $form_state->set('slots', $slots);

      $form['selected_items'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['selected-items'],
        ],
      ];

      if (!empty($input['search'])) {
        $search_string = $input['search'];
        $search_data = explode(' | ', $search_string);
      }

      $form['items'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];

      uasort($slots, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

      $selected_exists = FALSE;
      foreach ($slots as $slot) {
        $specialist = $slot['qqc244to'];
        $service = $slot['service'];

        $doctor_service = \Drupal::service('doctor_service'); // phpcs:ignore
        $node = $doctor_service->loadDoctorByMisCode($specialist);

        if ($node) {
          $slot['url'] = Url::fromRoute('entity.node.canonical', ['node' => $node->id()]);
        }

        $type = $this->slotManager->slotIsDistant($slot) ? 'home' : '->med';

        $prepare_info = $this->medMisServiceHelper->checkServicePrepareInfoByService($service);

        if (!empty($prepare_info)) {
          $prepare_info_all[$type][$prepare_info['type']] = $prepare_info['markup'];
        }

        $item_id = $service . '__' . $specialist . '__' . $slot['date'] . '__' . $slot['time'];

        $selected = FALSE;

        if (!empty($search_data)) {
          if ($search_data[0] == $slot['title'] && $search_data[1] == $slot['pAz']) {
            $form['selected_items'][] = [
              '#type' => 'item',
              '#theme' => 'cart_item',
              '#slot' => $slot,
              '#prefix' => '<div data-cart-item="' . $item_id . '" class="->med-lk-cart-form__cart-item">',
              '#suffix' => '</div>',
            ];

            $selected = TRUE;
            $selected_exists = TRUE;
          }
        }

        if (!$selected) {
          $items[$type][$item_id] = [
            '#type' => 'item',
            '#theme' => 'cart_item',
            '#slot' => $slot,
            '#prefix' => '<div data-cart-item="' . $item_id . '" class="->med-lk-cart-form__cart-item">',
            '#suffix' => '</div>',
          ];
        }
      }

      if ($selected_exists) {
        $form['selected_items']['#attributes']['class'][] = 'selected-items--finded';
      }

      if (!empty($items)) {
        $types = array_keys($items);

        if (count($types) > 1 && $types[0] == '->med') {
          $types = array_reverse($types);
        }

        foreach ($types as $type) {
          $med = $this->t('In th ENC');
          $home = $this->t('At home');
          $type_options[$type] = $type == '->med' ? $med : $home;
        }

        $form['types'] = [
          '#type' => 'container',
        ];

        $form['types']['select_type'] = [
          '#type' => 'radios',
          '#options' => $type_options,
          '#default_value' => reset($types),
          '#prefix' => '<div class="->med-lk-cart-form__select-type">',
          '#suffix' => '</div>',
        ];

        $form['types']['->med'] = [
          '#type' => 'container',
          '#states' => [
            'visible' => [
              ':input[name="select_type"]' => [
                'value' => '->med',
              ],
            ],
          ],
          'address' => [
            '#theme' => 'item_list',
            '#items' => [
              Markup::create(Utility::getAddress1()),
              Markup::create(Utility::getAddress2()),
            ],
          ],
        ];

        $form['types']['home'] = [
          '#type' => 'container',
          '#states' => [
            'visible' => [
              ':input[name="select_type"]' => [
                'value' => 'home',
              ],
            ],
          ],
        ];

        foreach ($types as $type) {
          $form['items'][$type] = [
            '#type' => 'container',
            '#states' => [
              'visible' => [
                ':input[name="select_type"]' => [
                  'value' => $type,
                ],
              ],
            ],
          ];

          if (!empty($prepare_info_all[$type])) {
            $form['items'][$type]['prepare_info'] = [
              '#type' => 'container',
              '#theme' => 'item_list',
              '#items' => $prepare_info_all[$type],
            ];
          }

          if (!empty($items[$type])) {
            $another_type = $type == '->med' ? 'home' : '->med';

            if (!empty($items[$another_type])) {
              $form['items'][$type]['other_services'] = [
                '#type' => 'container',
                '#attributes' => [
                  'class' => [
                    'other-services',
                    'bg-secondary',
                    'py-3',
                    'px-4',
                  ],
                ],
              ];

              $form['items'][$type]['other_services']['other_services_description'] = [
                '#markup' => $type == '->med' ? Markup::create($this->t(self::ENC_MSG)) : Markup::create($this->t(self::HOME_MSG)),  // phpcs:ignore
              ];

              $form['items'][$type]['other_services']['other_services_items'] = $items[$another_type];
              $form['items'][$type]['other_services']['remove'] = [
                '#type' => 'button',
                '#value' => $this->t('Remove these services'),
                '#ajax' => [
                  'callback' => '::removeItemsByTypeAjaxCallback',
                ],
              ];
            }

            $form['items'][$type]['items'] = $items[$type];
          }
        }
      }
    }

    else {
      $form['rows']['empty'] = [
        '#type' => 'item',
        '#markup' => CartUtility::buildEmptyCartMarkup(),
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    if (!empty($slots)) {
      $form['count'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('<span class="user-cart__count__label">Services count</span>: <span class="user-cart__count__value">@count</span> pc.', ['@count' => $result['slotsTotal']]),
        '#attributes' => [
          'class' => ['user-cart__count'],
        ],
      ];

      $form['total'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('<span class="user-cart__total__label">Total</span>: <span class="user-cart__total__value">@price</span> ₽', [
          '@price' => number_format($result['priceTotal'], 0, '.', ' '),
        ]),
        '#attributes' => [
          'class' => ['user-cart__total'],
        ],
      ];

      $form['actions'] = [
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Place an order'),
          '#attributes' => [
            'class' => ['btn', 'btn-primary'],
          ],
          '#ajax' => [
            'callback' => '::appointCartAjaxCallback',
            'progress' => [
              'type' => 'throbber',
              'message' => $this->t('The registration of appointments is in progress...'),
            ],
          ],
        ],
        'clear' => [
          '#type' => 'submit',
          '#value' => $this->t('Clear cart'),
          '#ajax' => [
            'callback' => '::clearCartAjaxCallback',
            'progress' => [
              'type' => 'throbber',
              'message' => $this->t('Clearing data...'),
            ],
          ],
        ],
      ];

      $form['clear_cart'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clear cart'),
        '#ajax' => [
          'callback' => '::clearCartAjaxCallback',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Clearing data...'),
          ],
        ],
      ];
    }

    $form['#theme'] = 'cart_form';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * Remove items by type ajax callback.
   */
  public function removeItemsByTypeAjaxCallback(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $response = new AjaxResponse();

    if (!empty($input['select_type'])) {
      $types = array_keys($form['types']['select_type']['#options']);

      $slots = $form_state->getStorage()['slots'];
      $type = $input['select_type'];

      foreach ($types as $key => $value) {
        if ($type !== $value) {
          unset($types[$key]);
          $another_type = $value;
        }
      }

      unset($types[$type]);

      $upd_types = $form['types']['select_type']['#options'];
      unset($upd_types[$another_type]);
      $form['types']['select_type']['#options'] = $upd_types;

      $count = count($slots);
      $total = 0;
      foreach (Element::children($form['items'][$type]['other_services']['other_services_items']) as $id) {
        $arr = explode('__', $id);

        foreach ($slots as $key => $slot) {
          if ($slot['service'] == $arr[0] && $slot['qqc244'] == $arr[1] && $slot['date'] == $arr[2] && $slot['time'] == $arr[3]) {
            $this->misClient->deleteFromCart($slot['service'], $slot['date'], $slot['time'], $slot['qqc244to']);
            $count--;
          }
          else {
            $total += $slot['price'];
          }
        }
      }
    }

    $response->addCommand(new RemoveCommand('form.enc-lk-cart-form .other-services'));
    $response->addCommand(new RemoveCommand('div.form-item-select-type:has(input[data-drupal-selector="edit-select-type-' . $another_type . '"])'));
    $response->addCommand(new HtmlCommand('.enc-lk-cart-form .user-cart__count__value', $count));
    $response->addCommand(new HtmlCommand('.enc-lk-cart-form .user-cart__total__value', number_format($total, 0, ',', ' ')));

    return $response;
  }

  /**
   * Update form Ajax callback.
   */
  public function updateFormAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('form.med-lk-cart-form', $form));

    return $response;
  }

  /**
   * Appoint all cart items ajax callback.
   */
  public function appointCartAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $slots = $form_state->get('slots');

    $errors = [];
    $success = [];

    $result = $this->misClient->getCart();

    if (!empty($result['slots'])) {
      $slots = $result['slots'];

      foreach ($slots as $slot) {
        $slot_is_outdated = FALSE;

        // If slot`s date is outdated.
        if (!empty($slot['date'])) {
          if (date('d.m.Y') > $slot['date']) {
            $slot_is_outdated = TRUE;
          }
        }

        $result = $this->misClient->appointCart([$slot]);

        // If the result is not successful.
        if (empty($result['slots']) || $outdated) {
          $service = $slot['service'];
          $date = $slot['date'];
          $time = $slot['time'];
          $specialist = $slot['qqc244to'];

          $slot_info_str = $this->t('The "@title, specialist @specialist, @date, @time" service', [
            '@title' => $slot['title'],
            '@specialist' => $slot['pAz'],
            '@date' => $slot['date'],
            '@time' => $slot['time'],
          ]);

          if ($outdated) {
            $errors[] = $this->t('@slot_info_str is outdated and has been removed from cart', ['@slot_info_str' => $slot_info_str]);
          }
          else {
            $errors[] = !empty($result['error']) ? $result['error'] : $this->t('@slot_info_str can`t be appointed and has been removed from cart', ['@slot_info_str' => $slot_info_str]);
          }

          $result = $this->misClient->deleteFromCart($service, $date, $time, $specialist);
        }

        // If the slot has been successfully apoointed.
        else {
          $success[] = new TranslatableMarkup('The "@title, specialist @specialist, @date, @time" service has been successfully completed', [
            '@title' => $slot['title'],
            '@specialist' => $slot['pAz'],
            '@date' => $slot['date'],
            '@time' => $slot['time'],
          ]);
        }

        $type = $this->slotManager->slotIsDistant($slot) ? 'home' : 'med';
        $item_id = $service . '__' . $specialist . '__' . $slot['date'] . '__' . $slot['time'];

        unset($form['items'][$type]['items'][$item_id]);
      }

      if (!empty($success)) {
        $msg_success = [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => $success,
        ];

        $msg_success = $this->renderer->render($msg_success);
        $response->addCommand(new MessageCommand($msg_success));
      }

      if (!empty($errors)) {
        $msg_error = [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => $errors,
        ];

        $msg_error = $this->renderer->render($msg_error);
        $response->addCommand(new MessageCommand($msg_error, NULL, ['type' => 'error'], FALSE));
      }

      // Check user cart.
      $result = $this->misClient->getCart();

      if (empty($result['slots'])) {
        $empty_msg = CartUtility::buildEmptyCartMarkup();
        $response->addCommand(new HtmlCommand('.cart__main-content', $empty_msg));

        $response->addCommand(new RemoveCommand('.cart__right-col__content'));

        $response->addCommand(new ReplaceCommand('.block-med-cart-top .count', '<span class="count"></span>'));
      }
      else {
        $response->addCommand(new ReplaceCommand('.block-med-cart-top .count', '<span class="count count--positive">' . count($result['slots']) . '</span>'));
      }
    }

    return $response;
  }

  /**
   * Clear cart ajax callback.
   */
  public function clearCartAjaxCallback(array &$form, FormStateInterface $form_state) {
    $result = $this->misClient->getCart();

    if (!empty($result['slots'])) {
      foreach ($result['slots'] as $slot) {
        $service = $slot['service'];
        $date = $slot['date'];
        $time = $slot['time'];
        $specialist = $slot['qqc244to'];

        $this->misClient->deleteFromCart($service, $date, $time, $specialist);
      }
    }

    $response = new AjaxResponse();

    $empty_msg = CartUtility::buildEmptyCartMarkup();
    $response->addCommand(new HtmlCommand('.cart__main-content', $empty_msg));

    $response->addCommand(new RemoveCommand('.cart__right-col__content'));

    return $response;
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

}
