<?php

declare(strict_types=1);

namespace Drupal\med_cart\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\med_mis\Client\MisClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\med_lk\UserService;
use Drupal\med_mis\Utility\Utility;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Provides a cart top block.
 */
#[Block(
  id: 'med_cart_top',
  admin_label: new TranslatableMarkup('Cart top'),
  category: new TranslatableMarkup('Custom'),
)]
final class CartTopBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly MisClient $misClient,
    private readonly AccountInterface $currentUser,
    private readonly SessionInterface $session,
    private readonly CacheBackendInterface $cacheBackend,
    private readonly UserService $userService,
    private readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mis_client'),
      $container->get('current_user'),
      $container->get('session'),
      $container->get('cache.data'),
      $container->get('med_lk.user_service'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $user_authenticated = $this->currentUser->isAuthenticated();

    $count = $this->getCartItemsCount($user_authenticated);

    if ($user_authenticated && Utility::isLkDomain() && $this->routeMatch->getRouteName() !== 'med_cart.cart_authenticated') {
      if (!empty($count)) {
        $this->showUserCartMsg();
      }
    }

    $build['content'] = [
      '#theme' => 'cart_block_top',
      '#count' => $count,
      '#user_authenticated' => $user_authenticated,
    ];

    return $build;
  }

  /**
   * Get cart items count.
   */
  public function getCartItemsCount($user_authenticated) {
    $cart_items = $this->getCartItems($user_authenticated);
    return count($cart_items);
  }

  /**
   * Get cart items list.
   */
  public function getCartItems($user_authenticated) {
    if ($user_authenticated) {
      $result = $this->misClient->getCart();

      return !empty($result['slots']) ? $result['slots'] : [];
    }
    else {
      return $this->session->get('cart') ?: [];
    }
  }

  /**
   * Build renderable msg.
   */
  public function checkUserCartMsg() {
    $result = $this->misClient->getCart();

    if (!empty($result['slots'])) {
      $this->showUserCartMsg();
    }
  }

  /**
   * Show msg about cart items.
   */
  public function showUserCartMsg() {
    $cart_url = Url::fromRoute('med_cart.cart_authenticated');
    $cart_url = $cart_url->toString();

    $msg = [
      '#theme' => 'message_lk',
      '#msg' => $this->t(Utility::CART_MSG), // phpcs:ignore
      '#link_url' => $cart_url,
      '#link_text' => $this->t('Checkout order'),
      '#icon' => 'cart',
    ];

    $this->messenger()->addMessage($msg);
  }

}
