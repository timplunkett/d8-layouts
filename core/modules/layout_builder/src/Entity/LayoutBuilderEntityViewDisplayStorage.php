<?php

namespace Drupal\layout_builder\Entity;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

/**
 * Provides storage for entity view display entities that have layouts.
 */
class LayoutBuilderEntityViewDisplayStorage extends ConfigEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function mapToStorageRecord(EntityInterface $entity) {
    $record = parent::mapToStorageRecord($entity);

    if (!empty($record['third_party_settings']['layout_builder']['sections'])) {
      /** @var \Drupal\layout_builder\Section[] $sections */
      $sections = &$record['third_party_settings']['layout_builder']['sections'];
      foreach ($sections as $section_delta => $section) {
        $sections[$section_delta] = [
          'layout_id' => $section->getLayoutId(),
          'layout_settings' => $section->getLayoutSettings(),
          'components' => $this->exportComponents($section->getComponents()),
        ];
      }
    }
    return $record;
  }

  /**
   * Exports an array of component objects into an array suitable for storage.
   *
   * @param \Drupal\layout_builder\SectionComponent[] $components
   *   The component objects.
   *
   * @return mixed[]
   *   An array of components in array form.
   */
  protected function exportComponents(array $components) {
    $export = [];
    foreach ($components as $delta => $component) {
      $configuration_reflection = new \ReflectionProperty($component, 'configuration');
      $configuration_reflection->setAccessible(TRUE);

      $additional_reflection = new \ReflectionProperty($component, 'additional');
      $additional_reflection->setAccessible(TRUE);

      $export[$delta] = [
        'uuid' => $component->getUuid(),
        'region' => $component->getRegion(),
        'configuration' => $configuration_reflection->getValue($component),
        'additional' => $additional_reflection->getValue($component),
        'weight' => $component->getWeight(),
      ];
    }
    return $export;
  }

  /**
   * {@inheritdoc}
   */
  protected function mapFromStorageRecords(array $records) {
    foreach ($records as $id => &$record) {
      if (!empty($record['third_party_settings']['layout_builder']['sections'])) {
        $sections = &$record['third_party_settings']['layout_builder']['sections'];
        foreach ($sections as $section_delta => $section) {
          $sections[$section_delta] = new Section(
            $section['layout_id'],
            $section['layout_settings'],
            $this->importComponents($section['components'])
          );
        }
      }
    }
    return parent::mapFromStorageRecords($records);
  }

  /**
   * Converts the array of component data back into objects.
   *
   * @param mixed[] $components
   *   An array of component data.
   *
   * @return \Drupal\layout_builder\SectionComponent[]
   *   An array of component objects.
   */
  protected function importComponents(array $components) {
    $import = [];
    foreach ($components as $delta => $component) {
      $import[$delta] = (new SectionComponent($component['uuid'], $component['region'], $component['configuration'], $component['additional']))->setWeight($component['weight']);
    }
    return $import;
  }

}
