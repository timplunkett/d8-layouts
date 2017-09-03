<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Layout Builder routes.
 */
class ChooseSectionController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * ChooseSectionController constructor.
   *
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager
   *   The layout manager.
   */
  public function __construct(LayoutPluginManagerInterface $layout_manager) {
    $this->layoutManager = $layout_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.core.layout')
    );
  }

  /**
   * Choose a layout plugin to add as a section.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   The render array.
   */
  public function build(EntityInterface $entity, $delta) {
    $output = [];
    $items = [];
    foreach ($this->layoutManager->getDefinitions() as $plugin_id => $definition) {
      $layout = $this->layoutManager->createInstance($plugin_id);
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
          '#url' => Url::fromRoute(
            $layout instanceof PluginFormInterface ? 'layout_builder.configure_section' : 'layout_builder.add_section',
            [
              'entity_type_id' => $entity->getEntityTypeId(),
              'entity' => $entity->id(),
              'delta' => $delta,
              'plugin_id' => $plugin_id,
            ]
          ),
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

}
