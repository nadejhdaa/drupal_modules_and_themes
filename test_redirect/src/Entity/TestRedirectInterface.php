<?php

namespace Drupal\test_redirect\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Test redirect entities.
 *
 * @ingroup test_redirect
 */
interface TestRedirectInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Test redirect name.
   *
   * @return string
   *   Name of the Test redirect.
   */
  public function getName();

  /**
   * Sets the Test redirect name.
   *
   * @param string $name
   *   The Test redirect name.
   *
   * @return \Drupal\test_redirect\Entity\TestRedirectInterface
   *   The called Test redirect entity.
   */
  public function setName($name);

  /**
   * Gets the Test redirect creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Test redirect.
   */
  public function getCreatedTime();

  /**
   * Sets the Test redirect creation timestamp.
   *
   * @param int $timestamp
   *   The Test redirect creation timestamp.
   *
   * @return \Drupal\test_redirect\Entity\TestRedirectInterface
   *   The called Test redirect entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Test redirect revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Test redirect revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\test_redirect\Entity\TestRedirectInterface
   *   The called Test redirect entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Test redirect revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Test redirect revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\test_redirect\Entity\TestRedirectInterface
   *   The called Test redirect entity.
   */
  public function setRevisionUserId($uid);

}
