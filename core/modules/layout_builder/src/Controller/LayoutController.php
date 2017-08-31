<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Layout Builder routes.
 */
class LayoutController implements ContainerInjectionInterface {

  use LayoutRebuildTrait;
  use StringTranslationTrait;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * LayoutController constructor.
   *
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager
   *   The layout manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   */
  public function __construct(LayoutPluginManagerInterface $layout_manager, BlockManagerInterface $block_manager, LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->layoutManager = $layout_manager;
    $this->blockManager = $block_manager;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.core.layout'),
      $container->get('plugin.manager.block'),
      $container->get('layout_builder.tempstore_repository')
    );
  }

  /**
   * Choose a layout plugin to add as a section.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   The render array.
   */
  public function chooseSection($entity_type_id, $entity_id, $delta) {
    $output = [];
    $items = [];
    foreach ($this->layoutManager->getDefinitions() as $plugin_id => $definition) {
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
          '#url' => $this->generateSectionUrl($entity_type_id, $entity_id, $delta, $plugin_id),
          '#attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'dialog',
            'data-dialog-renderer' => 'off_canvas',
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
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $plugin_id
   *   The plugin ID of the layout to add.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The render array.
   */
  public function addSection($entity_type_id, $entity_id, $delta, $plugin_id) {
    $entity = $this->layoutTempstoreRepository->getFromId($entity_type_id, $entity_id);
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
    $this->layoutTempstoreRepository->set($entity);
    return $this->rebuildAndClose(new AjaxResponse(), $entity);
  }

  /**
   * Provides the UI for choosing a new block.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $region
   *   The region the block is going in.
   *
   * @return array
   *   A render array.
   */
  public function chooseBlock($entity_type_id, $entity_id, $delta, $region) {
    $build['#type'] = 'container';
    $build['#attributes']['class'][] = 'block-categories';

    foreach ($this->blockManager->getGroupedDefinitions() as $category => $blocks) {
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
              'entity_type_id' => $entity_type_id,
              'entity_id' => $entity_id,
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
   * Moves a block to another region.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  public function moveBlock(Request $request, $entity_type_id, $entity_id) {
    $entity = $this->layoutTempstoreRepository->getFromId($entity_type_id, $entity_id);
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
    if (isset($data['preceding_block_uuid'])) {
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

    $this->layoutTempstoreRepository->set($entity);
    return $this->rebuildLayout(new AjaxResponse(), $entity);
  }

  /**
   * A helper function for building Url object to add a section.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $plugin_id
   *   The plugin ID of the layout to add.
   *
   * @return \Drupal\Core\Url
   *   The Url object of the add_section route.
   */
  protected function generateSectionUrl($entity_type_id, $entity_id, $delta, $plugin_id) {
    return new Url('layout_builder.add_section', [
      'entity_type_id' => $entity_type_id,
      'entity_id' => $entity_id,
      'delta' => $delta,
      'plugin_id' => $plugin_id,
    ]);
  }

}
