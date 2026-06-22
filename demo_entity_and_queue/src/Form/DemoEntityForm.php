<?php

declare(strict_types=1);

namespace Drupal\demo_entity_and_queue\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the enc service data from getpr entity edit forms.
 */
final class DemoEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $message_args = ['%label' => $this->entity->toLink()->toString()];
    $logger_args = [
      '%label' => $this->entity->label(),
      'link' => $this->entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New enc service data from getpr %label has been created.', $message_args));
        $this->logger('demo_entity_and_queue')->notice('New enc service data from getpr %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The enc service data from getpr %label has been updated.', $message_args));
        $this->logger('demo_entity_and_queue')->notice('The enc service data from getpr %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

}
