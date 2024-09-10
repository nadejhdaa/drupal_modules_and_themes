<?php

namespace Drupal\test_redirect;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Test redirect entity.
 *
 * @see \Drupal\test_redirect\Entity\TestRedirect.
 */
class TestRedirectAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\test_redirect\Entity\TestRedirectInterface $entity */

    switch ($operation) {

      case 'view':

        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'test view unpublished redirect entities');
        }


        return AccessResult::allowedIfHasPermission($account, 'test view published redirect entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'test edit redirect entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'test delete redirect entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add test redirect entities');
  }


}
