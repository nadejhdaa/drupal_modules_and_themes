<?php

declare(strict_types=1);

namespace Drupal\med_cart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\med_mis\MisData;
use Drupal\med_mis\Client\MisClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\med_service\EncMisServiceHelper;
use Drupal\Core\Link;
use Drupal\Core\Ajax\HtmlCommand;

/**
 * Returns responses for Enc cart routes.
 */
final class AddToCartController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly MisData $misData,
    private readonly MisClient $misClient,
    private readonly CartManager $cartManager,
    private readonly EncMisServiceHelper $medMisServiceHelper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mis_data'),
      $container->get('mis_client'),
      $container->get('med_cart.cart_manager'),
      $container->get('med_service.med_mis_service_helper'),
    );
  }

  /**
   * Builds the response.
   */
  public function addCartItem(Request $request) {
    $response = new AjaxResponse();
    $id = $request->get('id');

    if (!empty($id)) {
      $this->entityTypeManager->getStorage('node')->load($id);

      $item_id = $id;

      if (!$node->get('field_service_code')->isEmpty()) {
        $item_id .= '__' . $node->field_service_code->value;
      }

      if (!$node->get('field_mis_service_value')->isEmpty()) {
        $item_id .= '__' . $node->field_mis_service_value->value;
      }

      $price = !$node->get('field_mis_service_price')->isEmpty() ? $node->field_mis_service_price->value : $node->field_service_price->value;
      $service_value = !$node->get('field_mis_service_value')->isEmpty() ? $node->field_mis_service_value->value : '';

      $slot = [
        'code' => $node->field_service_code->value,
        'title' => $node->label(),
        'price' => $price,
        'service' => $service_value,
        'id' => $id,
        'item_id' => $item_id,
        'date' => date('d.m.Y', strtotime('tomorrow')),
      ];

      $result = $this->cartManager->addToCart($slot, $item_id);

      $selector = 'div[data-drupal-selector="edit-rows-' . $id . '"] .services-list__item__add-link';

      $options = [
        'attributes' => [
          'class' => ['button', 'button--selected', 'use-ajax'],
        ],
      ];

      $delete_link = Link::createFromRoute($this->t('Remove'), 'med_cart.cart_delete', ['item_id' => $item_id], $options);
      $delete_link = $delete_link->toString();

      $response->addCommand(new HtmlCommand($selector, $delete_link));

      $count = $result['count'];
      $response->addCommand(new HtmlCommand('.block-med-cart-top .count', $count));

      return $response;
    }
  }

}
