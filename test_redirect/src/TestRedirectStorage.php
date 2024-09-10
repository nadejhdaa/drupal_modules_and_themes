<?php

namespace Drupal\test_redirect;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\test_redirect\Entity\TestRedirectInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the storage handler class for Test redirect entities.
 *
 * This extends the base storage class, adding required special handling for
 * Test redirect entities.
 *
 * @ingroup test_redirect
 */
class TestRedirectStorage extends SqlContentEntityStorage implements EntityStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(TestRedirectInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {test_redirect_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {test_redirect_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(TestRedirectInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {test_redirect_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('test_redirect_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  // public function loadRevision($revision_id) {
  //   // return $revision_id;
  //   $revision = $this->loadRevision(5);
  //   return $revision;
  // }

}
