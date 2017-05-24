<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\layout_builder\LayoutSectionBuilder;
use Drupal\layout_builder\Traits\TempstoreIdHelper;
use Drupal\user\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @todo.
 */
class LayoutController extends ControllerBase {
  use TempstoreIdHelper;

  /**
   * The layout builder.
   *
   * @var \Drupal\layout_builder\LayoutSectionBuilder
   */
  protected $builder;

  /**
   * The Shared TempStore Factory.
   *
   * @var \Drupal\user\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * LayoutController constructor.
   *
   * @param \Drupal\layout_builder\LayoutSectionBuilder $builder
   */
  public function __construct(LayoutSectionBuilder $builder, SharedTempStoreFactory $temp_store_factory) {
    $this->builder = $builder;
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.builder'),
      $container->get('user.shared_tempstore')
    );
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
    list($collection, $id) = $this->generateTempstoreId($layout_section_entity, $layout_section_field_name);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    list($entity_id, $language, $revision_id) = explode('.', $id);
    if (!empty($tempstore['entity'])) {
      $layout_section_entity = $tempstore['entity'];
    }
    $url = new Url(
      'layout_builder.choose_section',
      ['entity_type' => $layout_section_entity->getEntityTypeId(), 'entity' => $revision_id ? $revision_id : $entity_id, 'field_name' => $layout_section_field_name],
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
      $output[] = [
        '#prefix' => '<div class="layout-section">',
        'layout-section' => $this->builder->buildAdministrativeSection($item->layout, $item->section ? $item->section : [], $layout_section_entity->getEntityTypeId(), $revision_id ? $revision_id : $entity_id, $layout_section_field_name, $count - 1),
        '#suffix' => '</div>',
      ];
      $output[] = [
        '#markup' => "<div class=\"add-section\">" . $this->l('Add Section', $url->setRouteParameter('delta', $count)) . "</div>"
      ];
      $count++;
    }
    $output['#attached']['library'][] = 'layout_builder/drupal.layout_builder';
    $output['#attached']['library'][] = 'outside_in/drupal.off_canvas';
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
    $items = [];
    /**
     * @var string $plugin_id
     * @var \Drupal\Core\Layout\LayoutDefinition $definition
     */
    foreach ($layout_manager->getDefinitions() as $plugin_id => $definition) {
      $items[] = [
        '#markup' => $this->l($definition->getLabel(), $this->generateSectionUrl($entity_type, $entity, $field_name, $delta, $plugin_id)),
      ];
    }
    $output['layouts'] = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
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
   * @return TrustedRedirectResponse
   *   The render array.
   */
  public function addSection($entity_type, $entity, $field_name, $delta, $plugin_id) {
    /** @var FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager()->getStorage($entity_type)->loadRevision($entity);
    list($collection, $id) = $this->generateTempstoreId($entity, $field_name);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    $values = $entity->$field_name->getValue();
    if (isset($values[$delta])) {
      $start = array_slice($values, 0, $delta);
      $end = array_slice($values, $delta);
      $value = [
        'layout' => $plugin_id,
        'section' => []
      ];
      $values = array_merge($start, [$value], $end);
    }
    else {
      $values[] = [
        'layout' => $plugin_id,
        'section' => []
      ];
    }
    $entity->$field_name->setValue($values);
    $tempstore['entity'] = $entity;
    $this->tempStoreFactory->get($collection)->set($id, $tempstore);
    $path = '/'. $this->getUrlGenerator()->getPathFromRoute("entity.$entity_type.layout", [$entity_type => $entity->id()]);
    return new TrustedRedirectResponse($path);
  }

  public function chooseBlock($entity_type, $entity, $field_name, $delta, $region) {
    /** @var \Drupal\Core\Block\BlockManagerInterface $manager */
    $manager = \Drupal::service('plugin.manager.block');
    // An array of blocks by categories.
    $blocks = [];
    foreach ($manager->getDefinitions() as $plugin_id => $definition) {
      $blocks[$definition['category']][] = [
        '#markup' => $this->l($definition['admin_label'], new Url('layout_builder.add_block', ['entity_type' => $entity_type, 'entity' => $entity, 'field_name' => $field_name, 'delta' => $delta, 'region' => $region, 'plugin_id' => $plugin_id]))
      ];
    }
    $build = [];
    ksort($blocks, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($blocks as $category => $links) {
      $build[$category]['title'] = ['#markup' => '<h3>' . $category . '</h3>'];
      $build[$category]['links'] = [
        '#theme' => 'item_list',
        '#items' => $links
      ];
    }
    $build['#prefix'] = "<div class=\"block-categories\">";
    $build['#suffix'] = "</div>";
    return $build;
  }

  /**
   * Save the layout.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $layout_section_entity
   *   The entity.
   * @param string $layout_section_field_name
   *   The field name.
   *
   * @return RedirectResponse
   *   A redirect response.
   */
  public function saveLayout(FieldableEntityInterface $layout_section_entity, $layout_section_field_name) {
    list($collection, $id) = $this->generateTempstoreId($layout_section_entity, $layout_section_field_name);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
      if (!empty($tempstore['entity'])) {
      $layout_section_entity = $tempstore['entity'];
    }
    // @todo figure out if we should save a new revision.
    $layout_section_entity->save();
    // @todo Make trusted redirect instead.
    return new RedirectResponse($layout_section_entity->toUrl()->setAbsolute()->toString(), Response::HTTP_SEE_OTHER);
  }


  /**
   * Cancel the layout.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $layout_section_entity
   *   The entity.
   * @param string $layout_section_field_name
   *   The field name.
   *
   * @return RedirectResponse
   *   A redirect response.
   */
  public function cancelLayout(FieldableEntityInterface $layout_section_entity, $layout_section_field_name) {
    list($collection, $id) = $this->generateTempstoreId($layout_section_entity, $layout_section_field_name);
    $this->tempStoreFactory->get($collection)->delete($id);
    // @todo Make trusted redirect instead.
    return new RedirectResponse($layout_section_entity->toUrl()->setAbsolute()->toString(), Response::HTTP_SEE_OTHER);
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
