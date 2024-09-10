<?php

namespace Drupal\test_redirect\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContext;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Access\AccessManager;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Routing\TrustedRedirectResponse;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\test_redirect\TestChecker;
use Drupal\redirect\Exception\RedirectLoopException;
use Drupal\path_alias\AliasManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class TestRedirectSubscriber.
 */
class TestRedirectSubscriber implements EventSubscriberInterface {
  /**
   * @var \Symfony\Component\Routing\RequestContext
   */
  protected $context;

  /**
   * A path processor manager for resolving the system path.
   *
   * @var \Drupal\Core\PathProcessor\InboundPathProcessorInterface
   */
  protected $pathProcessor;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Access\AccessManager
   */
  protected $accessManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * @var \Drupal\test_redirect\TestChecker
   */
  protected $checker;

  /**
   * An alias manager for looking up the system path.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a \Drupal\redirect\EventSubscriber\RedirectRequestSubscriber object.
   *
   *
   *   The redirect checker service.
   * @param \Symfony\Component\Routing\RequestContext
   *   Request context.
   */
  public function __construct(RequestContext $context, InboundPathProcessorInterface $path_processor, StateInterface $state, AccessManager $access_manager, AccountInterface $account, RouteProviderInterface $route_provider, ClientInterface $http_client, TestChecker $checker, AliasManager $alias_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->context = $context;
    $this->pathProcessor = $path_processor;
    $this->accessManager = $access_manager;
    $this->state = $state;
    $this->account = $account;
    $this->routeProvider = $route_provider;
    $this->httpClient = $http_client;
    $this->checker = $checker;
    $this->aliasManager = $alias_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // $events['TestRedirectEvent'] = ['testRedirectEvent'];
    $events[KernelEvents::REQUEST][] = ['onKernelRequestCheckRedirect', 33];
    return $events;
  }

  /**
   * This method is called when the TestRedirectEvent is dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function onKernelRequestCheckRedirect(RequestEvent $event) {
    $request = clone $event->getRequest();

    if (!$this->checker->canRedirect($request)) {
      return;
    }

    $request_query = $request->query->all();
    if (strpos($request->getPathInfo(), '/system/files/') === 0 && !$request->query->has('file')) {
      $path = $request->getPathInfo();
    }
    else {
      $path = $this->pathProcessor->processInbound($request->getPathInfo(), $request);
    }

    $alias = $this->aliasManager->getAliasByPath($path);
    $alias = trim($alias, '/');

    $path = trim($path, '/');
    $urls[] = $path;

    if ($path !== $alias) {
      $urls[] = $alias;
    }

    $query_string = UrlHelper::buildQuery($request_query);
    foreach ($urls as $url) {
      $sources[] = !empty($query_string) ? $url . '?' . $query_string : $url;
    }

    try {
      $test_redirects = $this->findByCourcePath($sources);
      if (!empty($test_redirects)) {
        $test_redirect = reset($test_redirects);
      }
    }
    catch (RedirectLoopException $e) {
      $response = new Response();
      $response->setStatusCode(503);
      $response->setContent('Service unavailable');
      $event->setResponse($response);
      return;
    }

    if (!empty($test_redirect)) {
      $log = [];
      $status_code = $test_redirect->getStatusCode();
      $log['status_code'] = $status_code;

      // Prepare url.
      $fallback = FALSE;
      $url = $test_redirect->getRedirectUrl();

      $redirect_type = t('Redirect url');
      $log['source'] = $test_redirect->getSourceUrl();

      if (empty($url)) {
        $url = $test_redirect->getFallbackUrl();
        $url = $this->prepareFallbackUrl($url);
        $redirect_type = t('Fallback url');
        $fallback = TRUE;
        $log['type'] = t('Error');
      }

      else {
        // Prepare log data.
        $log['type'] = t('Success');

        if (!$fallback) {
          $response_code = $this->checker->checkUrlStatusCode($url);
          $log['response_code'] = $response_code;

          if ($response_code !== 200) {
            $url = $test_redirect->getFallbackUrl();
            $url = $this->prepareFallbackUrl($url);
            $redirect_type = t('Fallback url');

            $log['type'] = t('Fallback');
          }
        }

        $log['url'] = $url;
        $log['redirect_type'] = $redirect_type;

        // Wright log.
        $test_log = _test_redirect_get_log($test_redirect);
        $this->wrightLog($test_log, $log);

        // Do redirect.
        if (!empty($url)) {
          $this->doRedirect($event, $url, $status_code, $test_redirect->id());
        }
      }
    }

    return;
  }

  public function doRedirect(RequestEvent $event, $url, $status_code, $id) {
    $headers = [
      'X-Redirect-ID' => $id,
    ];
    $response = new TrustedRedirectResponse($url, $status_code, $headers);
    $event->setResponse($response, $url);
  }

  public function wrightLog($test_log = NULL, $log = NULL) {
    if (!empty($test_log) && !empty($log)) {
      $log['time'] = (new DrupalDateTime())->getTimestamp();
      $test_log->addLog($log);
      $test_log->save();
    }
  }

  public function findByCourcePath($sources) {
    $entityStorage = $this->entityTypeManager->getStorage('test_redirect');
    $query = $entityStorage->getQuery();
    $query->condition('status', 1);
    $query->condition('test_redirect_source', $sources, 'IN');
    $query->accessCheck();
    $ids = $query->execute();
    if (!empty($ids)) {
      $test_redirects = $entityStorage->loadMultiple($ids);
      return $test_redirects;
    }
    return FALSE;
  }

  public function prepareFallbackUrl($url) {
    if (substr($url, 0, 1) == '/') {
      $host = \Drupal::request()->getSchemeAndHttpHost();
      $url = $host . $url;
    }
    return $url;
  }
}
