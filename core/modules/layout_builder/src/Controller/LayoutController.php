<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\LayoutSectionBuilder;
use Drupal\layout_builder\Traits\TempstoreIdHelper;
use Drupal\user\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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
        'remove' => [
          '#type' => 'link',
          '#title' => $this->t('Remove section'),
          '#url' => Url::fromRoute('layout_builder.remove_section', [
            'entity_type' => $layout_section_entity->getEntityTypeId(),
            'entity' => $revision_id ?: $entity_id,
            'field_name' => $layout_section_field_name,
            'delta' => $count - 1,
          ]),
          '#attributes' => [
            'class' => ['use-ajax', 'remove-section'],
          ],
        ],
        'layout-section' => $this->builder->buildAdministrativeSection($item->layout, $item->section ? $item->section : [], $layout_section_entity->getEntityTypeId(), $revision_id ? $revision_id : $entity_id, $layout_section_field_name, $count - 1),
        '#suffix' => '</div>',
      ];
      $output[] = [
        '#markup' => "<div class=\"add-section\">" . $this->l('Add Section', $url->setRouteParameter('delta', $count)) . "</div>"
      ];
      $count++;
    }
    $output['#attached']['library'][] = 'layout_builder/drupal.layout_builder';
    $output['#prefix'] = '<div id="layout-builder">';
    $output['#suffix'] = '</div>';
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
      $icon = $definition->getIconPath();
      if ($icon) {
        $icon = [
          '#theme' => 'image',
          '#uri' => $icon,
          '#alt' => $definition->getLabel(),
          '#theme_wrappers' => [
            'container' => [
              '#attributes' => [
                'class' => ['layout-icon'],
              ],
            ],
          ],
        ];
      }

      $items[] = [
        'icon' => $icon ?: [],
        'label' => [
          '#type' => 'link',
          '#title' => $definition->getLabel(),
          '#url' => $this->generateSectionUrl($entity_type, $entity, $field_name, $delta, $plugin_id),
          '#attributes' => [
            'class' => 'use-ajax',
          ],
        ],
      ];
    }
    $output['layouts'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#prefix' => '<details class="layout-selection" open="open"><summary class="title">Basic Layouts</summary>',
      '#suffix'=> "</details>",
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
   * @return \Drupal\Core\Ajax\AjaxResponse
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
    return $this->ajaxRebuildLayout($entity, $field_name);
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
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The render array.
   */
  public function removeSection($entity_type, $entity, $field_name, $delta) {
    /** @var FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager()->getStorage($entity_type)->loadRevision($entity);
    list($collection, $id) = $this->generateTempstoreId($entity, $field_name);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    $entity->$field_name->removeItem($delta);
    $tempstore['entity'] = $entity;
    $this->tempStoreFactory->get($collection)->set($id, $tempstore);
    return $this->ajaxRebuildLayout($entity, $field_name);
  }

  public function chooseBlock($entity_type, $entity, $field_name, $delta, $region) {
    /** @var \Drupal\Core\Block\BlockManagerInterface $manager */
    $manager = \Drupal::service('plugin.manager.block');
    $build = [];
    foreach ($manager->getGroupedDefinitions() as $category => $blocks) {
      $build[$category]['title'] = ['#markup' => '<summary class="title">' . $category . '</summary>'];
      $build[$category]['links'] = [
        '#type' => 'table',
      ];
      foreach ($blocks as $block_id => $block) {
        $build[$category]['links'][]['data'] = [
          '#type' => 'link',
          '#title' => $block['admin_label'],
          '#url' => Url::fromRoute('layout_builder.add_block',
            [
              'entity_type' => $entity_type,
              'entity' => $entity,
              'field_name' => $field_name,
              'delta' => $delta,
              'region' => $region,
              'plugin_id' => $block_id,
            ]
          ),
          '#attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'dialog',
            'data-dialog-renderer' => 'off_canvas',
          ],
        ];
      }
      $build[$category]['#prefix'] = '<details open="open">';
      $build[$category]['#suffix'] = "</details>";
    }
    $build['#prefix'] = "<div class=\"block-categories\">";
    $build['#suffix'] = "</div>";
    return $build;
  }

  public function removeBlock($entity_type, $entity, $field_name, $delta, $region, $uuid) {
    $entity_from_storage = $this->entityTypeManager()->getStorage($entity_type)->loadRevision($entity);
    list($collection, $id) = $this->generateTempstoreId($entity_from_storage, $field_name);

    $entity = $this->tempStoreFactory->get($collection)->get($id)['entity'] ?: $entity_from_storage;

    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
    $field = $entity->$field_name->get($delta);
    $values = $field->section;
    unset($values[$region][$uuid]);
    $field->section = array_filter($values);

    $tempstore['entity'] = $entity;
    $this->tempStoreFactory->get($collection)->set($id, $tempstore);

    $redirect = Url::fromRoute('entity.' . $entity_type . '.layout', [
      $entity_type => $entity->id(),
    ]);

    return $this->ajaxRebuildLayout($entity, $field_name);
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
    $this->tempStoreFactory->get($collection)->delete($id);
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

  public function moveBlock(Request $request, $entity_type, $entity, $field_name) {
    /** @var FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager()->getStorage($entity_type)->loadRevision($entity);
    list($collection, $id) = $this->generateTempstoreId($entity, $field_name);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    $data = Json::decode($request->getContent());
//    region_from
//    region_to
//    block_uuid
//    delta_from
//    delta_to
//    preceding_block_uuid

    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
    $field = $entity->$field_name->get($data['delta_from']);
    $values = $field->section ? $field->section : [];

    $region_from = $data['region_from'];
    $region_to = $data['region_to'];
    $block_uuid = $data['block_uuid'];
    $configuration = $values[$region_from][$block_uuid];
    unset($values[$region_from][$block_uuid]);
    $field->section = array_filter($values);

    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
    $field = $entity->$field_name->get($data['delta_to']);
    $values = $field->section ? $field->section : [];
    if ($data['preceding_block_uuid']) {
      $slice_id = array_search($data['preceding_block_uuid'], array_keys($values[$region_to]));
      $before = array_slice($values[$region_to], 0, $slice_id + 1);
      $after = array_slice($values[$region_to], $slice_id + 1);
      $values[$region_to] = array_merge($before, [$block_uuid => $configuration], $after);
    }
    else {
      if (empty($values[$region_to])) {
        $values[$region_to] = [];
      }
      $values[$region_to] = array_merge([$block_uuid => $configuration], $values[$region_to]);
    }
    $field->section = array_filter($values);

    $tempstore['entity'] = $entity;
    $this->tempStoreFactory->get($collection)->set($id, $tempstore);

    return new AjaxResponse();
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

  /**
   * Rebuild layout, add Ajax commands replace and close dialog.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response to replace the layout and close the dialog.
   */
  protected function ajaxRebuildLayout(FieldableEntityInterface $entity, $field_name) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#layout-builder', $this->layout($entity, $field_name)));
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    return $response;
  }

}
