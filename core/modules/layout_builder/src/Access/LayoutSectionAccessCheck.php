<?php

namespace Drupal\layout_builder\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * @todo.
 */
class LayoutSectionAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LayoutSectionAccessCheck.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks routing access to layout for the entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    $entity = $route_match->getParameter('layout_section_entity');
    $field_name = $route_match->getParameter('layout_section_field_name');
    // If we don't have an entity, forbid access.
    if (empty($entity) || empty($field_name)) {
      return AccessResult::forbidden()->addCacheContexts(['route']);
    }

    // If the entity isn't fieldable, forbid access.
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($field_name)) {
      $access = AccessResult::forbidden();
    }
    else {
      $access = AccessResult::allowedIf($account->hasPermission('configure any layout'));
    }

    return $access->addCacheableDependency($entity);
  }

}
