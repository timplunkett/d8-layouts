<?php

namespace Drupal\layout_builder\Plugin\Field\FieldFormatter;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
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
   * @var BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Constructs a LayoutSectionFormatter object.
   *
   * @param AccountInterface $account
   *   The current user.
   * @param LayoutPluginManager $layoutPluginManager
   *   The layout plugin manager.
   * @param BlockManagerInterface $blockManager
   *   THe block plugin manager.
   * @param string $plugin_id
   *   The plugin_id for the formatter.
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
  public function __construct(AccountInterface $account, LayoutPluginManager $layoutPluginManager, BlockManagerInterface $blockManager, $plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings) {
    $this->account = $account;
    $this->layoutPluginManager = $layoutPluginManager;
    $this->blockManager = $blockManager;
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($container->get('current_user'), $container->get('plugin.manager.core.layout'), $container->get('plugin.manager.block'), $plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'], $configuration['third_party_settings']);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewValue($item);
    }

    return $elements;
  }

  /**
   * Build the render array for the field item.
   *
   * @param FieldItemInterface $item
   *   The field item.
   *
   * @return array
   */
  protected function viewValue(FieldItemInterface $item) {
    /** @var \Drupal\Core\Layout\LayoutInterface $layout */
    $layout = $this->layoutPluginManager->createInstance($item->get('layout')->getValue());
    $section = $item->get('section')->getValue();
    $regions = [];
    foreach ($section as $region => $blocks) {
      foreach ($blocks as $uuid => $configuration) {
        /** @var \Drupal\Core\Block\BlockPluginInterface $block */
        $block = $this->blockManager->createInstance($configuration['plugin_id'], $configuration);
        $access = $block->access($this->account, TRUE);
        if ($access->isAllowed()) {
          $regions[$region][$uuid] = $block->build();
        }
      }
    }
    return $layout->build($regions);
  }

}
