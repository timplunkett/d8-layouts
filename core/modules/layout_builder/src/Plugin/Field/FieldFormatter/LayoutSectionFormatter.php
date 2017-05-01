<?php

namespace Drupal\layout_builder\Plugin\Field\FieldFormatter;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\LayoutSectionItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'layout_section' formatter.
 *
 * @FieldFormatter(
 *   id = "layout_section",
 *   label = @Translation("Layout Section"),
 *   field_types = {
 *     "layout_section"
 *   }
 * )
 */
class LayoutSectionFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The layout plugin manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManager
   */
  protected $layoutPluginManager;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The context manager service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Constructs a LayoutSectionFormatter object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layoutPluginManager
   *   The layout plugin manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   THe block plugin manager.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The ContextHandler for applying contexts to conditions properly.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The lazy context repository service.
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   */
  public function __construct(AccountInterface $account, LayoutPluginManagerInterface $layoutPluginManager, BlockManagerInterface $blockManager, ContextHandlerInterface $context_handler, ContextRepositoryInterface $context_repository, $plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings) {
    $this->account = $account;
    $this->layoutPluginManager = $layoutPluginManager;
    $this->blockManager = $blockManager;
    $this->contextHandler = $context_handler;
    $this->contextRepository = $context_repository;
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('current_user'),
      $container->get('plugin.manager.core.layout'),
      $container->get('plugin.manager.block'),
      $container->get('context.handler'),
      $container->get('context.repository'),
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->buildSection($item);
    }

    return $elements;
  }

  /**
   * Builds the render array for the layout section.
   *
   * @param \Drupal\layout_builder\LayoutSectionItemInterface $item
   *   The layout section item.
   *
   * @return array
   *   A render array for the field item.
   */
  protected function buildSection(LayoutSectionItemInterface $item) {
    /** @var \Drupal\Core\Layout\LayoutInterface $layout */
    $layout = $this->layoutPluginManager->createInstance($item->layout);
    $regions = [];
    foreach ($item->section as $region => $blocks) {
      foreach ($blocks as $uuid => $configuration) {
        /** @var \Drupal\Core\Block\BlockPluginInterface $block */
        $block = $this->blockManager->createInstance($configuration['plugin_id'], $configuration);
        if ($block instanceof ContextAwarePluginInterface) {
          $contexts = $this->contextRepository->getRuntimeContexts(array_values($block->getContextMapping()));
          $this->contextHandler->applyContextMapping($block, $contexts);
        }
        $access = $block->access($this->account, TRUE);
        if ($access->isAllowed()) {
          $regions[$region][$uuid] = $block->build();
        }
      }
    }
    return $layout->build($regions);
  }

}
