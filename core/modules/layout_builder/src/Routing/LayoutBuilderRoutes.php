<?php

namespace Drupal\layout_builder\Routing;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for the Layout Builder UI.
 *
 * @internal
 */
class LayoutBuilderRoutes implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a new LayoutBuilderRoutes.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Generates layout builder routes.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function getRoutes() {
    $routes = [];

    foreach ($this->getEntityTypes() as $entity_type_id => $entity_type) {
      $defaults = [];
      $defaults['entity_type_id'] = $entity_type_id;
      $defaults['section_storage_type'] = 'overrides';
      // Provide an empty value to allow the section storage to be upcast.
      $defaults['section_storage'] = '';

      $requirements = [];
      if ($this->hasIntegerId($entity_type)) {
        $requirements[$entity_type_id] = '\d+';
      }

      $options = [];
      $options['parameters']['section_storage']['layout_builder_tempstore'] = TRUE;
      $options['parameters'][$entity_type_id]['type'] = 'entity:' . $entity_type_id;

      $template = $entity_type->getLinkTemplate('layout-builder');
      $routes += $this->buildRoute('entity.' . $entity_type_id, $template, $defaults, $requirements, $options);
    }
    return $routes;
  }

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The route build event.
   */
  public function onAlterRoutes(RouteBuildEvent $event) {
    $collection = $event->getRouteCollection();
    foreach ($this->getEntityTypes() as $entity_type_id => $entity_type) {
      if ($route_name = $entity_type->get('field_ui_base_route')) {
        // Try to get the route from the current collection.
        if (!$entity_route = $collection->get($route_name)) {
          continue;
        }
        $path = $entity_route->getPath() . '/display-layout';

        $defaults = [];
        $defaults['entity_type_id'] = $entity_type_id;
        $defaults['view_mode_name'] = 'default';

        $defaults['section_storage_type'] = 'defaults';
        // Provide an empty value to allow the section storage to be upcast.
        $defaults['section_storage'] = '';

        // If the entity type has no bundles and it doesn't use {bundle} in its
        // admin path, use the entity type.
        if (strpos($path, '{bundle}') === FALSE) {
          if (!$entity_type->hasKey('bundle')) {
            $defaults['bundle'] = $entity_type_id;
          }
          else {
            $defaults['bundle_key'] = $entity_type->getBundleEntityType();
          }
        }

        $requirements = [];
        $requirements['_field_ui_view_mode_access'] = 'administer ' . $entity_type_id . ' display';

        $options = $entity_route->getOptions();
        $options['parameters']['section_storage']['layout_builder_tempstore'] = TRUE;

        $routes = $this->buildRoute('entity.entity_view_display.' . $entity_type_id, $path, $defaults, $requirements, $options);
        foreach ($routes as $name => $route) {
          $collection->add($name, $route);
        }
      }
    }
  }

  /**
   * Builds the layout routes for the given values.
   *
   * @param string $route_name_prefix
   *   The prefix to use for the route name.
   * @param string $path
   *   The path patten for the routes.
   * @param array $defaults
   *   An array of default parameter values.
   * @param array $requirements
   *   An array of requirements for parameters.
   * @param array $options
   *   An array of options.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  protected function buildRoute($route_name_prefix, $path, array $defaults, array $requirements, array $options) {
    $routes = [];

    $defaults['is_rebuilding'] = FALSE;
    $requirements['_has_layout_section'] = 'true';
    $options['_layout_builder'] = TRUE;

    $main_defaults = $defaults;
    $main_defaults['_controller'] = '\Drupal\layout_builder\Controller\LayoutBuilderController::layout';
    $main_defaults['_title_callback'] = '\Drupal\layout_builder\Controller\LayoutBuilderController::title';
    $route = (new Route($path))
      ->setDefaults($main_defaults)
      ->setRequirements($requirements)
      ->setOptions($options);
    $routes["{$route_name_prefix}.layout_builder"] = $route;

    $save_defaults = $defaults;
    $save_defaults['_controller'] = '\Drupal\layout_builder\Controller\LayoutBuilderController::saveLayout';
    $route = (new Route("$path/save"))
      ->setDefaults($save_defaults)
      ->setRequirements($requirements)
      ->setOptions($options);
    $routes["{$route_name_prefix}.layout_builder_save"] = $route;

    $cancel_defaults = $defaults;
    $cancel_defaults['_controller'] = '\Drupal\layout_builder\Controller\LayoutBuilderController::cancelLayout';
    $route = (new Route("$path/cancel"))
      ->setDefaults($cancel_defaults)
      ->setRequirements($requirements)
      ->setOptions($options);
    $routes["{$route_name_prefix}.layout_builder_cancel"] = $route;

    return $routes;
  }

  /**
   * Determines if this entity type's ID is stored as an integer.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   An entity type.
   *
   * @return bool
   *   TRUE if this entity type's ID key is always an integer, FALSE otherwise.
   */
  protected function hasIntegerId(EntityTypeInterface $entity_type) {
    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type->id());
    return $field_storage_definitions[$entity_type->getKey('id')]->getType() === 'integer';
  }

  /**
   * Returns an array of relevant entity types.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity types.
   */
  protected function getEntityTypes() {
    return array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $entity_type) {
      return $entity_type->hasLinkTemplate('layout-builder');
    });
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run after \Drupal\field_ui\Routing\RouteSubscriber.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -110];
    return $events;
  }

}
