<?php

namespace Drupal\layout_builder\Traits;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * Provides helper methods for tempstore.
 */
trait TempstoreIdHelper {

  /**
   * Generates a collection and ID for putting an entity in tempstore.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $layout_section_entity
   *   The entity being stored.
   *
   * @return array
   *   An array containing the collection name and the tempstore ID.
   */
  protected function generateTempstoreId(FieldableEntityInterface $layout_section_entity) {
    // @todo Can we make the collection simply the entity type ID?
    $collection = $layout_section_entity->getEntityTypeId() . '.layout_builder__layout';
    $id = "{$layout_section_entity->id()}.{$layout_section_entity->language()->getId()}";
    if ($layout_section_entity instanceof RevisionableInterface) {
      $id .= '.' . $layout_section_entity->getRevisionId();
    }
    return [$collection, $id];
  }

}
