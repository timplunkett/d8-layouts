<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\layout_builder\LayoutSectionBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

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
  public function build(EntityInterface $entity) {
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemListInterface $field_list */
    $field_list = $entity->layout_builder__layout;
    $result = $field_list->getValue();
    $cacheability = new CacheableMetadata();

    foreach ($field_list as $delta => $item) {
      /** @var \Drupal\layout_builder\LayoutSectionItemInterface $item */
      $layout_definition = $this->layoutManager->getDefinition($item->layout);
      $result[$delta]['layout_definition'] = [
        'label' => $layout_definition->getLabel(),
        'category' => $layout_definition->getCategory(),
        'description' => $layout_definition->getDescription(),
        'path' => $layout_definition->getPath(),
        'template_path' => $layout_definition->getTemplatePath(),
        'template' => $layout_definition->getTemplate(),
        'theme_hook' => $layout_definition->getThemeHook(),
        'icon_path' => $layout_definition->getIconPath(),
        'library' => $layout_definition->getLibrary(),
        'default_region' => $layout_definition->getDefaultRegion(),
        'regions' => $layout_definition->getRegions(),
      ];

      $build = $this->builder->buildSection($item->layout, $item->layout_settings, $item->section);
      foreach (Element::children($build) as $region) {
        foreach ($build[$region] as $uuid => $block) {
          $context = new RenderContext();
          $html = $this->renderer->executeInRenderContext($context, function () use ($block) {
            return $this->renderer->render($block);
          });
          if (!$context->isEmpty()) {
            $cacheability->merge($context->pop());
          }
          $result[$delta]['section'][$region][$uuid]['html'] = $html;
        }
      }
    }
    $cacheability->applyTo($result);
    return new JsonResponse($result);
  }

}
