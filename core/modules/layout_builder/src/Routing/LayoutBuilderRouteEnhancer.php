<?php


namespace Drupal\layout_builder\Routing;


use Drupal\Core\Routing\Enhancer\RouteEnhancerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

class LayoutBuilderRouteEnhancer implements RouteEnhancerInterface{

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route) {
    return ($route->hasOption('_layout_builder'));
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    $entity_type_id = $defaults['_route_object']->getOption('_layout_builder');
    $defaults['entity'] = $defaults[$entity_type_id];
    /** @var \Drupal\Core\Field\FieldItemListInterface $field */
    foreach ($defaults['entity'] as $field) {
      if ($field->getFieldDefinition()->getType() == 'layout_section') {
        $defaults['field_name'] = $field->getFieldDefinition()->getName();
        break;
      }
    }
    return $defaults;
  }

}
