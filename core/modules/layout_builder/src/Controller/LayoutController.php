<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * @todo.
 */
class LayoutController extends ControllerBase {

  /**
   * @todo.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $layout_section_entity
   *   The entity.
   * @param string $layout_section_field_name
   *   The field name.
   *
   * @return array
   *   A render array.
   */
  public function layout(FieldableEntityInterface $layout_section_entity, $layout_section_field_name) {
    // Render the layout section field in isolation, with no label.
    return $layout_section_entity->{$layout_section_field_name}->view(['label' => 'hidden']);
  }

}
