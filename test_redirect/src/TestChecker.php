<?php

namespace Drupal\test_redirect;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\State\StateInterface;

/**
 * Class TestChecker.
 */
class TestChecker {

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Drupal\Core\Routing\RequestContext definition.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $routerRequestContext;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new TestChecker object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ClientInterface $http_client, RequestContext $router_request_context, AccountProxyInterface $current_user, StateInterface $state) {
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
    $this->routerRequestContext = $router_request_context;
    $this->currentUser = $current_user;
    $this->state = $state;
  }

  public function checkUrlStatusCode($url_string) {
    try {
      $response = $this->httpClient->request('GET', $url_string, ['limit' => 2]);
      return $response->getStatusCode();
    } catch (ClientException $e) {
      $response = $e->getResponse();
      if (NULL === $response) {
        throw $e;
      }
      return $response->getStatusCode();
    } catch (RequestException $e) {
      $response = $e->getResponse();
      if (NULL === $response) {
        throw $e;
      }
      return $response->getStatusCode();
    } catch (ConnectException $e) {
      return 0;
    }
  }

  public function canRedirect(Request $request, $route_name = NULL) {
    $can_redirect = TRUE;
    if (isset($route_name)) {
      $route = $this->routeProvider->getRouteByName($route_name);

      if ($this->config->get('access_check')) {
        // Do not redirect if is a protected page.
        $can_redirect = $this->accessManager->checkNamedRoute($route_name, [], $this->account);
      }
    }
    else {
      $route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT);
    }
    if (!preg_match('/index\.php$/', $request->getScriptName())) {
      // Do not redirect if the root script is not /index.php.
      $can_redirect = FALSE;
    }
    elseif (!($request->isMethod('GET') || $request->isMethod('HEAD'))) {
      // Do not redirect if this is other than GET request.
      $can_redirect = FALSE;
    }
    elseif (!$this->currentUser->hasPermission('access site in maintenance mode') && ($this->state->get('system.maintenance_mode') || defined('MAINTENANCE_MODE'))) {
      // Do not redirect in offline or maintenance mode.
      $can_redirect = FALSE;
    }
    elseif ($request->query->has('destination')) {
      $can_redirect = FALSE;
    }
    elseif (isset($route)) {
      // Do not redirect on admin paths.
      $can_redirect &= !(bool) $route->getOption('_admin_route');
    }

    return $can_redirect;
  }

}
