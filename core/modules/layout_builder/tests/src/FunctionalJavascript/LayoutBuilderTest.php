<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * @todo.
 *
 * @group layout_builder
 */
class LayoutBuilderTest extends JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['layout_builder', 'node', 'block_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->createContentType(['type' => 'bundle_with_section_field']);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_my_sections',
      'entity_type' => 'node',
      'type' => 'layout_section',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->setTranslatable(TRUE);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'bundle_with_section_field',
      'label' => 'My Sections',
    ]);
    $instance->save();

    $display = EntityViewDisplay::load('node.bundle_with_section_field.default');
    $display->setComponent('field_my_sections', [
      'type' => 'layout_section',
      'settings' => [],
    ]);
    $display->save();

    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
      'field_my_sections' => [
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
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'access toolbar',
      'configure any layout',
    ], 'foobar'));
  }

  public function test() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->container->get('state')->set('test_block_access', TRUE);

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Powered by');

    $page->clickLink('Content Layout');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkExists('Add Section');
    $assert_session->linkExists('Add Block');

    $this->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('css', '#drupal-off-canvas');
    $assert_session->elementExists('css', '#drupal-off-canvas');

    $this->clickLink('Display message');
    $assert_session->assertWaitOnAjaxRequest();
    $page->fillField('settings[label]', 'This is the label');
    $page->fillField('settings[display_message]', 'This is the message');
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('This is the message');
  }

}
