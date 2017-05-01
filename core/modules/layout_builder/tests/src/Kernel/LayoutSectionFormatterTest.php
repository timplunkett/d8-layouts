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
  public function testLayoutSectionFormatter($layout_data, $expected_selector, $expected_content) {
    $values = [];
    $values[$this->fieldName] = $layout_data;
    $entity = EntityTest::create($values);

    // Build and render the content.
    $content = $this->display->build($entity);
    $this->render($content);

    // Find the given selector.
    foreach ((array) $expected_selector as $selector) {
      $element = $this->cssSelect($selector);
      $this->assertNotEmpty($element);
    }

    // Find the given content.
    foreach ((array) $expected_content as $content) {
      $this->assertRaw($content);
    }
  }

  /**
   * Provides test data to ::testLayoutSectionFormatter().
   */
  public function providerTestLayoutSectionFormatter() {
    $data = [];
    $data['single_section_single_block'] = [
      [
        [
          'layout' => 'layout_onecol',
          'section' => [
            'content' => [
              'baz' => [
                'plugin_id' => 'system_powered_by_block',
              ],
            ],
          ],
        ],
      ],
      '.layout--onecol',
      'Powered by',
    ];
    $data['multiple_sections'] = [
      [
        [
          'layout' => 'layout_onecol',
          'section' => [
            'content' => [
              'baz' => [
                'plugin_id' => 'system_powered_by_block',
              ],
            ],
          ],
        ],
        [
          'layout' => 'layout_twocol',
          'section' => [
            'left' => [
              'foo' => [
                'plugin_id' => 'test_content',
                'text' => 'foo text',
              ],
            ],
            'right' => [
              'bar' => [
                'plugin_id' => 'test_content',
                'text' => 'bar text',
              ],
            ],
          ],
        ],
      ],
      [
        '.layout--onecol',
        '.layout--twocol',
      ],
      [
        'Powered by',
        'foo text',
        'bar text',
      ],
    ];
    return $data;
  }

}
