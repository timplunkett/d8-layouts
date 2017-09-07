<?php

namespace Drupal\layout_builder\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\layout_builder\LayoutSectionItemInterface;

/**
 * Plugin implementation of the 'layout_section' field type.
 *
 * @FieldType(
 *   id = "layout_section",
 *   label = @Translation("Layout Section"),
 *   description = @Translation("Layout Section"),
 *   default_formatter = "layout_section",
 *   list_class = "\Drupal\layout_builder\Field\LayoutSectionItemList",
 *   no_ui = TRUE,
 *   cardinality = \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
 * )
 */
class LayoutSectionItem extends FieldItemBase implements LayoutSectionItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['layout'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Layout'))
      ->setSetting('case_sensitive', FALSE)
      ->setRequired(TRUE);
    $properties['layout_settings'] = MapDataDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Layout Settings'))
      ->setRequired(FALSE);
    $properties['section'] = MapDataDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Layout Section'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    // @todo parent::__get() does not return default values unless
    //   $this->properties has been initialized.
    $this->getProperties();

    return parent::__get($name);
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'section';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'layout' => [
          'type' => 'varchar',
          'length' => '255',
          'binary' => FALSE,
        ],
        'layout_settings' => [
          'type' => 'blob',
          'size' => 'normal',
          'serialize' => TRUE,
        ],
        'section' => [
          'type' => 'blob',
          'size' => 'normal',
          'serialize' => TRUE,
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['layout'] = 'layout_onecol';
    $values['layout_settings'] = [];
    $values['section'] = [];
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->layout);
  }

}
