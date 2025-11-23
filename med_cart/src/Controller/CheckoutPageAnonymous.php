<?php

declare(strict_types=1);

namespace Drupal\med_cart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\med_cart\CartManager;
use Drupal\Core\Render\Markup;

/**
 * Returns responses for ENC content routes.
 */
final class CheckoutPageAnonymous extends ControllerBase {
  const WEBFORM_ID = 'zayavka_na_priem';

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly CartManager $cartManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('med_cart.cart_manager'),
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(): array {
    $services = [];
    $all_items = $this->cartManager->cartItems();

    if (!empty($all_items)) {
      foreach ($all_items as $item) {
        $service_data = [
          $item['pAz'],
          '"' . $item['title'] . '"',
          $item['date'] . ', "' . $item['time'] . '"',
        ];
        $services[] = implode('. ', $service_data);
      }
    }

    $services_markup = Markup::create(implode('<br>', $services));

    $build[self::WEBFORM_ID] = [
      '#type' => 'webform',
      '#webform' => self::WEBFORM_ID,
      '#default_data' => ['services' => $services_markup],
      '#entity_type' => 'user',
      '#entity_id' => 0,
    ];
    return $build;
  }

}
