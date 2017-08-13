<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\layout_builder\LayoutSectionBuilder;
use Drupal\layout_builder\Traits\TempstoreIdHelper;
use Drupal\user\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Layout Builder routes.
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
   *   The layout section builder.
   * @param \Drupal\user\SharedTempStoreFactory $temp_store_factory
   *   The shared temp store factory.
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
   * Provides a title callback.
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
   * Renders the Layout UI.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $layout_section_entity
   *   The entity.
   *
   * @return array
   *   A render array.
   */
  public function layout(FieldableEntityInterface $layout_section_entity) {
    list($collection, $id) = $this->generateTempstoreId($layout_section_entity);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    list($entity_id, , $revision_id) = explode('.', $id);
    if (!empty($tempstore['entity'])) {
      $layout_section_entity = $tempstore['entity'];
    }
    $url = new Url(
      'layout_builder.choose_section',
      [
        'entity_type' => $layout_section_entity->getEntityTypeId(),
        'entity' => $revision_id ?: $entity_id,
      ],
      [
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
          'data-outside-in-edit' => TRUE,
        ],
      ]
    );
    $output = [];
    $count = 0;
    $link = Link::fromTextAndUrl($this->t('Add Section'), $url->setRouteParameter('delta', $count));
    $output[] = [
      'link' => $link->toRenderable(),
      '#type' => 'container',
      '#attributes' => [
        'class' => ['add-section'],
      ],
    ];
    $count++;
    foreach ($layout_section_entity->layout_builder__layout as $item) {
      $link->getUrl()->setRouteParameter('delta', $count);
      $output[] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['layout-section'],
        ],
        'remove' => [
          '#type' => 'link',
          '#title' => $this->t('Remove section'),
          '#url' => Url::fromRoute('layout_builder.remove_section', [
            'entity_type' => $layout_section_entity->getEntityTypeId(),
            'entity' => $revision_id ?: $entity_id,
            'delta' => $count - 1,
          ]),
          '#attributes' => [
            'class' => ['use-ajax', 'remove-section'],
            'data-dialog-type' => 'dialog',
            'data-dialog-renderer' => 'off_canvas',
          ],
        ],
        'layout-section' => $this->builder->buildAdministrativeSection($item->layout, $item->section ?: [], $layout_section_entity->getEntityTypeId(), $revision_id ?: $entity_id, $count - 1),
      ];
      $output[] = [
        'link' => $link->toRenderable(),
        '#type' => 'container',
        '#attributes' => [
          'class' => ['add-section'],
        ],
      ];
      $count++;
    }
    $output['#attached']['library'][] = 'layout_builder/drupal.layout_builder';
    $output['#type'] = 'container';
    $output['#attributes']['id'] = 'layout-builder';
    // Mark this UI as uncacheable.
    $output['#cache']['max-age'] = 0;
    return $output;
  }

  /**
   * Choose a layout plugin to add as a section.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity
   *   The entity ID.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   The render array.
   */
  public function chooseSection($entity_type, $entity, $delta) {
    $output = [];
    /** @var \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager */
    $layout_manager = \Drupal::service('plugin.manager.core.layout');
    $items = [];
    foreach ($layout_manager->getDefinitions() as $plugin_id => $definition) {
      $icon = $definition->getIconPath();
      if ($icon) {
        $icon = [
          '#theme' => 'image',
          '#uri' => $icon,
          '#alt' => $definition->getLabel(),
        ];
      }

      $items[] = [
        'label' => [
          '#type' => 'link',
          '#title' => [
            $icon ?: [],
            [
              '#type' => 'container',
              '#children' => $definition->getLabel(),
            ],
          ],
          '#url' => $this->generateSectionUrl($entity_type, $entity, $delta, $plugin_id),
          '#attributes' => [
            'class' => 'use-ajax',
          ],
        ],
      ];
    }
    $output['layouts'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic Layouts'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => [
          'layout-selection',
        ],
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => [
          'class' => [
            'layout-list',
          ],
        ],
      ],
    ];

    return $output;
  }

  /**
   * Add the layout to the entity field in a tempstore.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity
   *   The entity ID.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $plugin_id
   *   The plugin ID of the layout to add.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The render array.
   */
  public function addSection($entity_type, $entity, $delta, $plugin_id) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager()->getStorage($entity_type)->loadRevision($entity);
    list($collection, $id) = $this->generateTempstoreId($entity);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    $values = $entity->layout_builder__layout->getValue();
    if (isset($values[$delta])) {
      $start = array_slice($values, 0, $delta);
      $end = array_slice($values, $delta);
      $value = [
        'layout' => $plugin_id,
        'section' => [],
      ];
      $values = array_merge($start, [$value], $end);
    }
    else {
      $values[] = [
        'layout' => $plugin_id,
        'section' => [],
      ];
    }
    $entity->layout_builder__layout->setValue($values);
    $tempstore['entity'] = $entity;
    $this->tempStoreFactory->get($collection)->set($id, $tempstore);
    return $this->ajaxRebuildLayout($entity);
  }

  /**
   * Provides the UI for choosing a new block.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity
   *   The entity ID.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $region
   *   The region the block is going in.
   *
   * @return array
   *   A render array.
   */
  public function chooseBlock($entity_type, $entity, $delta, $region) {
    $build['#type'] = 'container';
    $build['#attributes']['class'][] = 'block-categories';

    /** @var \Drupal\Core\Block\BlockManagerInterface $manager */
    $manager = \Drupal::service('plugin.manager.block');
    foreach ($manager->getGroupedDefinitions() as $category => $blocks) {
      $build[$category]['#type'] = 'details';
      $build[$category]['#open'] = TRUE;
      $build[$category]['#title'] = $category;
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
    }
    return $build;
  }

  /**
   * Saves the layout.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $layout_section_entity
   *   The entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function saveLayout(FieldableEntityInterface $layout_section_entity) {
    list($collection, $id) = $this->generateTempstoreId($layout_section_entity);
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
   * Cancels the layout.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $layout_section_entity
   *   The entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function cancelLayout(FieldableEntityInterface $layout_section_entity) {
    list($collection, $id) = $this->generateTempstoreId($layout_section_entity);
    $this->tempStoreFactory->get($collection)->delete($id);
    // @todo Make trusted redirect instead.
    return new RedirectResponse($layout_section_entity->toUrl()->setAbsolute()->toString(), Response::HTTP_SEE_OTHER);
  }

  /**
   * Moves a block to another region.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $entity_type
   *   The entity type.
   * @param string $entity
   *   The entity ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  public function moveBlock(Request $request, $entity_type, $entity) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager()->getStorage($entity_type)->loadRevision($entity);
    list($collection, $id) = $this->generateTempstoreId($entity);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    $data = $request->request->all();

    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
    $field = $entity->layout_builder__layout->get($data['delta_from']);
    $values = $field->section ?: [];

    $region_from = $data['region_from'];
    $region_to = $data['region_to'];
    $block_uuid = $data['block_uuid'];
    $configuration = $values[$region_from][$block_uuid];
    unset($values[$region_from][$block_uuid]);
    $field->section = array_filter($values);

    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
    $field = $entity->layout_builder__layout->get($data['delta_to']);
    $values = $field->section ?: [];
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

    return $this->ajaxRebuildLayout($entity);
  }

  /**
   * A helper function for building Url object to add a section.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity
   *   The entity ID.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $plugin_id
   *   The plugin ID of the layout to add.
   *
   * @return \Drupal\Core\Url
   *   The Url object of the add_section route.
   */
  protected function generateSectionUrl($entity_type, $entity, $delta, $plugin_id) {
    return new Url('layout_builder.add_section', [
      'entity_type' => $entity_type,
      'entity' => $entity,
      'delta' => $delta,
      'plugin_id' => $plugin_id,
    ]);
  }

  /**
   * Rebuilds layout, add Ajax commands replace and close dialog.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response to replace the layout and close the dialog.
   */
  protected function ajaxRebuildLayout(FieldableEntityInterface $entity) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#layout-builder', $this->layout($entity)));
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    return $response;
  }

}
