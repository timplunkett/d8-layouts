<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Render\Element;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
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
    'language',
    'file',
    'locale',
    'config_translation',
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
   * Languages to enable.
   *
   * @var array
   */
  protected $langcodes = [
    'fr',
    'es',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['field', 'config_translation']);
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
    $field_storage->setTranslatable(TRUE);
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

    // Add languages.
    foreach ($this->langcodes as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
  }

  /**
   * Tests layout_section formatter output.
   *
   * @dataProvider providerTestLayoutSectionFormatter
   */
  public function testLayoutSectionFormatter($layout_data, $expected_selector, $expected_content, $expected_cache) {
    $values = [];
    $values[$this->fieldName] = $layout_data;
    $entity = EntityTest::create($values);

    $this->assertRenderedEntity($entity, $expected_selector, $expected_content, $expected_cache);
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
      [['contexts' => ['user'], 'tags' => ['user:1'], 'max-age' => -1]],
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
      [],
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
      [
        [],
        ['contexts' => ['user.permissions'], 'tags' => [], 'max-age' => -1],
      ],
    ];
    return $data;
  }

  /**
   * Tests layout_section multilingual formatter output.
   */
  public function testMultilingualLayoutSectionFormatter() {
    $fr_values = [];
    $fr_values[$this->fieldName] = [
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
    ];
    $es_values = [];
    $es_values[$this->fieldName] = [
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
    ];
    $entity = EntityTest::create($fr_values);
    $entity->addTranslation('es', $es_values);

    $this->assertRenderedEntity($entity, '.layout--onecol', 'Powered by');

    // Build and render the es content.
    $entity = $entity->getTranslation('es');
    $expected_cacheable_metadata = [
      [
        'contexts' => ['user.permissions'],
        'tags' => [],
        'max-age' => -1,
      ],
      [
        'contexts' => ['user.permissions'],
        'tags' => [],
        'max-age' => -1,
      ],
    ];
    $this->assertRenderedEntity($entity, '.layout--twocol', ['foo text', 'bar text'], $expected_cacheable_metadata);
  }

  public function testLayoutSectionFormatterAccess() {
    $values = [];
    $values[$this->fieldName] = [
      [
        'layout' => 'layout_onecol',
        'section' => [
          'content' => [
            'baz' => [
              'plugin_id' => 'test_access',
            ],
          ],
        ],
      ],
    ];
    $expected_cacheable_metadata = [[
      'contexts' => [],
      'tags' => [],
      'max-age' => 0,
    ]];

    $entity = EntityTest::create($values);

    // Restrict access to the block.
    $this->container->get('state')->set('test_block_access', FALSE);
    $this->assertRenderedEntity($entity, '.layout--onecol', NULL, $expected_cacheable_metadata);
    // Ensure the block was not rendered.
    $this->assertNoRaw('Hello test world');

    // Grant access to the block, and ensure it was rendered.
    $this->container->get('state')->set('test_block_access', TRUE);
    $this->assertRenderedEntity($entity, '.layout--onecol', 'Hello test world', $expected_cacheable_metadata);
  }

  /**
   * Asserts the output of a rendered entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to render.
   * @param string|array $expected_selector
   *   A selector or list of CSS selectors to find.
   * @param string|array $expected_content
   *   A string or list of strings to find.
   * @param array|null $expected_cacheable_metadata
   *   (optional) An array of cacheable metadata keyed by field delta.
   */
  protected function assertRenderedEntity(FieldableEntityInterface $entity, $expected_selector, $expected_content, $expected_cacheable_metadata = []) {
    // Build and render the content.
    $content = $this->display->build($entity);
    foreach (Element::children($content[$this->fieldName]) as $key) {
      // If no cacheable metadata is expected, use the default values.
      if (empty($expected_cacheable_metadata[$key])) {
        $expected_cacheable_metadata[$key] = [
          'contexts' => [],
          'tags' => [],
          'max-age' => -1,
        ];
      }
      $this->assertEquals($expected_cacheable_metadata[$key], $content[$this->fieldName][$key]['#cache']);
    }
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

}
