<?php

namespace Drupal\layout_builder\Plugin\Menu;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides route parameters needed to link to layout related tabs.
 */
class LayoutBuilderLocalTask extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $parameters = parent::getRouteParameters($route_match);

    $parameters['layout_section_entity'] = $route_match->getParameter('layout_section_entity');
    return $parameters;
  }

}
