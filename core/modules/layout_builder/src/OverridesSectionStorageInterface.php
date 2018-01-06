<?php

namespace Drupal\layout_builder;

/**
 * Defines an interface for an object that stores layout sections for overrides.
 */
interface OverridesSectionStorageInterface {

  /**
   * Returns the corresponding defaults section storage for this override.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The defaults section storage.
   */
  public function getDefaultSectionStorage();

}
