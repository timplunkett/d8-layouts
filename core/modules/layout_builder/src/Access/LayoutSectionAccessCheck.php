<?php

namespace Drupal\layout_builder\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for node revisions.
 *
 * @ingroup node_access
 */
class LayoutSectionAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * LayoutSectionAccessCheck constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }


  /**
   * Checks routing access to layout for the entity.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @return \Drupal\Core\Access\AccessResultInterface
   * The access result.
   */
  public function access(Request $request, AccountInterface $account) {
    $entity = $request->attributes->get('entity');
    $has_field = $request->attributes->get('field_name');
    // If we don't have an entity forbid access.
    if (empty($entity) || empty($has_field)) {
      return AccessResult::forbidden();
    }
    // If the entity isn't fieldable, forbid access.
    if (!$entity instanceof FieldableEntityInterface) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }
    return AccessResult::allowedIf($account->hasPermission('configure any layout'))->addCacheableDependency($entity);
  }

}
