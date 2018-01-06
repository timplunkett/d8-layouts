<?php

namespace Drupal\layout_builder\Entity;

use Drupal\Core\Entity\Entity\EntityViewDisplay as BaseEntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an entity view display entity that has a layout.
 *
 * @internal
 */
class LayoutBuilderEntityViewDisplay extends BaseEntityViewDisplay implements LayoutEntityDisplayInterface {

  /**
   * {@inheritdoc}
   */
  public function isOverridable() {
    return $this->getThirdPartySetting('layout_builder', 'allow_custom', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function setOverridable($overridable = TRUE) {
    $this->setThirdPartySetting('layout_builder', 'allow_custom', $overridable);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $entities) {
    $build_list = parent::buildMultiple($entities);

    foreach ($entities as $id => $entity) {
      $sections = $this->getRuntimeSections($entity);
      if ($sections) {
        foreach ($build_list[$id] as $name => $build_part) {
          $field_definition = $this->getFieldDefinition($name);
          if ($field_definition && $field_definition->isDisplayConfigurable('view')) {
            unset($build_list[$id][$name]);
          }
        }

        foreach ($sections as $delta => $section) {
          $build_list[$id]['_layout_builder'][$delta] = $section->toRenderArray();
        }
      }
    }

    return $build_list;
  }

  /**
   * Gets the runtime sections for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\layout_builder\Section[]
   *   The sections.
   */
  protected function getRuntimeSections(EntityInterface $entity) {
    if ($this->isOverridable() && !$entity->layout_builder__layout->isEmpty()) {
      return $entity->layout_builder__layout->getSections();
    }

    return [];
  }

}
