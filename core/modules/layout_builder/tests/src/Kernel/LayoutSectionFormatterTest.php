<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests the layout section formatter.
 *
 * @group layout_builder
 */
class LayoutSectionFormatterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'layout_builder',
    'layout_discovery',
    'entity_test',
    'user',
    'system',
    'block_test',
  ];

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
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');

    $entity_type = 'entity_test';
    $bundle = $entity_type;
    $this->fieldName = 'field_my_sections';

    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $entity_type,
      'type' => 'layout_section',
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => 'My Sections',
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

    $test_user = User::create([
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
    ]);
    $test_user->save();
    $this->container->get('current_user')->setAccount($test_user);
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
    // Pass the main content to the assertions to help with debugging.
    $main_content = $this->cssSelect('main')[0]->asXML();

    // Find the given selector.
    foreach ((array) $expected_selector as $selector) {
      $element = $this->cssSelect($selector);
      $this->assertNotEmpty($element, $main_content);
    }

    // Find the given content.
    foreach ((array) $expected_content as $content) {
      $this->assertRaw($content, $main_content);
    }
  }

  /**
   * Provides test data to ::testLayoutSectionFormatter().
   */
  public function providerTestLayoutSectionFormatter() {
    $data = [];
    $data['block_with_context'] = [
      [
        [
          'layout' => 'layout_onecol',
          'section' => [
            'content' => [
              'baz' => [
                'plugin_id' => 'test_context_aware',
                'context_mapping' => [
                  'user' => '@user.current_user_context:current_user',
                ],
              ],
            ],
          ],
        ],
      ],
      [
        '.layout--onecol',
        '#test_context_aware--username',
      ],
      [
        'foobar',
        'User context found',
      ],
    ];
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
                'plugin_id' => 'test_block_instantiation',
                'display_message' => 'foo text',
              ],
            ],
            'right' => [
              'bar' => [
                'plugin_id' => 'test_block_instantiation',
                'display_message' => 'bar text',
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
