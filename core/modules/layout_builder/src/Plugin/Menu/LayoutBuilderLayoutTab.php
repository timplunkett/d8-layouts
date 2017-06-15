<?php


namespace Drupal\layout_builder\Plugin\Menu;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides route parameters needed to link to layout related tabs.
 */
class LayoutBuilderLayoutTab extends LocalTaskDefault {

  /**
   * The current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Gets the entity type id with which we are working.
   *
   * @return string
   */
  protected function getEntityTypeId() {
    list(, $entity_type_id,) = explode('.', $this->getPluginId());
    return $entity_type_id;
  }

  /**
   * Gets the current request object.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   */
  protected function currentRequest() {
    if (!$this->currentRequest) {
      /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
      $request_stack = \Drupal::service('request_stack');
      $request = $request_stack->getCurrentRequest();
      $defaults = $request->attributes->all();
      $defaults['entity_type_id'] = $this->getEntityTypeId();
      /** @var \Symfony\Component\Routing\Route $route */
      $route = $defaults['_route_object'];
      $route->addOptions(['_layout_builder' => TRUE]);
      /** @var \Drupal\Core\Routing\Enhancer\RouteEnhancerInterface $enhancer */
      $enhancer = \Drupal::service('layout_builder.route_enhancer');
      $request->attributes->add($enhancer->enhance($defaults, $request));
      $this->currentRequest = $request;
    }
    return $this->currentRequest;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $parameters = parent::getRouteParameters($route_match);
    $request = $this->currentRequest();
    $parameters['layout_section_entity'] = $request->attributes->get('layout_section_entity');
    $parameters['layout_section_field_name'] = $request->attributes->get('layout_section_field_name');
    return $parameters;
  }

}
