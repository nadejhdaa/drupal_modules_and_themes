<?php

declare(strict_types=1);

namespace Drupal\med_cart;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class for Cart for anonymous functions.
 */
final class CartManager {

  /**
   * Constructs a CartManager object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly SessionInterface $session,
    private readonly MessengerInterface $messenger,
    private readonly CacheBackendInterface $cacheDefault,
  ) {}

  /**
   * Add to cart slot.
   */
  public function addToCart($slot, $item_id = '') {
    $result = [
      'success' => FALSE,
    ];

    // Корзина в сессии.
    $cart = $this->session->get('cart') ?: [];

    if (empty($item_id)) {
      $item_id = $slot['service'] . '__' . $slot['qqc244'];

      if (!empty($slot['date'])) {
        $item_id .= '__' . $slot['date'];
      }

      if (!empty($slot['time'])) {
        $item_id .= '__' . $slot['time'];
      }
    }

    $cart[$item_id] = $slot;
    $this->session->set('cart', $cart);

    $result['count'] = count($cart);
    $result['success'] = TRUE;

    return $result;
  }

  /**
   * Get cart items from session.
   */
  public function cartItems() {
    return $this->session->get('cart') ?: [];
  }

  /**
   * Remove cart item from session.
   */
  public function removeItem($key) {
    $items = $this->cartItems();
    if (!empty($items[$key])) {
      unset($items[$key]);
    }
    $this->session->set('cart', $items);
    return TRUE;
  }

  /**
   * Clear cart items from session.
   */
  public function clearCart() {
    $this->session->remove('cart');
  }

}
