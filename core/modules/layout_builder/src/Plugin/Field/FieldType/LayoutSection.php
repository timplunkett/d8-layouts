<?php

namespace Drupal\layout_builder\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Plugin implementation of the 'layout_section' field type.
 *
 * @FieldType(
 *   id = "layout_section",
 *   label = @Translation("Layout Section"),
 *   description = @Translation("Layout Section"),
 *   default_formatter = "layout_section"
 * )
 */
class LayoutSection extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['layout'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Layout'))
      ->setSetting('case_sensitive', FALSE)
      ->setRequired(TRUE);
    $properties[static::mainPropertyName()] = MapDataDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Layout Section'))
      ->setRequired(FALSE);

    return $properties;
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
        static::mainPropertyName() => [
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
    $values[static::mainPropertyName()] = [];
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $sections = $this->get(static::mainPropertyName())->getValue();
    return empty($sections);
  }

}
