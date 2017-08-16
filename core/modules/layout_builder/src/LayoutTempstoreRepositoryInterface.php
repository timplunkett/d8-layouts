<?php

namespace Drupal\layout_builder;

use Drupal\Core\Entity\EntityInterface;

/**
 * @todo.
 */
interface LayoutTempstoreRepositoryInterface {

  /**
   * Gets the tempstore version of an entity, if it exists.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for in tempstore.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Either the version of this entity from tempstore, or the passed entity if
   *   none exist.
   */
  public function get(EntityInterface $entity);

  /**
   * Stores this entity in tempstore.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to set in tempstore.
   */
  public function set(EntityInterface $entity);

  /**
   * Removes the tempstore version of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to remove from tempstore.
   */
  public function delete(EntityInterface $entity);

}
