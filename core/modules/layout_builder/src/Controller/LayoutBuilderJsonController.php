<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\layout_builder\LayoutSectionBuilder;
use Drupal\layout_builder\LayoutSectionItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @todo.
 */
class LayoutBuilderJsonController implements ContainerInjectionInterface {

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
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * LayoutBuilderJsonController constructor.
   *
   * @param \Drupal\layout_builder\LayoutSectionBuilder $builder
   *   The layout section builder.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager
   *   The layout manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(LayoutSectionBuilder $builder, LayoutPluginManagerInterface $layout_manager, RendererInterface $renderer) {
    $this->builder = $builder;
    $this->layoutManager = $layout_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.builder'),
      $container->get('plugin.manager.core.layout'),
      $container->get('renderer')
    );
  }

  /**
   * Returns a JSON representation of a layout.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function build(EntityInterface $entity, Request $request) {
    $include_layout = $request->query->get('layout');
    if ($delta = $request->query->get('delta')) {
      $build = $this->buildSection($entity->layout_builder__layout->get($delta), $include_layout);
      if ($region = $request->query->get('region')) {
        if (!isset($build['section'][$region])) {
          throw new \InvalidArgumentException($region);
        }

        $build['section'] = $build['section'][$region];

        if ($block_uuid = $request->query->get('uuid')) {
          if (!isset($build['section'][$block_uuid])) {
            throw new \InvalidArgumentException($block_uuid);
          }

          $build['section'] = $build['section'][$block_uuid];
        }
      }
    }
    else {
      $build = [];
      foreach ($entity->layout_builder__layout as $delta => $item) {
        $build[$delta] = $this->buildSection($item, $include_layout);
      }
    }
    return new JsonResponse($build);
  }

  /**
   * @todo.
   *
   * @param \Drupal\layout_builder\LayoutSectionItemInterface $item
   *
   * @return array[]
   */
  protected function buildSection(LayoutSectionItemInterface $item, $include_layout = FALSE) {
    $result = $item->getValue();

    if ($include_layout) {
      $layout_definition = $this->layoutManager->getDefinition($item->layout);
      $reflection = new \ReflectionClass($layout_definition);
      $result['layout_definition']['region_names'] = $layout_definition->getRegionNames();
      foreach ($reflection->getProperties() as $property) {
        $property->setAccessible(TRUE);
        $result['layout_definition'][$property->getName()] = $property->getValue($layout_definition);
      }
    }

    $build = $this->builder->buildSection($item->layout, $item->layout_settings, $item->section);
    foreach (Element::children($build) as $region) {
      foreach ($build[$region] as $uuid => $block) {
        $result['section'][$region][$uuid]['html'] = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($block) {
          return $this->renderer->render($block);
        });
      }
    }
    return $result;
  }

}
