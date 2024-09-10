<?php

namespace Drupal\test_redirect\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Test log entities.
 *
 * @ingroup test_redirect
 */
interface TestLogInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Test log name.
   *
   * @return string
   *   Name of the Test log.
   */
  public function getName();

  /**
   * Sets the Test log name.
   *
   * @param string $name
   *   The Test log name.
   *
   * @return \Drupal\test_redirect\Entity\TestLogInterface
   *   The called Test log entity.
   */
  public function setName($name);

  /**
   * Gets the Test log creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Test log.
   */
  public function getCreatedTime();

  /**
   * Sets the Test log creation timestamp.
   *
   * @param int $timestamp
   *   The Test log creation timestamp.
   *
   * @return \Drupal\test_redirect\Entity\TestLogInterface
   *   The called Test log entity.
   */
  public function setCreatedTime($timestamp);

}
