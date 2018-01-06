<?php

namespace Drupal\layout_builder\Entity;

use Drupal\Core\Entity\Display\EntityDisplayInterface;

/**
 * Provides an interface for entity displays that have layout.
 *
 * @internal
 */
interface LayoutEntityDisplayInterface extends EntityDisplayInterface {

  /**
   * Determines if the display allows custom overrides.
   *
   * @return bool
   *   TRUE if custom overrides are allowed, FALSE otherwise.
   */
  public function isOverridable();

  /**
   * Sets the display to allow or disallow overrides.
   *
   * @param bool $overridable
   *   TRUE if the display should allow overrides, FALSE otherwise.
   *
   * @return $this
   */
  public function setOverridable($overridable = TRUE);

}
