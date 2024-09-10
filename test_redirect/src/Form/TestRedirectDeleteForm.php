<?php

namespace Drupal\test_redirect\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Provides a form for deleting Test redirect entities.
 *
 * @ingroup test_redirect
 */
class TestRedirectDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the %redirect?', [
      '%redirect' => $this->entity->getName(),
    ]);
  }
}
