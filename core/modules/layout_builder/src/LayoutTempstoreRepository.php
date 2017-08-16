<?php

namespace Drupal\layout_builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\user\SharedTempStoreFactory;

/**
 * @todo.
 */
class LayoutTempstoreRepository implements LayoutTempstoreRepositoryInterface {

  /**
   * The shared tempstore factory.
   *
   * @var \Drupal\user\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * LayoutTempstoreRepository constructor.
   *
   * @param \Drupal\user\SharedTempStoreFactory $temp_store_factory
   *   The shared tempstore factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(SharedTempStoreFactory $temp_store_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityInterface $entity) {
    list($collection, $id) = $this->generateTempstoreId($entity);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    if (!empty($tempstore['entity'])) {
      return $tempstore['entity'];
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getFromId($entity_type_id, $entity_id) {
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->loadRevision($entity_id);
    return $this->get($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function set(EntityInterface $entity) {
    list($collection, $id) = $this->generateTempstoreId($entity);
    $this->tempStoreFactory->get($collection)->set($id, ['entity' => $entity]);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(EntityInterface $entity) {
    if ($this->get($entity)) {
      list($collection, $id) = $this->generateTempstoreId($entity);
      $this->tempStoreFactory->get($collection)->delete($id);
    }
  }

  /**
   * Generates a collection and ID for putting an entity in tempstore.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being stored.
   *
   * @return array
   *   An array containing the collection name and the tempstore ID.
   */
  protected function generateTempstoreId(EntityInterface $entity) {
    // @todo Can we make the collection simply the entity type ID?
    $collection = $entity->getEntityTypeId() . '.layout_builder__layout';
    $id = "{$entity->id()}.{$entity->language()->getId()}";
    if ($entity instanceof RevisionableInterface) {
      $id .= '.' . $entity->getRevisionId();
    }
    return [$collection, $id];
  }

  /**
   * {@inheritdoc}
   */
  public function getFromId($entity_type_id, $entity_id) {
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->loadRevision($entity_id);
    return $this->get($entity);
  }

}
