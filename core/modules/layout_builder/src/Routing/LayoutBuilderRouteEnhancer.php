<?php

namespace Drupal\layout_builder\Routing;

use Drupal\Core\Routing\Enhancer\RouteEnhancerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * @todo.
 */
class LayoutBuilderRouteEnhancer implements RouteEnhancerInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route) {
    return $route->hasOption('_layout_builder');
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    // Copy the entity by reference so that any changes are reflected.
    $defaults['layout_section_entity'] = &$defaults[$defaults['entity_type_id']];

    /** @var \Drupal\Core\Field\FieldItemListInterface $field */
    foreach ($defaults['layout_section_entity'] as $field) {
      if ($field->getFieldDefinition()->getType() == 'layout_section') {
        $defaults['layout_section_field_name'] = $field->getFieldDefinition()->getName();
        break;
      }
    }
    return $defaults;
  }

}
