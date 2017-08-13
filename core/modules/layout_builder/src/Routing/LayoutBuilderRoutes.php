<?php

namespace Drupal\layout_builder\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for the Layout Builder UI.
 */
class LayoutBuilderRoutes extends RouteSubscriberBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LayoutBuilderRoutes.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
      $template = $entity_type->getLinkTemplate('canonical');
      $route = (new Route("$template/layout"))
        ->setDefaults([
          '_controller' => '\Drupal\layout_builder\Controller\LayoutBuilderController::layout',
          '_title_callback' => '\Drupal\layout_builder\Controller\LayoutBuilderController::title',
          'layout_section_entity' => NULL,
          'entity_type_id' => $entity_type_id,
        ])
        ->addRequirements([
          $entity_type_id => '\d+',
          '_has_layout_selection' => 'true',
        ])
        ->addOptions([
          '_layout_builder' => TRUE,
          'parameters' => [
            $entity_type_id => [
              'type' => "entity:$entity_type_id",
            ],
          ],
        ]);
      $routes["entity.$entity_type_id.layout"] = $route;

      $route = (new Route("$template/layout/save"))
        ->setDefaults([
          '_controller' => '\Drupal\layout_builder\Controller\LayoutBuilderController::saveLayout',
          'layout_section_entity' => NULL,
          'entity_type_id' => $entity_type_id,
        ])
        ->addRequirements([
          $entity_type_id => '\d+',
          '_has_layout_selection' => 'true',
        ])
        ->addOptions([
          '_layout_builder' => TRUE,
          'parameters' => [
            $entity_type_id => [
              'type' => "entity:$entity_type_id",
            ],
          ],
        ]);
      $routes["entity.$entity_type_id.save_layout"] = $route;

      $route = (new Route("$template/layout/cancel"))
        ->setDefaults([
          '_controller' => '\Drupal\layout_builder\Controller\LayoutBuilderController::cancelLayout',
          'layout_section_entity' => NULL,
          'entity_type_id' => $entity_type_id,
        ])
        ->addRequirements([
          $entity_type_id => '\d+',
          '_has_layout_selection' => 'true',
        ])
        ->addOptions([
          '_layout_builder' => TRUE,
          'parameters' => [
            $entity_type_id => [
              'type' => "entity:$entity_type_id",
            ],
          ],
        ]);
      $routes["entity.$entity_type_id.cancel_layout"] = $route;
    }
    return $routes;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $templates = ['canonical', 'edit_form', 'delete_form'];
    foreach ($this->getEntityTypes() as $entity_type) {
      foreach ($templates as $template) {
        // Mark this as a Layout Builder route so that links like local tasks
        // will be enhanced.
        if ($route = $collection->get('entity.' . $entity_type->id() . '.' . $template)) {
          $route->setOption('_layout_builder', TRUE);
          $route->addDefaults([
            'layout_section_entity' => NULL,
            'entity_type_id' => $entity_type->id(),
          ]);
        }
      }
    }
  }

  /**
   * Returns an array of relevant entity types.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity types.
   */
  protected function getEntityTypes() {
    return array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $entity_type) {
      return $entity_type->entityClassImplements(FieldableEntityInterface::class) && $entity_type->hasLinkTemplate('canonical') && $entity_type->hasViewBuilderClass();
    });
  }

}
