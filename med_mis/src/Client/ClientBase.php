<?php

declare(strict_types=1);

namespace Drupal\med_mis\Client;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use GuzzleHttp\Psr7\Uri;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Render\Markup;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\med_lk\User\UserQqc153;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines base client object.
 *
 * See https://i.sparm.com/wiki/?id=webqms:lk:api-methods.
 */
final class ClientBase implements ClientBaseInterface {

  /**
   * The user qqc153.
   *
   * @var string
   */
  protected $qqc153;

  /**
   * The debug option.
   *
   * @var bool
   */
  protected $debug;

  /**
   * The debug option.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * Constructs a ClientBase object.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ClientInterface $httpClient,
    private readonly SessionInterface $session,
    private readonly MessengerInterface $messenger,
    private readonly Connection $connection,
    private readonly AccountProxyInterface $currentUser,
    private readonly UserQqc153 $userQqc153,
    private readonly RequestStack $current_request,
  ) {
    $this->qqc153 = $this->setQqc153();
    $this->request = $current_request->getCurrentRequest();
  }

  /**
   * Set debug on/odd in request.
   *
   * @return bool
   *   Show or not debug.
   */
  public function setDebug($on = NULL) {
    $config_debug = $this->checkConfigDebugSettings();
    if ($config_debug) {
      $this->debug = TRUE;
    }
    else {
      $this->debug = $on;
    }
    return $this;
  }

  /**
   * Check config for debug settings.
   */
  public function checkConfigDebugSettings() {
    $set_debug = FALSE;
    $config_debug = $this->configDebug();
    $check = $config_debug->get('check');

    if ($check == 'no_check') {
      $set_debug = TRUE;
    }

    else {
      $check_email_ip = $this->debugCheckEmailIp();
      $check_role = $this->debugCheckRole();

      if ($check == 'role_or_ip' && ($check_email_ip || $check_role)) {
        $set_debug = TRUE;
      }

      elseif ($check == 'role_and_ip' && ($check_email_ip && $check_role)) {
        $set_debug = TRUE;
      }
    }

    return $set_debug;
  }

  /**
   * Check user roles.
   */
  public function debugCheckRole() {
    $check_role = FALSE;

    $config_debug = $this->configDebug();
    $check_roles = $config_debug->get('roles');

    $account = $this->currentUser->getAccount();
    $roles = $account->getRoles();

    if (!empty($check_roles)) {
      $intersect = array_intersect($check_roles, $roles);
      if (!empty($intersect)) {
        $check_role = TRUE;
      }
    }

    return $check_role;
  }

  /**
   * Check current user IP and email.
   */
  public function debugCheckEmailIp() {
    $check_email_ip_result = FALSE;
    $account = $this->currentUser->getAccount();

    $email = $account->getEmail();
    $ip = $this->request->getClientIp();

    $ips = $this->getDebugIp();
    if (!empty($ips)) {
      foreach ($ips as $item) {
        if ($item['ip'] == $ip && $item['email'] == $email) {
          $check_email_ip_result = TRUE;
          break;
        }
      }
    }

    return $check_email_ip_result;
  }

  /**
   * Set qqc153 on/off in request.
   *
   * @return string
   *   Set user qqc153.
   */
  public function setQqc153($qqc153_off = NULL) {
    $qqc153_data = $this->userQqc153->getQqc153();
    return $this->qqc153 = !empty($qqc153_data['qqc153']) ? $qqc153_data['qqc153'] : FALSE;
  }

  /**
   * Method to check qqc153.
   *
   * @return string
   *   User qqc153.
   */
  public function getQqc153() {
    return $this->qqc153;
  }

  /**
   * Get module 'med_mis.settings' config.
   */
  public function config() {
    return $this->configFactory->get('med_mis.settings');
  }

  /**
   * Get module 'med_mis.debug_settings' configs.
   */
  public function configDebug() {
    return $this->configFactory->get('med_mis.debug_settings');
  }

