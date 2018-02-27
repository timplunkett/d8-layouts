<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\PluginDefinitionRepository;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller to choose a new section.
 *
 * @internal
 */
class ChooseSectionController implements ContainerInjectionInterface {

  use AjaxHelperTrait;
  use StringTranslationTrait;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * The plugin definition repository.
   *
   * @var \Drupal\Core\Plugin\PluginDefinitionRepository
   */
  protected $definitionRepository;

  /**
   * ChooseSectionController constructor.
   *
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager
   *   The layout manager.
   * @param \Drupal\Core\Plugin\PluginDefinitionRepository $definition_repository
   *   The plugin definition repository.
   */
  public function __construct(LayoutPluginManagerInterface $layout_manager, PluginDefinitionRepository $definition_repository) {
    $this->layoutManager = $layout_manager;
    $this->definitionRepository = $definition_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.core.layout'),
      $container->get('plugin.definition_repository')
    );
  }

  /**
   * Choose a layout plugin to add as a section.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   The render array.
   */
  public function build(SectionStorageInterface $section_storage, $delta) {
    $output['#title'] = $this->t('Choose a layout');

    $items = [];
    $definitions = $this->definitionRepository->get('layout', 'layout_builder', $this->layoutManager);
    foreach ($definitions as $plugin_id => $definition) {
      $layout = $this->layoutManager->createInstance($plugin_id);
      $item = [
        '#type' => 'link',
        '#title' => [
          $definition->getIcon(60, 80, 1, 3),
          [
            '#type' => 'container',
            '#children' => $definition->getLabel(),
          ],
        ],
        '#url' => Url::fromRoute(
          $layout instanceof PluginFormInterface ? 'layout_builder.configure_section' : 'layout_builder.add_section',
          [
            'section_storage_type' => $section_storage->getStorageType(),
            'section_storage' => $section_storage->getStorageId(),
            'delta' => $delta,
            'plugin_id' => $plugin_id,
          ]
        ),
      ];
      if ($this->isAjax()) {
        $item['#attributes']['class'][] = 'use-ajax';
        $item['#attributes']['data-dialog-type'][] = 'dialog';
        $item['#attributes']['data-dialog-renderer'][] = 'off_canvas';
      }
      $items[] = $item;
    }
    $output['layouts'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => [
        'class' => [
          'layout-selection',
        ],
      ],
    ];

    return $output;
  }

}
