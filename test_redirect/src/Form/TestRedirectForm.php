<?php

namespace Drupal\test_redirect\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\test_redirect\Entity\TestLog;
use Drupal\test_redirect\TestChecker;
use Drupal\Core\Url;

/**
 * Form controller for Test redirect edit forms.
 *
 * @ingroup test_redirect
 */
class TestRedirectForm extends ContentEntityForm {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * @var \Drupal\test_redirect\TestChecker
   */
  protected $checker;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');
    $instance->checker = $container->get('testchecker');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var \Drupal\test_redirect\Entity\TestRedirect $entity */
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;
    $default_code = $entity->getStatusCode() ? $entity->getStatusCode() : \Drupal::config('redirect.settings')->get('default_status_code');

    $form['status_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Redirect status'),
      '#description' => $this->t('You can find more information about HTTP redirect status codes at <a href="@status-codes">@status-codes</a>.', ['@status-codes' => 'http://en.wikipedia.org/wiki/List_of_HTTP_status_codes#3xx_Redirection']),
      '#default_value' => !empty($entity->getStatusCode()) ? $entity->getStatusCode() : 301,
      '#options' => test_redirect_status_code_options(),
    ];
    $form['status_code']['#weight'] = -4;

    $form['test_redirect_source']['widget'][0]['value']['#field_prefix'] = \Drupal::request()->getSchemeAndHttpHost() . '/';

    $form['revision']['#default_value'] = 1;
    $form['revision']['#access'] = FALSE;
    $form['revision_log']['#states'] = [];

    if (!$entity->isNew()) {
      $form['revision_log']['widget'][0]['value']['#required'] = 1;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Check test_redirect_source is not external.
    $source = $form_state->getValue(['test_redirect_source', 0]);
    $source_url = $source['value'];

    $values['test_redirect_source'] = $source_url;

    $label = t('Redirect: @source_url', ['@source_url' => $source_url]);
    $form_state->setValue('label', $label->__toString());

    $source_explode = explode('?', $source_url);
    $source_path = $source_explode[0];

    if ($this->urlExternal($source_path)) {
      $msg = $this->t('Enter internal URL');
      $form_state->setErrorByName('test_redirect_source', $msg);
    }

    // Check Redirect url is absolute and valid.
    $redirect = $form_state->getValue(['test_redirect_redirect', 0]);
    $redirect_value = $redirect['value'];

    $values['test_redirect_redirect'] = $redirect_value;

    if (!empty($redirect_value)) {
      $redirect_url = $redirect_value;
      $is_absolute_url = UrlHelper::isValid($redirect_url, 1);

      if (!$is_absolute_url) {
        $msg = $this->t('Enter absolute URL');
        $form_state->setErrorByName('test_redirect_redirect', $msg);
      }
      else {
        $request_status_code = $this->checker->checkUrlStatusCode($redirect_url);
        if ($request_status_code !== 200) {
          $msg = $this->t('Enter valid URL in "From" field. Entered URL returned code @code', ['@code' => $request_status_code]);
          $form_state->setErrorByName('test_redirect_redirect', $msg);
        }
      }
    }

    // Check Fallback url.
    $fallback = $form_state->getValue(['test_redirect_fallback', 0]);
    $fallback_value = $fallback['value'];
    $values['test_redirect_fallback'] = $fallback_value;

    if (!empty($fallback_value)) {
      $fallback_url = $fallback_value;

      if (substr($fallback_url, 0, 1) !== '/') {
        $is_absolute_url = UrlHelper::isValid($fallback_url, 1);
        if (!$is_absolute_url) {
          $msg = $this->t('Add / at the beginning of the line');
          $form_state->setErrorByName('test_redirect_fallback', $msg);
        }
      }
    }

    // Check values to avoid cyclic redirection.
    $host = \Drupal::request()->getHost();
    $search = [];
    $alias_manager = \Drupal::service('path_alias.manager');

    foreach ($values as $field_name => $url) {
      $is_absolute_url = UrlHelper::isValid($url, 1);

      if ($is_absolute_url) {
        $clear_url = $this->stripPrefixes($url);

        if (strpos($clear_url, $host) !== false) {
          $clear_url = str_replace($host, '', $clear_url);

          $alias = $alias_manager->getAliasByPath($clear_url);
          $url = Url::fromUri("internal:" . $alias);
          $clear_url = $url->setAbsolute()->toString();
        }
        $search[$clear_url][] = $field_name;
      }
      else {
        $alias = $alias_manager->getAliasByPath('/' . $url);
        $url = Url::fromUri("internal:" . $alias);
        $search[$url->setAbsolute()->toString()][] = $field_name;
      }
    }

    $msg = $this->t('Enter different urls to avoid cyclic redirection');
    foreach ($search as $clear_url => $field_names) {
      if (!empty($field_names) && count($field_names) > 1) {
        foreach ($field_names as $field_name) {
          $form_state->setErrorByName($field_name, $msg = $msg);
        }
      }
    }
  }

  /**
   * Strip off the "http://", "https://" and "www." prefixes from a URL.
   *
   * This makes it easier to compare URLs.
   *
   * @param string $url
   *   The URl to strip prefixes from.
   *
   * @return string
   *   The URL with "http://", "https://" and "www." removed.
   */
  protected function stripPrefixes($url) {
    $url = str_replace('http://', '', $url);
    $url = str_replace('https://', '', $url);
    $url = str_replace('www.', '', $url);
    return $url;
  }


  public function urlExternal($url) {
    return UrlHelper::isExternal($url);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created redirect %label', [
          '%label' => $entity->getName(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved redirect %label', [
          '%label' => $entity->getName(),
        ]));
    }

    $form_state->setRedirect('view.test_redirect_statistics.page_1');
  }

}
