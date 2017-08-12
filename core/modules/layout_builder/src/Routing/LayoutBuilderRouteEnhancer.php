<?php

namespace Drupal\layout_builder\Routing;

use Drupal\Core\Routing\Enhancer\RouteEnhancerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Enhances routes to ensure the entity is available with a generic name.
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
    return $defaults;
  }

}
