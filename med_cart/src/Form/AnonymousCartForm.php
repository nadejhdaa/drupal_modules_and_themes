<?php

declare(strict_types=1);

namespace Drupal\med_cart\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\med_mis\Client\MisClient;
use Drupal\med_content\DoctorService;
use Drupal\med_mis\MisData;
use Drupal\med_cart\CartManager;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\med_service\EncMisServiceHelper;
use Drupal\med_mis\Utility\Utility;
use Drupal\med_cart\Utility\Utility as CartUtility;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a ENC content form.
 */
final class AnonymousCartForm extends FormBase implements CartFormInterface {

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
   * The specialist mis code.
   *
   * @var string
   */
  protected $specialist;

  /**
   * The service mis code.
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
   * The services array.
   *
   * @var array
   */
  protected $services;

  /**
   * The services options for select element.
   *
   * @var array
   */
  protected $serviceOptions;

  /**
   * The days info.
   *
   * @var array
   */
  protected $daysSettings;

  /**
   * The slots info.
   *
   * @var array
   */
  protected $slots;

  /**
   * The med_mis_service_helper service.
   *
   * @var \Drupal\med_service\EncMisServiceHelper
   */
  protected $medMisServiceHelper;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an AnonymousCartForm object.
   */
  public function __construct(
    MisData $mis_data,
    CartManager $cart_manager,
    MisClient $mis_client,
    DoctorService $doctor_service,
    FormBuilder $form_builder,
    EncMisServiceHelper $med_mis_service_helper,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->misData = $mis_data;
    $this->cartManager = $cart_manager;
    $this->misClient = $mis_client;
    $this->doctorService = $doctor_service;
    $this->formBuilder = $form_builder;
    $this->medMisServiceHelper = $med_mis_service_helper;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_data'),
      $container->get('med_cart.cart_manager'),
      $container->get('mis_client'),
      $container->get('doctor_service'),
      $container->get('form_builder'),
      $container->get('med_service.med_mis_service_helper'),
      $container->get('entity_type.manager'),
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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div class="#med-doctor-cart-page-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#id'] = 'med-doctor-cart-page-form';

    $items = $this->cartManager->cartItems();
    $total = 0;

    if (!empty($items)) {
      $form['items'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];

      uasort($items, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

      foreach ($items as $item) {
        $service = !empty($item['service']) ? $item['service'] : '';
        $specialist = !empty($item['qqc244']) ? $item['qqc244'] : '';

        if (!empty($item['price'])) {
          $total += $item['price'];
        }

        $doctor_service = \Drupal::service('doctor_service'); // phpcs:ignore
        $node = $doctor_service->loadDoctorByMisCode($specialist);

        if ($node) {
          $item['url'] = Url::fromRoute('entity.node.canonical', ['node' => $node->id()]);
        }

        if (!empty($item['item_id'])) {
          $item_id = $item['item_id'];
        }
        else {
          $item_id = $service . '__' . $specialist;
          if (!empty($item['date'])) {
            $item_id .= '__' . $item['date'];
          }

          if (!empty($item['time'])) {
            $item_id .= '__' . $item['time'];
          }
        }

        $type = $this->medMisServiceHelper->checkServiceTypeByService($service);

        $cart_items[$type][$item_id] = [
          '#type' => 'item',
          '#theme' => 'cart_item',
          '#slot' => $item,
          '#prefix' => '<div data-cart-item="' . $item_id . '" class="med-lk-cart-form__cart-item">',
          '#suffix' => '</div>',
        ];
      }

      $form['#count'] = count($items);
      $form['#total'] = $total;

      $types = array_keys($cart_items);

      if (count($types) > 1 && $types[0] == 'med') {
        $types = array_reverse($types);
      }

      foreach ($types as $type) {
        $med = $this->t('In th ENC');
        $home = $this->t('At home');
        $type_options[$type] = $type == 'med' ? $med : $home;
      }

      $form['types'] = [
        '#type' => 'container',
      ];

      $form['types']['select_type'] = [
        '#type' => 'radios',
        '#options' => $type_options,
        '#default_value' => reset($types),
      ];

      $form['types']['med'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            ':input[name="types[select_type]"]' => [
              'value' => 'med',
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
        'login' => [
          '#markup' => $this->buildLoginLink(),
        ],
      ];

      $form['types']['home'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            ':input[name="types[select_type]"]' => [
              'value' => 'home',
            ],
          ],
        ],

        'login' => [
          '#markup' => $this->buildLoginLink(),
        ],
      ];

      foreach ($types as $type) {
        $form['items'][$type] = [
          '#type' => 'container',
          '#states' => [
            'visible' => [
              ':input[name="types[select_type]"]' => [
                'value' => $type,
              ],
            ],
          ],
        ];

        if (!empty($cart_items[$type])) {
          $med_msg = self::ENC_MSG;
          $home_msg = self::HOME_MSG;

          $form['items'][$type]['description'] = [
            '#markup' => $type == 'med' ? Markup::create($this->t($med_msg)) : Markup::create($this->t($home_msg)), // phpcs:ignore
          ];

          $form['items'][$type]['items'] = $cart_items[$type];
        }
      }
    }

    else {
      $form['items']['empty'] = [
        '#type' => 'item',
        '#markup' => CartUtility::buildEmptyCartMarkup(),
      ];
    }

    if (!empty($items)) {
      $form['actions'] = [
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Place an order'),
          '#attributes' => [
            'class' => ['btn', 'btn-primary'],
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
   * Clear cart ajax callback.
   */
  public function clearCartAjaxCallback(array &$form, FormStateInterface $form_state) {
    $this->cartManager->clearCart();

    $response = new AjaxResponse();

    $response->addCommand(new ReplaceCommand('.block-med-cart-top .count', '<div class="count"></div>'));

    $markup = CartUtility::buildEmptyCartMarkup();
    $response->addCommand(new ReplaceCommand('.med-doctor-cart-form', $markup));

    return $response;
  }

  /**
   * Build the user login link.
   */
  public function buildLoginLink() {
    $domains = $this->entityTypeManager->getStorage('domain')->loadByProperties([
      'is_default' => FALSE,
    ]);

    $url = reset($domains)->getPath();
    $url = trim('/', $url);

    $options = [
      'attributes' => [
        'class' => ['button'],
      ],
      'base_url' => $url,
    ];

    $login_link = Link::createFromRoute($this->t('Sign in personal account'), 'esia_authorization.login', [], $options);
    $login_link = $login_link->toString();

    $login_msg = self::LOGIN_MSG;
    return new TranslatableMarkup('<div>@msg</div> @login_link', [
      '@msg' => $this->t($login_msg), // phpcs:ignore
      '@login_link' => $login_link,
    ]);
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
    $webform_url = Url::fromRoute('entity.webform.canonical', ['webform' => Utility::WEBFORM_ORDER]);
    $form_state->setRedirectUrl($webform_url);
  }

}
