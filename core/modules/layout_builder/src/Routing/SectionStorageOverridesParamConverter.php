<?php

namespace Drupal\layout_builder\Routing;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\layout_builder\SectionStorageParamConverterInterface;

/**
 * Provides a param converter for overrides-based section storage.
 */
class SectionStorageOverridesParamConverter extends EntityConverter implements SectionStorageParamConverterInterface {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_id = NULL;
    if (strpos($value, ':') !== FALSE) {
      list(, $entity_id) = explode(':', $value);
    }
    else {
      $entity_id = $defaults[$defaults['entity_type_id']];
    }

    if ($entity_id) {
      $entity = parent::convert($entity_id, $definition, $name, $defaults);
      if ($entity instanceof FieldableEntityInterface && $entity->hasField('layout_builder__layout')) {
        return $entity->get('layout_builder__layout');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeFromDefaults($definition, $name, array $defaults) {
    if (isset($defaults[$name]) && strpos($defaults[$name], ':') !== FALSE) {
      list($entity_type_id) = explode(':', $defaults[$name], 2);
      return $entity_type_id;
    }
    elseif (isset($defaults['entity_type_id'])) {
      return $defaults['entity_type_id'];
    }
  }

}
