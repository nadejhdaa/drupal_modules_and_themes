<?php

declare(strict_types=1);

namespace Drupal\demo_cart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\demo_is\TestData;
use Drupal\demo_is\Client\TestClient;
use Symfony\Component\DependtestyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\demo_service\testTestServiceHelper;
use Drupal\Core\Link;
use Drupal\Core\Ajax\HtmlCommand;

/**
 * Returns responses for test cart routes.
 */
final class AddToCartController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly TestData $testData,
    private readonly TestClient $testClient,
    private readonly CartManager $cartManager,
    private readonly testTestServiceHelper $testTestServiceHelper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('demo_data'),
      $container->get('demo_client'),
      $container->get('demo_cart.cart_manager'),
      $container->get('demo_service.demo_demo_service_helper'),
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

      if (!$node->get('field_demo_service_value')->isEmpty()) {
        $item_id .= '__' . $node->field_demo_service_value->value;
      }

      $price = !$node->get('field_demo_service_price')->isEmpty() ? $node->field_demo_service_price->value : $node->field_service_price->value;
      $service_value = !$node->get('field_demo_service_value')->isEmpty() ? $node->field_demo_service_value->value : '';

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

      $delete_link = Link::createFromRoute($this->t('Remove'), 'demo_cart.cart_delete', ['item_id' => $item_id], $options);
      $delete_link = $delete_link->toString();

      $response->addCommand(new HtmlCommand($selector, $delete_link));

      $count = $result['count'];
      $response->addCommand(new HtmlCommand('.block-test-cart-top .count', $count));

      return $response;
    }
  }

}
