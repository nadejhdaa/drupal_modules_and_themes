<?php

declare(strict_types=1);

namespace Drupal\auto_import\Client;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Drupal\auto_import\Client\ClientBaseInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;
use GuzzleHttp\Cookie\SessionCookieJar;

/**
 * @todo Add class description.
 */
final class ClientBase implements ClientBaseInterface {

  /**
   * The base authorize name and pass.
   *
   * @var array
   */
  protected $baseAuth;

  /**
   * The brand name.
   *
   * @var string
   */
  protected $brand;

  /**
   * Constructs a ClientBase object.
   *
   * @param Psr\Http\Client\ClientInterface $httpClient
   *   The ClientInterface.
   * @param Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The SessionInterface.
   * @param Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator
   *   The UrlGeneratorInterface.
   * @param Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The ConfigFactoryInterface.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly SessionInterface $session,
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Get module 'auto_import.import' config.
   */
  public function config() {
    return $this->configFactory->get('auto_import.import');
  }

  /**
   * Get remote site base url.
   *
   * @return string
   */
  public function baseUrl() {
    return $this->config()->get('base_url');
  }

  /**
   * Set base_auth login and pass.
   *
   * @return string
   */
  public function setBaseAuth() {
    $this->baseAuth = !empty($this->baseAuth) ? $this->baseAuth : [$this->config()->get('base_auth_login'), $this->config()->get('base_auth_pass')];
    return 'Basic ' . base64_encode(implode(':', $this->baseAuth));
  }

  /**
   * Generate Cookies filename.
   *
   * @return string
   */
  public function getCookiesName() {
    return 'cookies_' . (!empty($this->brand) ? $this->brand : 'all');
  }

  /**
   * Create cookies.
   *
   * @return mixed
   */
  public function getCookies() {
    $cookies_name = $this->getCookiesName();
    return new SessionCookieJar($cookies_name, TRUE);
  }

  /**
   * Set params to Client.
   *
   */
  public function setParams($params) {
    if (!empty($params['brand'])) {
      $this->brand = $params['brand'];
    }
  }

  /**
   * Build uri for request.
   *
   * @return string
   */
  public function buildUrl($path) {
    $url_options = [];

    if (!empty($this->brand) && $path != self::LOGOUT_PATH) {
      $url_options['query']['brand'] = $this->brand;
    }

    if ($path != self::TOKEN_PATH && $path != self::LOGIN_PATH) {
      $token = $this->getToken();
      $url_options['query']['_csrf_token'] = $token;
    }

    return Url::fromUri($this->baseUrl() . $path, $url_options)->toString();
  }

  /**
   * Build options for request.
   *
   * @return array
   */
  public function setOptions($path = NULL, $no_http_errors = FALSE) {
    $options = [
      'allow_redirects' => true,
      'cookies' => $this->getCookies(),
    ];

    $options['headers']['Authorization'] = $this->setBaseAuth();

    if (!empty($no_http_errors)) {
      $options['http_errors'] = FALSE;
    }

    if ($path == self::LOGIN_PATH) {
      $options['headers']['Content-type'] = 'application/x-www-form-urlencoded';
      $options['form_params'] = [
        'form_id' => 'user_login_form',
        'name' => $this->config()->get('login'),
        'pass' => $this->config()->get('pass'),
      ];
    }
    else {
      $options['headers']['Content-type'] = 'application/json';
      $options['headers']['Connection'] = 'close';
    }

    return $options;
  }

  /**
   * Make GET HTTP Request to path/url.
   *
   * @return mixed
   */
  public function getRequest($path, $no_http_errors = FALSE) {
    $url = UrlHelper::isExternal($path) ? $path : $this->buildUrl($path);
    $options = $this->setOptions($path, $no_http_errors);

    try {
      $response = $this->httpClient->get($url, $options);
      return $response;

    } catch (RequestException $e) {
      \Drupal::messenger()->addError($e->getMessage());
      return $e->getMessage();
    }
  }

  /**
   * Make HTTP POST Request to path/url.
   *
   * @return mixed
   */
  public function postRequest($path, $no_http_errors = FALSE) {
    $url = $this->buildUrl($path);
    $options = $this->setOptions($path);

    try {
      $response = $this->httpClient->post($url, $options);
      return $response;
    } catch (RequestException $e) {
      \Drupal::messenger()->addError($e->getMessage());
    }
  }

  /**
   * Authorize client on donor site.
   *
   * @return string
   */
  public function login() {
    $response = $this->postRequest(self::LOGIN_PATH);
    return $response;
  }

  /**
   * Logout client from recipient site.
   *
   * @return string
   */
  public function logout() {
    try {
      $response = $this->getRequest(self::LOGOUT_PATH);
      $this->session->remove('scrf_token');
      return TRUE;
    } catch (RequestException $e) {
      \Drupal::messenger()->addError($e->getMessage());
    }
  }

  /**
   * Get and set scrf_token from recipient site.
   *
   * @return string
   */
  public function getToken() {
    $token = $this->session->get('scrf_token');
    if ($token) {
      return $token;
    }
    else {
      try {
        $response = $this->getRequest(self::TOKEN_PATH);
        if ($response->getStatusCode() == 200) {
          $token = $response->getBody()->getContents();
          $this->session->set('scrf_token', $token);
          return $token;
        }
        return FALSE;
      } catch (RequestException $e) {
        \Drupal::messenger()->addError($e->getMessage());
      }
    }
    return;
  }

  /**
   * Get data from REST-service on recipient site.
   *
   * @return array
   */
  public function getContacts() {
    $this->login();
    $path = $this->configFactory->get('auto_import.import')->get('contact');

    try {
      $response = $this->getRequest($path);
      $this->logout();
      if ($response->getStatusCode() == 200) {
        return $response->getBody()->getContents();
      }
      return FALSE;
    } catch (RequestException $e) {
      \Drupal::messenger()->addError($e->getMessage());
    }
  }
}
