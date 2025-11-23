<?php

declare(strict_types=1);

namespace Drupal\med_registry\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormState;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Open in modal dialog Doctor cart form.
 */
final class AjaxLoadDoctorCartFormController extends ControllerBase {
  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The ModalDoctorCartFormController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
    );
  }

  /**
   * Open cart form in dialog window.
   */
  public function __invoke(Request $request) {
    if (empty($request->get('_wrapper_format'))) {
      throw new AccessDeniedHttpException();
    }

    $response = new AjaxResponse();
    $args = [];

    if (!empty($request->get('service'))) {
      $selected_service = $request->get('service');
      $args = ['service' => $selected_service];
    }

    $form_state = new FormState(['args' => $args]);

    $modal_form = $this->formBuilder->buildForm('Drupal\med_cart\Form\DoctorCartForm', $form_state);

    $dialog_options = [
      'width' => '1296',
      'dialogClass' => 'modal-dialog--doctor-cart-form',
    ];

    $response->addCommand(new OpenModalDialogCommand($this->t('Make an appointment'), $modal_form, $dialog_options));

    return $response;
  }

}