  /**
   * Get IP from settings.
   */
  public function getDebugIp() {
    $ips = [];
    $result = $this->connection->select('med_mis_debug_users', 'u')->fields('u')->execute()->fetchAllAssoc('id');
    if (!empty($result)) {
      foreach ($result as $row) {
        $ips[] = [
          'ip' => $row->ip,
          'email' => $row->email,
        ];
      }
    }
    return $ips;
  }

  /**
   * Build url to MIS.
   */
  public function buildUrl($path = '') {
    $uri_parts = $this->buildUriParts($path);
    $domain = (new Uri())::fromParts($uri_parts);

    $url = (new Uri((string) $domain))->withUserInfo(
      $this->config()->get('username'),
      $this->config()->get('password')
    );

    return (string) $url;
  }

  /**
   * Build uri parts.
   */
  public function buildUriParts($path = '') {
    $type = $this->config()->get('type');

    return [
      'scheme' => self::BASE_SCHEME,
      'host' => $this->config()->get('url_' . $type),
      'port' => $this->config()->get('port_' . $type),
      'path' => $this->buildPath($path),
    ];
  }

  /**
   * Build url path.
   */
  public function buildPath($path = '') {
    $parts = [self::BASE_PATH];

    if (!empty($path)) {
      $parts[] = $path;
    }

    return implode('/', $parts);
  }

  /**
   * Set rest_auth data.
   *
   * @return array
   *   Auth data array.
   */
  public function getRestAuth() {
    $rest_auth = $this->config()->get('rest_auth');
    foreach ($rest_auth as $value) {
      $data[key($value)] = $value[key($value)];
    }
    $data['user'] = '*';
    $data['unauthorized'] = 1;

    if (!empty($this->qqc153)) {
      $data['qqc153'] = $this->qqc153;
    }

    return $data;
  }

  /**
   * Set base_auth login and pass.
   *
   * @return array
   *   Options array.
   */
  public function setOptions($data = []) {
    $options['headers']['Content-type'] = 'application/json';
    $options['timeout'] = self::MIS_REQUEST_TIMEOUT;
    $options['connect_timeout'] = self::MIS_REQUEST_CONNECT_TIMEOUT;
    $post_data = $this->getRestAuth();

    if (!empty($data)) {
      $post_data = array_merge($post_data, $data);
    }

    $options['body'] = json_encode($post_data, JSON_UNESCAPED_UNICODE);

    return $options;
  }

  /**
   * Make GET HTTP Request to path/url.
   *
   * @return mixed
   *   Response array.
   */
  public function postRequest($path = '', $data = []) {
    $result = FALSE;
    $url = $this->buildUrl($path);
    $options = $this->setOptions($data);

    try {
      $response = $this->httpClient->post($url, $options);

      if ($response->getStatusCode() == 200) {
        $response_body = $response->getBody();
        $result = Json::decode($response_body);
        $response->getBody()->close();
      }
      else {
        $msg = $this->t('Status code: @status_code', ['@status_code' => $response->getStatusCode()]);
        $this->message->addError($msg);
      }

      if ($this->debug) {
        $debug_msgs[] = 'url: ' . $url;
        $debug_msgs[] .= 'in: ' . $options['body'];
        $debug_msgs[] .= 'out: ' . json_encode($result, JSON_UNESCAPED_UNICODE);

        $debug_msg = implode('<br>', $debug_msgs);

        $this->messenger->addStatus(Markup::create($debug_msg));
      }
      return $result;

    }
    catch (ClientException $e) {
      $this->messenger->addError($e->getMessage());
      return FALSE;
    }
    catch (RequestException $e) {
      $this->messenger->addError($e->getMessage());
      return FALSE;
    }
    catch (ConnectException $e) {
      $this->messenger->addError($e->getMessage());
      return FALSE;
    }
    catch (GuzzleException $e) {
      $this->messenger->addError($e->getMessage());
      return FALSE;
    }
  }

}
