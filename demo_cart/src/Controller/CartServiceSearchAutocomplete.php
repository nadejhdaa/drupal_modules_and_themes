<?php

namespace Drupal\demo_cart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\demo_is\Client\DemoClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Xss;

/**
 * Cart autocomplete by service name controller.
 */
class CartServiceSearchAutocomplete extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly DemoClient $demoClient,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('demo_client'),
    );
  }

  /**
   * Method returning autocomplete matches.
   */
  public function autocomplete(Request $request) {
    $string = $request->query->get('q');

    $matches = [];

    if (!empty($string)) {
      $string = Xss::filter($string);
      $string = mb_strtolower($string);

      // User is Authenticated.
      if ($this->currentuser()->isAuthenticated()) {

        $result = $this->demoClient->getCart();
        $slots = !empty($result['slots']) ? $result['slots'] : [];

        foreach ($slots as $slot) {
          if (strpos(mb_strtolower($slot['title']), $string) !== FALSE) {
            $value = $slot['title'] . ' | ' . $slot['pAz'];
            $label = $slot['title'] . ' | ' . $slot['pAz'];
            $matches[] = ['value' => $value, 'label' => $label];
          }
        }
      }

    }

    return new JsonResponse($matches);
  }

}
