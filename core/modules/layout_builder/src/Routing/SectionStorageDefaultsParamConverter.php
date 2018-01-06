<?php

namespace Drupal\layout_builder\Routing;

use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\layout_builder\SectionStorageParamConverterInterface;

/**
 * Provides a param converter for defaults-based section storage.
 */
class SectionStorageDefaultsParamConverter extends EntityConverter implements SectionStorageParamConverterInterface {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!$value) {
      // If a bundle is not provided but a value corresponding to the bundle key
      // is, use that for the bundle value.
      if (empty($defaults['bundle']) && !empty($defaults[$defaults['bundle_key']])) {
        $defaults['bundle'] = $defaults[$defaults['bundle_key']];
      }

      $value = $defaults['entity_type_id'] . '.' . $defaults['bundle'] . '.' . $defaults['view_mode_name'];
    }
    return parent::convert($value, $definition, $name, $defaults);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeFromDefaults($definition, $name, array $defaults) {
    return 'entity_view_display';
  }

}
