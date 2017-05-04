<?php


namespace Drupal\layout_builder\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Routing\Route;

class LayoutBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * LayoutBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Generates layout administration routes.
   *
   * @return \Symfony\Component\Routing\Route[]
   */
  public function routes() {
    $routes = [];

    foreach ($this->getCanonicalFieldableEntityLinkTemplates() as $entityTypeId => $template) {
      $route = (new Route("$template/layout"))
        ->setDefaults([
          '_controller' => '\Drupal\layout_builder\Controller\LayoutController::layout',
          'title' => 'Layout',
        ])
        ->addRequirements([
          $entityTypeId => '\d+',
          '_has_layout_selection' => 'true'
        ])
        ->addOptions([
          '_layout_builder' => $entityTypeId,
          '_node_operation_route' => 'true',
          'parameters' => [
            $entityTypeId => [
              'type' => "entity:$entityTypeId"
            ]
          ]
        ]);
      $routes["entity.$entityTypeId.layout"] = $route;
    }
    return $routes;
  }

  /**
   * Returns an array of canonical link templates for fieldable entities.
   *
   * @return array
   */
  protected function getCanonicalFieldableEntityLinkTemplates() {
    $storage = [];
    /** @var \Drupal\Core\Entity\EntityType $entityType */
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if ($entityType->isSubclassOf('\Drupal\Core\Entity\FieldableEntityInterface') && $entityType->hasLinkTemplate('canonical') && $entityType->hasHandlerClass('view_builder')) {
        $storage[$entityTypeId] = $entityType->getLinkTemplate('canonical');
      }
    }
    return $storage;
  }

}
