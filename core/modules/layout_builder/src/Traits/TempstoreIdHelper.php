<?php


namespace Drupal\layout_builder\Traits;


use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;

trait TempstoreIdHelper {

  /**
   * @param FieldableEntityInterface $layout_section_entity
   * @param $layout_section_field_name
   */
  protected function generateTempstoreId(FieldableEntityInterface $layout_section_entity, $layout_section_field_name) {
    $collection = "{$layout_section_entity->getEntityTypeId()}.$layout_section_field_name";
    $id = "{$layout_section_entity->id()}.{$layout_section_entity->language()->getId()}";
    if ($layout_section_entity instanceof RevisionableInterface) {
      $id .= '.'. $layout_section_entity->getRevisionId();
    }
    return [$collection, $id];
  }

}
