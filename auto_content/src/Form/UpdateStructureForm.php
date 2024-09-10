<?php

declare(strict_types=1);

namespace Drupal\auto_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\auto_content\Update\UpdateStructure;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\auto_content\Utility\Utility;

/**
 * Provides a Auto content form.
 */
final class UpdateStructureForm extends FormBase {
  protected $updateStructure;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->updateStructure = $container->get('auto_content.update_structure');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'auto_content_update_structure';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $input = $form_state->getUserInput();
    $options = [
      'menus' => $this->t('Update menus'),
      'intersections' => $this->t('Update intersections'),
    ];

    $form['vid'] = [
      '#type' => 'checkboxes',
      '#title' => 'Какой словарь обновить',
      '#required' => TRUE,
      '#options' => [
        Utility::VID_BRANDS => 'Авто',
        Utility::VID_SERVICES => 'Услуги',
        Utility::VID_AREA => 'Округи',
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Update'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
    $input = $form_state->getUserInput();

    if (!empty($input['vid'])) {
      foreach ($input['vid'] as $value) {
        if (!empty($value)) {
          $vids[] = $value;
        }
      }
    }

    if (!empty($vids)) {
      $this->updateStructure->run($vids);
    }

    $form_state->setRedirect('auto_content.update_structure');
  }

}
