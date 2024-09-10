<?php

namespace Drupal\test_redirect\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Provides a form for deleting Test log entities.
 *
 * @ingroup test_redirect
 */
class TestLogDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the %log?', [
      '%log' => $this->entity->getName(),
    ]);
  }
}
