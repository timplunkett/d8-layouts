<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the layout section formatter.
 *
 * @group layout_builder
 */
class LayoutSectionFormatterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['field', 'layout_builder', 'layout_discovery', 'entity_test', 'user', 'system'];

  /**
   * The name of the layout section field.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The entity display.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $display;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['field']);
    $this->installEntitySchema('entity_test');

    $entity_type = 'entity_test';
    $bundle = $entity_type;
    $this->fieldName = Unicode::strtolower($this->randomMachineName());

    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $entity_type,
      'type' => 'layout_section',
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $this->randomMachineName(),
    ]);
    $instance->save();

    $this->display = EntityViewDisplay::create([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $this->display->setComponent($this->fieldName, [
      'type' => 'layout_section',
      'settings' => [],
    ]);
    $this->display->save();
  }

  /**
   * Tests layout_section formatter output.
   *
   * @dataProvider providerTestLayoutSectionFormatter
   */
  public function testLayoutSectionFormatter($layout, $section, $expected_selector, $expected_content) {
    $entity = EntityTest::create([]);
    $entity->{$this->fieldName}->layout = $layout;
    $entity->{$this->fieldName}->section = $section;

    // Build and render the content.
    $content = $this->display->build($entity);
    $this->render($content);

    // Find the given selector.
    $element = $this->cssSelect($expected_selector);
    $this->assertNotEmpty($element);

    // Find the given content.
    $this->assertRaw($expected_content);
  }

  /**
   * Provides test data to ::testLayoutSectionFormatter().
   */
  public function providerTestLayoutSectionFormatter() {
    $data = [];
    $data[] = [
      'layout_onecol',
      [
        'content' => [
          'this_should_be_a_UUID' => [
            'plugin_id' => 'system_powered_by_block',
          ],
        ],
      ],
      '.layout--onecol',
      'Powered by',
    ];
    return $data;
  }

}
