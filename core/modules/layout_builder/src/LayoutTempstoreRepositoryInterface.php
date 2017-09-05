<?php

namespace Drupal\layout_builder;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for loading layouts from tempstore.
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
   *   none exists.
   */
  public function get(EntityInterface $entity);

  /**
   * Loads an entity from tempstore given the entity ID.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID (or revision ID).
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Either the version of this entity from tempstore, or the entity from
   *   storage if none exists.
   */
  public function getFromId($entity_type_id, $entity_id);

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
