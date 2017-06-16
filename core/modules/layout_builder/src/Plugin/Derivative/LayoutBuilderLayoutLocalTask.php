<?php

namespace Drupal\layout_builder\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for layout builder user interface.
 */
class LayoutBuilderLayoutLocalTask extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * LayoutBuilderLayoutLocalTask constructor.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->hasLinkTemplate('canonical')) {
        $this->derivatives["entity.$entity_type_id.layout"] = [
          'route_name' => "entity.$entity_type_id.layout",
          'weight' => 11,
          'title' => $this->t('Layout'),
          'base_route' => "entity.$entity_type_id.canonical",
        ];
        $this->derivatives["entity.$entity_type_id.save_layout"] = [
          'route_name' => "entity.$entity_type_id.save_layout",
          'weight' => 11,
          'title' => $this->t('Save Layout'),
          'base_route' => "entity.$entity_type_id.layout",
          'parent_id' => "layout_builder_ui:entity.$entity_type_id.layout",
        ];
        $this->derivatives["entity.$entity_type_id.cancel_layout"] = [
          'route_name' => "entity.$entity_type_id.cancel_layout",
          'weight' => 11,
          'title' => $this->t('Cancel Layout'),
          'base_route' => "entity.$entity_type_id.layout",
          'parent_id' => "layout_builder_ui:entity.$entity_type_id.layout",
        ];
      }
    }
    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
