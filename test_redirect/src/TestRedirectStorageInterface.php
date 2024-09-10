<?php

namespace Drupal\test_redirect;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\test_redirect\Entity\TestRedirectInterface;

/**
 * Defines the storage handler class for Test redirect entities.
 *
 * This extends the base storage class, adding required special handling for
 * Test redirect entities.
 *
 * @ingroup test_redirect
 */
interface TestRedirectStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Test redirect revision IDs for a specific Test redirect.
   *
   * @param \Drupal\test_redirect\Entity\TestRedirectInterface $entity
   *   The Test redirect entity.
   *
   * @return int[]
   *   Test redirect revision IDs (in ascending order).
   */
  public function revisionIds(TestRedirectInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Test redirect author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Test redirect revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\test_redirect\Entity\TestRedirectInterface $entity
   *   The Test redirect entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(TestRedirectInterface $entity);

  /**
   * Unsets the language for all Test redirect with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
