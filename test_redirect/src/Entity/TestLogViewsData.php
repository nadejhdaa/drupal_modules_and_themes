<?php

namespace Drupal\test_redirect\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Test log entities.
 */
class TestLogViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}
