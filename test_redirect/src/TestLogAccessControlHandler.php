<?php

namespace Drupal\test_redirect;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Test log entity.
 *
 * @see \Drupal\test_redirect\Entity\TestLog.
 */
class TestLogAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\test_redirect\Entity\TestLogInterface $entity */

    switch ($operation) {

      case 'view':

        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'test view unpublished log entities');
        }


        return AccessResult::allowedIfHasPermission($account, 'test view published log entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'test edit log entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'test delete log entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'test add log entities');
  }


}
