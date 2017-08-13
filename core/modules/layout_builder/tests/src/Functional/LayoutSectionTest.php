<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the rendering of a layout section field.
 *
 * @group layout_builder
 */
class LayoutSectionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['layout_builder', 'field_ui', 'node', 'block_test'];

  /**
   * The name of the layout section field.
   *
   * @var string
   */
  protected $fieldName = 'layout_builder__layout';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->createContentType([
      'type' => 'bundle_with_section_field',
    ]);
    $this->createContentType([
      'type' => 'bundle_without_section_field',
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
    ], 'foobar'));
    $page = $this->getSession()->getPage();
    $this->drupalGet('admin/structure/types/manage/bundle_with_section_field/display');
    $page->checkField('layout[custom]');
    $page->pressButton('Save');
  }

  /**
   * Provides test data for ::testLayoutSectionFormatter().
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
                'id' => 'test_context_aware',
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
      'user',
      'user:2',
      'UNCACHEABLE',
    ];
    $data['single_section_single_block'] = [
      [
        [
          'layout' => 'layout_onecol',
          'section' => [
            'content' => [
              'baz' => [
                'id' => 'system_powered_by_block',
              ],
            ],
          ],
        ],
      ],
      '.layout--onecol',
      'Powered by',
      '',
      '',
      'MISS',
    ];
    $data['multiple_sections'] = [
      [
        [
          'layout' => 'layout_onecol',
          'section' => [
            'content' => [
              'baz' => [
                'id' => 'system_powered_by_block',
              ],
            ],
          ],
        ],
        [
          'layout' => 'layout_twocol',
          'section' => [
            'first' => [
              'foo' => [
                'id' => 'test_block_instantiation',
                'display_message' => 'foo text',
              ],
            ],
            'second' => [
              'bar' => [
                'id' => 'test_block_instantiation',
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
      'user.permissions',
      '',
      'MISS',
    ];
    return $data;
  }

  /**
   * Tests layout_section formatter output.
   *
   * @dataProvider providerTestLayoutSectionFormatter
   */
  public function testLayoutSectionFormatter($layout_data, $expected_selector, $expected_content, $expected_cache_contexts, $expected_cache_tags, $expected_dynamic_cache) {
    $this->createSectionNode($layout_data);

    $this->drupalGet('node/1');
    $this->assertLayoutSection($expected_selector, $expected_content, $expected_cache_contexts, $expected_cache_tags, $expected_dynamic_cache);

    $this->drupalGet('node/1/layout');
    $this->assertLayoutSection($expected_selector, $expected_content, $expected_cache_contexts, $expected_cache_tags, 'UNCACHEABLE');
  }

  /**
   * Tests the access checking of the section formatter.
   */
  public function testLayoutSectionFormatterAccess() {
    $this->createSectionNode([
      [
        'layout' => 'layout_onecol',
        'section' => [
          'content' => [
            'baz' => [
              'id' => 'test_access',
            ],
          ],
        ],
      ],
    ]);

    // Restrict access to the block.
    $this->container->get('state')->set('test_block_access', FALSE);

    $this->drupalGet('node/1');
    $this->assertLayoutSection('.layout--onecol', NULL, '', '', 'UNCACHEABLE');
    // Ensure the block was not rendered.
    $this->assertSession()->pageTextNotContains('Hello test world');

    // Grant access to the block, and ensure it was rendered.
    $this->container->get('state')->set('test_block_access', TRUE);
    $this->drupalGet('node/1');
    $this->assertLayoutSection('.layout--onecol', 'Hello test world', '', '', 'UNCACHEABLE');
  }

  /**
   * Tests the multilingual support of the section formatter.
   */
  public function testMultilingualLayoutSectionFormatter() {
    $this->container->get('module_installer')->install(['content_translation']);
    $this->rebuildContainer();

    ConfigurableLanguage::createFromLangcode('es')->save();
    $this->container->get('content_translation.manager')->setEnabled('node', 'bundle_with_section_field', TRUE);

    $entity = $this->createSectionNode([
      [
        'layout' => 'layout_onecol',
        'section' => [
          'content' => [
            'baz' => [
              'id' => 'system_powered_by_block',
            ],
          ],
        ],
      ],
    ]);
    $entity->addTranslation('es', [
      'title' => 'Translated node title',
      $this->fieldName => [
        [
          'layout' => 'layout_twocol',
          'section' => [
            'first' => [
              'foo' => [
                'id' => 'test_block_instantiation',
                'display_message' => 'foo text',
              ],
            ],
            'second' => [
              'bar' => [
                'id' => 'test_block_instantiation',
                'display_message' => 'bar text',
              ],
            ],
          ],
        ],
      ],
    ]);
    $entity->save();

    $this->drupalGet('node/1');
    $this->assertLayoutSection('.layout--onecol', 'Powered by');
    $this->drupalGet('es/node/1');
    $this->assertLayoutSection('.layout--twocol', ['foo text', 'bar text']);
  }

  /**
   * Ensures that the entity title is displayed.
   */
  public function testLayoutPageTitle() {
    $this->drupalPlaceBlock('page_title_block');
    $this->createSectionNode([]);

    $this->drupalGet('node/1/layout');
    $this->assertSession()->titleEquals('Edit layout for The node title | Drupal');
    $this->assertEquals('Edit layout for The node title', $this->cssSelect('h1.page-title')[0]->getText());
  }

  /**
   * Tests that no Layout link shows without a section field.
   */
  public function testLayoutUrlNoSectionField() {
    $this->createNode([
      'type' => 'bundle_without_section_field',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
    ]);
    $this->drupalGet('node/1/layout');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Asserts the output of a layout section.
   *
   * @param string|array $expected_selector
   *   A selector or list of CSS selectors to find.
   * @param string|array $expected_content
   *   A string or list of strings to find.
   * @param string $expected_cache_contexts
   *   A string of cache contexts to be found in the header.
   * @param string $expected_cache_tags
   *   A string of cache tags to be found in the header.
   * @param string $expected_dynamic_cache
   *   The expected dynamic cache header. Either 'HIT', 'MISS' or 'UNCACHEABLE'.
   */
  protected function assertLayoutSection($expected_selector, $expected_content, $expected_cache_contexts = '', $expected_cache_tags = '', $expected_dynamic_cache = 'MISS') {
    $assert_session = $this->assertSession();
    // Find the given selector.
    foreach ((array) $expected_selector as $selector) {
      $element = $this->cssSelect($selector);
      $this->assertNotEmpty($element);
    }

    // Find the given content.
    foreach ((array) $expected_content as $content) {
      $assert_session->pageTextContains($content);
    }
    if ($expected_cache_contexts) {
      $assert_session->responseHeaderContains('X-Drupal-Cache-Contexts', $expected_cache_contexts);
    }
    if ($expected_cache_tags) {
      $assert_session->responseHeaderContains('X-Drupal-Cache-Tags', $expected_cache_tags);
    }
    $assert_session->responseHeaderEquals('X-Drupal-Dynamic-Cache', $expected_dynamic_cache);
  }

  /**
   * Creates a node with a section field.
   *
   * @param array $section_values
   *   An array of values for a section field.
   *
   * @return \Drupal\node\NodeInterface
   *   The node object.
   */
  protected function createSectionNode(array $section_values) {
    return $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
      $this->fieldName => $section_values,
    ]);
  }

}
