<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\LayoutSectionBuilder;
use Drupal\layout_builder\LayoutSectionItemInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Layout Builder routes.
 */
class LayoutBuilderController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The layout builder.
   *
   * @var \Drupal\layout_builder\LayoutSectionBuilder
   */
  protected $builder;

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
   * @param \Drupal\layout_builder\LayoutSectionBuilder $builder
   *   The layout section builder.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager
   *   The layout manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   */
  public function __construct(LayoutSectionBuilder $builder, LayoutPluginManagerInterface $layout_manager, BlockManagerInterface $block_manager, LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->builder = $builder;
    $this->layoutManager = $layout_manager;
    $this->blockManager = $block_manager;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.builder'),
      $container->get('plugin.manager.core.layout'),
      $container->get('plugin.manager.block'),
      $container->get('layout_builder.tempstore_repository')
    );
  }

  /**
   * Provides a title callback.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The title for the layout page.
   */
  public function title(EntityInterface $entity) {
    return $this->t('Edit layout for %label', ['%label' => $entity->label()]);
  }

  /**
   * Renders the Layout UI.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   A render array.
   */
  public function layout(EntityInterface $entity) {
    $entity_id = $entity->id();
    $entity_type_id = $entity->getEntityTypeId();

    $output = [];
    $count = 0;
    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $item */
    foreach ($entity->layout_builder__layout as $item) {
      $output[] = $this->buildAddSectionLink($entity_type_id, $entity_id, $count);
      $output[] = $this->buildAdministrativeSection($item, $entity, $count);
      $count++;
    }
    $output[] = $this->buildAddSectionLink($entity_type_id, $entity_id, $count);
    $output['#attached']['library'][] = 'layout_builder/drupal.layout_builder';
    $output['#type'] = 'container';
    $output['#attributes']['id'] = 'layout-builder';
    // Mark this UI as uncacheable.
    $output['#cache']['max-age'] = 0;
    return $output;
  }

  /**
   * Builds a link to add a new section at a given delta.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   A render array for a link.
   */
  protected function buildAddSectionLink($entity_type_id, $entity_id, $delta) {
    return [
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Add Section'),
        '#url' => Url::fromRoute('layout_builder.choose_section',
          [
            'entity_type_id' => $entity_type_id,
            'entity' => $entity_id,
            'delta' => $delta,
          ],
          [
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'dialog',
              'data-dialog-renderer' => 'off_canvas',
            ],
          ]
        ),
      ],
      '#type' => 'container',
      '#attributes' => [
        'class' => ['add-section'],
      ],
    ];
  }

  /**
   * Builds the render array for the layout section while editing.
   *
   * @param \Drupal\layout_builder\LayoutSectionItemInterface $item
   *   The layout section item.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param int $delta
   *   The delta of the section.
   *
   * @return array
   *   The render array for a given section.
   */
  protected function buildAdministrativeSection(LayoutSectionItemInterface $item, EntityInterface $entity, $delta) {
    $entity_type_id = $entity->getEntityTypeId();
    $entity_id = $entity->id();

    $layout = $this->layoutManager->createInstance($item->layout, $item->layout_settings);
    $build = $this->builder->buildSectionFromLayout($layout, $item->section);
    $layout_definition = $layout->getPluginDefinition();

    foreach ($layout_definition->getRegions() as $region => $info) {
      if (!empty($build[$region])) {
        foreach ($build[$region] as $uuid => $block) {
          $build[$region][$uuid]['#attributes']['class'][] = 'draggable';
          $build[$region][$uuid]['#attributes']['data-layout-block-uuid'] = $uuid;
          $build[$region][$uuid]['#contextual_links'] = [
            'layout_builder_block' => [
              'route_parameters' => [
                'entity_type_id' => $entity_type_id,
                'entity' => $entity_id,
                'delta' => $delta,
                'region' => $region,
                'uuid' => $uuid,
              ],
            ],
          ];
        }
      }

      $build[$region]['layout_builder_add_block']['link'] = [
        '#type' => 'link',
        '#title' => $this->t('Add Block'),
        '#url' => Url::fromRoute('layout_builder.choose_block',
          [
            'entity_type_id' => $entity_type_id,
            'entity' => $entity_id,
            'delta' => $delta,
            'region' => $region,
          ],
          [
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'dialog',
              'data-dialog-renderer' => 'off_canvas',
            ],
          ]
        ),
      ];
      $build[$region]['layout_builder_add_block']['#type'] = 'container';
      $build[$region]['layout_builder_add_block']['#attributes'] = ['class' => ['add-block']];
      $build[$region]['#attributes']['data-region'] = $region;
      $build[$region]['#attributes']['class'][] = 'layout-builder--layout__region';
    }

    $build['#attributes']['data-layout-update-url'] = Url::fromRoute('layout_builder.move_block', [
      'entity_type_id' => $entity_type_id,
      'entity' => $entity_id,
    ])->toString();
    $build['#attributes']['data-layout-delta'] = $delta;
    $build['#attributes']['class'][] = 'layout-builder--layout';

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-section'],
      ],
      'configure' => [
        '#type' => 'link',
        '#title' => $this->t('Configure section'),
        '#access' => $layout instanceof PluginFormInterface,
        '#url' => Url::fromRoute('layout_builder.configure_section', [
          'entity_type_id' => $entity_type_id,
          'entity' => $entity_id,
          'delta' => $delta,
        ]),
        '#attributes' => [
          'class' => ['use-ajax', 'configure-section'],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
        ],
      ],
      'remove' => [
        '#type' => 'link',
        '#title' => $this->t('Remove section'),
        '#url' => Url::fromRoute('layout_builder.remove_section', [
          'entity_type_id' => $entity_type_id,
          'entity' => $entity_id,
          'delta' => $delta,
        ]),
        '#attributes' => [
          'class' => ['use-ajax', 'remove-section'],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
        ],
      ],
      'layout-section' => $build,
    ];
  }

  /**
   * Saves the layout.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function saveLayout(EntityInterface $entity) {
    $entity->save();
    $this->layoutTempstoreRepository->delete($entity);
    return new RedirectResponse($entity->toUrl()->setAbsolute()->toString());
  }

  /**
   * Cancels the layout.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function cancelLayout(EntityInterface $entity) {
    $this->layoutTempstoreRepository->delete($entity);
    return new RedirectResponse($entity->toUrl()->setAbsolute()->toString());
  }

}
