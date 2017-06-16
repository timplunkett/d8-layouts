<?php

namespace Drupal\layout_builder\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Plugin\Menu\LayoutBuilderLocalTask;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for the layout builder user interface.
 */
class LayoutBuilderLocalTaskDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LayoutBuilderLocalTaskDeriver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
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
    foreach (array_keys($this->getEntityTypes()) as $entity_type_id) {
      $this->derivatives["entity.$entity_type_id.layout"] = $base_plugin_definition + [
        'route_name' => "entity.$entity_type_id.layout",
        'weight' => 15,
        'title' => $this->t('Layout'),
        'base_route' => "entity.$entity_type_id.canonical",
        'entity_type_id' => $entity_type_id,
        'class' => LayoutBuilderLocalTask::class,
      ];
      $this->derivatives["entity.$entity_type_id.save_layout"] = $base_plugin_definition + [
        'route_name' => "entity.$entity_type_id.save_layout",
        'title' => $this->t('Save Layout'),
        'parent_id' => "layout_builder_ui:entity.$entity_type_id.layout",
        'entity_type_id' => $entity_type_id,
        'class' => LayoutBuilderLocalTask::class,
      ];
      $this->derivatives["entity.$entity_type_id.cancel_layout"] = $base_plugin_definition + [
        'route_name' => "entity.$entity_type_id.cancel_layout",
        'title' => $this->t('Cancel Layout'),
        'parent_id' => "layout_builder_ui:entity.$entity_type_id.layout",
        'entity_type_id' => $entity_type_id,
        'class' => LayoutBuilderLocalTask::class,
        'weight' => 5,
      ];
    }

    return $this->derivatives;
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
