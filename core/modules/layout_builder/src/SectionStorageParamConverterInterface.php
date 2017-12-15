<?php

namespace Drupal\layout_builder;

/**
 * Defines the interface of a param converter for section storage.
 */
interface SectionStorageParamConverterInterface {

  /**
   * Converts path variables to their corresponding objects.
   *
   * @param mixed $value
   *   The raw value.
   * @param mixed $definition
   *   The parameter definition provided in the route options.
   * @param string $name
   *   The name of the parameter.
   * @param array $defaults
   *   The route defaults array.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   The section storage if it could be loaded, or NULL otherwise.
   */
  public function convert($value, $definition, $name, array $defaults);

}
