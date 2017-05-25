<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
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
  public static $modules = ['layout_builder', 'node', 'block_content'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic',
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
    BlockContent::create([
      'info' => 'My custom block',
      'type' => 'basic',
      'body' => [[
        'value' => 'This is the block content',
        'format' => filter_default_format(),
      ]],
    ])->save();

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
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'access toolbar',
      'configure any layout',
    ], 'foobar'));
  }

  public function test() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Ensure the block is not displayed initially.
    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Powered by Drupal');

    // Enter the layout editing mode.
    $this->clickAjaxLink('Content Layout');
    $assert_session->linkExists('Add Section');
    $assert_session->linkNotExists('Add Block');

    // Add a new section.
    $this->clickAjaxLink('Add Section');
    $this->clickAjaxLink('One column');
    $assert_session->linkExists('Add Section');
    $assert_session->linkExists('Add Block');

    // Add a new block.
    $this->clickAjaxLink('Add Block');
    $assert_session->elementExists('css', '#drupal-off-canvas');

    $this->clickAjaxLink('Powered by Drupal');
    $page->fillField('settings[label]', 'This is the label');
    $page->checkField('settings[label_display]');

    // Save the new block, and ensure it is displayed on the page.
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('Powered by Drupal');
    // @todo The label should be shown, but this is currently handled by
    //   template_preprocess_block() for block entities.
    $assert_session->pageTextNotContains('This is the label');

    // Until the layout is saved, the new block is not visible on the node page.
    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Powered by Drupal');

    // When returning to the layout edit mode, the new block is visible.
    $this->drupalGet('node/1/layout');
    $assert_session->pageTextContains('Powered by Drupal');

    // Save the layout, and the new block is visible.
    $this->clickLink('Save Layout');
    $assert_session->addressEquals('node/1');
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->pageTextContains('My Sections');

    // Remove a block.
    $this->drupalGet('node/1/layout');
    $page->clickLink('Remove block');
    $assert_session->pageTextNotContains('Powered by Drupal');
    $assert_session->linkExists('Add Block');
    $assert_session->addressEquals('node/1/layout');
    $this->clickLink('Save Layout');
    $assert_session->pageTextContains('My Sections');

    // Test deriver-based blocks.
    $this->drupalGet('node/1/layout');
    $this->clickAjaxLink('Add Block');
    $this->clickAjaxLink('My custom block');
    $page->pressButton('Add Block');
    $assert_session->pageTextContains('This is the block content');

    // Remove a section.
    $this->clickLink('Remove section');
    $assert_session->pageTextNotContains('This is the block content');
    $assert_session->linkNotExists('Add Block');
    $this->clickLink('Save Layout');
    $assert_session->pageTextNotContains('My Sections');
  }

  /**
   * @todo.
   */
  protected function clickAjaxLink($label) {
    $this->getSession()->getPage()->clickLink($label);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->htmlOutput($this->getSession()->getPage()->getContent());
  }

}
