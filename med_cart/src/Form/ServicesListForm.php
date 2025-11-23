<?php

declare(strict_types=1);

namespace Drupal\med_cart\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\med_cart\CartManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a Services list form.
 */
final class ServicesListForm extends FormBase {

  /**
   * The cart_manager service.
   *
   * @var \Drupal\med_cart\CartManager
   */
  protected $cartManager;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct ServicesListForm.
   */
  public function __construct(
    CartManager $cart_manager,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->cartManager = $cart_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('med_cart.cart_manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'med_services_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;

    if ($this->getRouteMatch()->getRouteName() == 'entity.taxonomy_term.canonical') {
      $term_id = $this->getRouteMatch()->getRawParameter('taxonomy_term');

      $services = $this->getServices($term_id);

      $cart_items = $this->cartManager->cartItems();

      if (!empty($services)) {
        $form['search'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Search by title'),
          '#placeholder' => $this->t('Start typing the title'),
          '#title_display' => 'inline',
        ];

        $form['rows'] = [
          '#type' => 'container',
        ];

        foreach ($services as $id => $service) {
          $code = $service->field_service_code->value;
          $price = !$service->get('field_mis_service_price')->isEmpty() ? $service->field_mis_service_price->value : $service->field_service_price->value;
          $service_value = !$service->get('field_mis_service_value')->isEmpty() ? $service->field_mis_service_value->value : '';

          $item_id = $id . '__' . $code;
          if (!empty($service_value)) {
            $item_id .= '__' . $service_value;
          }

          $service_data = [
            'code' => $code,
            'title' => $service->label(),
            'price' => $price,
            'service' => $service_value,
            'id' => $id,
            'item_id' => !empty($cart_items[$item_id]) ? $item_id : FALSE,
            'date' => date('d.m.Y', strtotime('tomorrow')),
          ];

          $form['rows'][$id] = [
            '#type' => 'container',
            'service' => [
              '#theme' => 'med_cart_services_list_item',
              '#service' => $service_data,
            ],
          ];
        }
      }
    }

    return $form;
  }

  /**
   * Add service ajax callback.
   */
  public function addServiceAjaxCallback(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $data = $triggering_element['#attributes']['data-service'];
    $data = json_decode($data, TRUE);

    $id = $data['id'];

    $item_id = $id . '__' . $data['code'];
    if (!empty($data['service'])) {
      $item_id .= '__' . $data['service'];
    }

    $this->cartManager->addToCart($data, $item_id);

    $response = new AjaxResponse();

    $selector = 'div[data-drupal-selector="edit-rows-' . $id . '"] .add-button';

    $form['rows'][$id]['add']['#value'] = $this->t('Delete');
    $form['rows'][$id]['add']['#attributes']['class'][] = 'button--selected';

    $response->addCommand(new HtmlCommand($selector, $form['rows'][$id]['add']));

    return $response;
  }

  /**
   * Get service by term.
   */
  public function getServices($term_id) {
    return $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'service',
      'status' => '1',
      'field_service_section' => $term_id,
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

  }

}
