<?php

namespace Drupal\layout_builder;

use Drupal\Core\Field\FieldItemInterface;

/**
 * Defines an interface for the layout section field item.
 *
 * @property string layout
 * @property array[] layout_settings
 * @property array[] section
 */
interface LayoutSectionItemInterface extends FieldItemInterface {

}
