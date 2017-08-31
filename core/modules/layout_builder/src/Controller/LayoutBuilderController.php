<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\LayoutSectionBuilder;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

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
    $layout_section_entity = $this->layoutTempstoreRepository->get($layout_section_entity);
    $entity_id = $layout_section_entity->id();
    if ($layout_section_entity instanceof RevisionableInterface) {
      $entity_id = $layout_section_entity->getRevisionId();
    }

    $entity_type_id = $layout_section_entity->getEntityTypeId();

    $output = [];
    $count = 0;
    $output[] = $this->buildAddSectionLink($entity_type_id, $entity_id, $count);
    $count++;
    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $item */
    foreach ($layout_section_entity->layout_builder__layout as $item) {
      $output[] = $this->buildAdministrativeSection($item->layout, $item->section ?: [], $entity_type_id, $entity_id, $count - 1);
      $output[] = $this->buildAddSectionLink($entity_type_id, $entity_id, $count);
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
    $link = Link::createFromRoute($this->t('Add Section'),
      'layout_builder.choose_section',
      [
        'entity_type_id' => $entity_type_id,
        'entity_id' => $entity_id,
        'delta' => $delta,
      ],
      [
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
        ],
      ]
    );
    return [
      'link' => $link->toRenderable(),
      '#type' => 'container',
      '#attributes' => [
        'class' => ['add-section'],
      ],
    ];
  }

  /**
   * Builds the render array for the layout section while editing.
   *
   * @param string $layout_id
   *   The ID of the layout.
   * @param array $section
   *   An array of configuration, keyed first by region and then by block UUID.
   * @param string $entity_type_id
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   The render array for a given section.
   */
  protected function buildAdministrativeSection($layout_id, array $section, $entity_type_id, $entity_id, $delta) {
    $build = $this->builder->buildSection($layout_id, $section);
    $layout = $this->layoutManager->getDefinition($layout_id);
    foreach ($layout->getRegions() as $region => $info) {
      $link = Link::createFromRoute($this->t('Add Block'),
        'layout_builder.choose_block',
        [
          'entity_type_id' => $entity_type_id,
          'entity_id' => $entity_id,
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
      );
      $build[$region]['layout_builder_add_block']['link'] = $link->toRenderable();
      $build[$region]['layout_builder_add_block']['#type'] = 'container';
      $build[$region]['layout_builder_add_block']['#attributes'] = ['class' => ['add-block']];
      $build[$region]['#attributes']['data-region'] = $region;
    }
    foreach ($section as $region => $blocks) {
      foreach ($blocks as $uuid => $configuration) {
        if (isset($build[$region][$uuid])) {
          $build[$region][$uuid]['#attributes']['class'][] = 'draggable';
          $build[$region][$uuid]['#attributes']['data-layout-block-uuid'] = $uuid;
          $build[$region][$uuid]['#contextual_links'] = [
            'layout_builder_block' => [
              'route_parameters' => [
                'entity_type_id' => $entity_type_id,
                'entity_id' => $entity_id,
                'delta' => $delta,
                'region' => $region,
                'uuid' => $uuid,
              ],
            ],
          ];
        }
      }
    }

    $build['#attributes']['data-layout-update-url'] = Url::fromRoute('layout_builder.move_block', [
      'entity_type_id' => $entity_type_id,
      'entity_id' => $entity_id,
    ])->toString();
    $build['#attributes']['data-layout-delta'] = $delta;

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-section'],
      ],
      'remove' => [
        '#type' => 'link',
        '#title' => $this->t('Remove section'),
        '#url' => Url::fromRoute('layout_builder.remove_section', [
          'entity_type_id' => $entity_type_id,
          'entity_id' => $entity_id,
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
   * @param \Drupal\Core\Entity\FieldableEntityInterface $layout_section_entity
   *   The entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function saveLayout(FieldableEntityInterface $layout_section_entity) {
    $layout_section_entity = $this->layoutTempstoreRepository->get($layout_section_entity);

    // @todo figure out if we should save a new revision.
    $layout_section_entity->save();

    $this->layoutTempstoreRepository->delete($layout_section_entity);

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
    $this->layoutTempstoreRepository->delete($layout_section_entity);
    // @todo Make trusted redirect instead.
    return new RedirectResponse($layout_section_entity->toUrl()->setAbsolute()->toString(), Response::HTTP_SEE_OTHER);
  }

}
