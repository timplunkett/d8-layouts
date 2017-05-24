<?php

namespace Drupal\layout_builder\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\Routing\Route;

/**
 * @todo.
 */
class LayoutBuilderRoutes {

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

    foreach ($this->getFieldableEntityCanonicalLinkTemplates() as $entity_type_id => $template) {
      $route = (new Route("$template/layout"))
        ->setDefaults([
          '_controller' => '\Drupal\layout_builder\Controller\LayoutController::layout',
          '_title_callback' => '\Drupal\layout_builder\Controller\LayoutController::title',
          'layout_section_entity' => NULL,
          'layout_section_field_name' => NULL,
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
          '_controller' => '\Drupal\layout_builder\Controller\LayoutController::saveLayout',
          'layout_section_entity' => NULL,
          'layout_section_field_name' => NULL,
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
          '_controller' => '\Drupal\layout_builder\Controller\LayoutController::cancelLayout',
          'layout_section_entity' => NULL,
          'layout_section_field_name' => NULL,
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
   * Returns an array of canonical link templates for fieldable entities.
   *
   * @return string[]
   *   An array of canonical link templates.
   */
  protected function getFieldableEntityCanonicalLinkTemplates() {
    $templates = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->entityClassImplements(FieldableEntityInterface::class) && $entity_type->hasLinkTemplate('canonical') && $entity_type->hasViewBuilderClass()) {
        $templates[$entity_type_id] = $entity_type->getLinkTemplate('canonical');
      }
    }
    return $templates;
  }

}
