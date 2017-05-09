<?php

namespace Drupal\layout_builder\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @todo.
 *
 * @FieldWidget(
 *   id = "layout_section",
 *   label = @Translation("Layout Section"),
 *   field_types = {
 *     "layout_section"
 *   }
 * )
 */
class LayoutSectionWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The layout plugin manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutPluginManager;

  /**
   * LayoutSectionWidget constructor.
   *
   * @param string $plugin_id
   *   The plugin ID for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_plugin_manager
   *   The layout plugin manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, LayoutPluginManagerInterface $layout_plugin_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->layoutPluginManager = $layout_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.core.layout')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $item */
    $item = $items[$delta];

    $element['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Select a layout'),
      '#options' => $this->layoutPluginManager->getLayoutOptions(),
      '#default_value' => $item->layout,
    ];
    $element['section'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Place a serialized PHP array here'),
      '#default_value' => $item->section ? serialize($item->section) : NULL,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $key => $value) {
      if (!empty($value['section'])) {
        $values[$key]['section'] = unserialize($value['section']);
      }
      else {
        unset($values[$key]);
      }
    }
    return $values;
  }

}
