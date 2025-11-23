<?php

declare(strict_types=1);

namespace Drupal\med_cart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\med_mis\MisData;
use Drupal\med_mis\Client\MisClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\med_cart\CartManager;
use Drupal\med_service\EncMisServiceHelper;
use Drupal\Core\Link;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\med_cart\Utility\Utility as CartUtility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns responses for Enc cart routes.
 */
final class DeleteCartController extends ControllerBase {

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
  public function deleteCartItem(Request $request) {
    if (empty($request->get('_wrapper_format'))) {
      throw new AccessDeniedHttpException();
    }

    $response = new AjaxResponse();

    $service = $request->get('service');
    $item_id = $request->get('item_id');

    if (!empty($item_id)) {
      $this->cartManager->removeItem($item_id);

      $cart_items = $this->cartManager->cartItems();
      $count = count($cart_items);

      $id = explode('__', $item_id);
      $id = reset($id);

      $selector = 'div[data-drupal-selector="edit-rows-' . $id . '"] .services-list__item__add-link';

      $options = [
        'attributes' => [
          'class' => ['button', 'use-ajax'],
        ],
      ];

      $add_link = Link::createFromRoute($this->t('Add'), 'med_cart.add_cart_item', ['id' => $id], $options);
      $add_link = $add_link->toString();

      $response->addCommand(new HtmlCommand($selector, $add_link));

      $selector = '.block-med-cart-top .count--positive';
      $response->addCommand(new HtmlCommand($selector, $count));
    }

    if (!empty($service)) {
      $service = urldecode($service);
      $item_id = $service;

      $current_type = $this->medMisServiceHelper->checkServiceTypeByService($service);

      $specialist = $request->get('specialist') ? urldecode($request->get('specialist')) : '';
      if ($specialist) {
        $item_id .= '__' . $specialist;
      }

      $date = $request->get('date') ? $request->get('date') : '';
      if ($date) {
        $item_id .= '__' . $date;
      }

      $time = $request->get('time') ? $request->get('time') : '';
      if ($time) {
        $item_id .= '__' . $time;
      }

      // If user is Anonymous delete slotes from session.
      if ($this->currentUser()->isAnonymous()) {
        $this->cartManager->removeItem($item_id);

        $cart_items = $this->cartManager->cartItems();

        foreach ($cart_items as $cart_item) {
          $cart_item_service = $cart_item['service'];
          $type = $this->medMisServiceHelper->checkServiceTypeByService($cart_item_service);

          if ($type == $current_type) {
            $same_types[] = $type;
          }
        }

        if (empty($same_types)) {
          $clear_cart = TRUE;
        }
        else {
          $selector = 'div[data-cart-item="' . $item_id . '"]';
        }
      }

      // If user is logged_in delete slots from MIS.
      else {
        $this->misClient->deleteFromCart($service, $date, $time, $specialist);

        $result = $this->misClient->getCart();
        $slots = !empty($result['slots']) ? $result['slots'] : [];

        if (empty($slots)) {
          $clear_cart = TRUE;
        }
        else {
          foreach ($slots as $slot) {
            $cart_item_service = $slot['service'];
            $type = $this->medMisServiceHelper->checkServiceTypeByService($cart_item_service);

            if ($type == $current_type) {
              $same_types[] = $type;
            }
          }

          if (empty($same_types)) {
            $clear_cart = TRUE;
          }
          else {
            $selector = 'div[data-cart-item="' . $item_id . '"]';
          }
        }
      }

      // If cart is empty.
      if (!empty($clear_cart)) {
        $response->addCommand(new RemoveCommand('.cart__items'));
        $response->addCommand(new RemoveCommand('.cart__right-col__content'));

        $empty_msg = '<div class="cart__empty">' . CartUtility::buildEmptyCartMarkup() . '</div>';
        $response->addCommand(new HtmlCommand('.cart__main-content', $empty_msg));
      }
      else {
        $response->addCommand(new RemoveCommand($selector));
      }

    }
    return $response;
  }

}
