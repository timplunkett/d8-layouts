<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\LayoutSectionBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @todo.
 */
class LayoutController extends ControllerBase {

  /**
   * The layout builder.
   *
   * @var \Drupal\layout_builder\LayoutSectionBuilder
   */
  protected $builder;

  /**
   * LayoutController constructor.
   *
   * @param \Drupal\layout_builder\LayoutSectionBuilder $builder
   */
  public function __construct(LayoutSectionBuilder $builder) {
    $this->builder = $builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('layout_builder.builder'));
  }

  /**
   * @todo.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $layout_section_entity
   *   The entity.
   *
   * @return string
   *   The title for the layout page.
   */
  public function title(FieldableEntityInterface $layout_section_entity) {
    return $this->t('Edit layout for %label', ['%label' => $layout_section_entity->label()]);
  }

  /**
   * @todo.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $layout_section_entity
   *   The entity.
   * @param string $layout_section_field_name
   *   The field name.
   *
   * @return array
   *   A render array.
   */
  public function layout(FieldableEntityInterface $layout_section_entity, $layout_section_field_name) {
    $url = new Url(
      'layout_builder.choose_section',
      ['entity_type' => $layout_section_entity->getEntityType()->id(), 'entity' => $layout_section_entity->id(), 'field_name' => $layout_section_field_name],
      ['attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
        'data-outside-in-edit' => TRUE,
      ]]
    );
    $output = [];
    $count = 0;
    $output[] = [
      '#markup' => "<div class=\"add-section\">" . $this->l('Add Section', $url->setRouteParameter('delta', $count)) . "</div>"
    ];
    $count++;
    foreach ($layout_section_entity->$layout_section_field_name as $item) {
      $output[] = $this->builder->buildSection($item);
      $output[] = [
        '#markup' => "<div class=\"add-section\">" . $this->l('Add Section', $url->setRouteParameter('delta', $count)) . "</div>"
      ];
      $count++;
    }
    $output['#attached']['library'][] = 'layout_builder/drupal.layout_builder';
    return $output;
  }

  /**
   * Choose a layout plugin to add as a section.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity
   *   The entity id.
   * @param string $field_name
   *   The layout field name.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   The render array.
   */
  public function chooseSection($entity_type, $entity, $field_name, $delta) {
    $output = [];
    /** @var PluginManagerInterface $layout_manager */
    $layout_manager = \Drupal::service('plugin.manager.core.layout');
    /**
     * @var string $plugin_id
     * @var \Drupal\Core\Layout\LayoutDefinition $definition
     */
    foreach ($layout_manager->getDefinitions() as $plugin_id => $definition) {
      $output[] = [
        '#markup' => $this->l($definition->getLabel(), $this->generateSectionUrl($entity_type, $entity, $field_name, $delta, $plugin_id)),
      ];
    }
    return $output;
  }

  /**
   * Add the layout to the entity field in a tempstore.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity
   *   The entity id.
   * @param string $field_name
   *   The layout field name.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $plugin_id
   *   The plugin id of the layout to add.
   *
   * @return array
   *   The render array.
   */
  public function addSection($entity_type, $entity, $field_name, $delta, $plugin_id) {
    return [];
  }

  /**
   * A helper function for building Url object to add a section.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity
   *   The entity id.
   * @param string $field_name
   *   The layout field name.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $plugin_id
   *   The plugin id of the layout to add.
   *
   * @return \Drupal\Core\Url
   *   The Url object of the add_section route.
   */
  protected function generateSectionUrl($entity_type, $entity, $field_name, $delta, $plugin_id) {
    return new Url('layout_builder.add_section', ['entity_type' => $entity_type, 'entity' => $entity, 'field_name' => $field_name, 'delta' => $delta, 'plugin_id' => $plugin_id]);
  }

}
