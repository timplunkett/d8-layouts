<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Tests the Layout Builder UI.
 *
 * @group layout_builder
 */
class LayoutBuilderTest extends JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['layout_builder', 'node', 'block_content', 'contextual', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');

    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic',
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
    BlockContent::create([
      'info' => 'My custom block',
      'type' => 'basic',
      'body' => [
        [
          'value' => 'This is the block content',
          'format' => filter_default_format(),
        ],
      ],
    ])->save();

    $this->createContentType(['type' => 'bundle_with_section_field']);
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
      'access contextual links',
      'configure any layout',
      'administer node display',
    ], 'foobar'));
  }

  /**
   * Tests the Layout Builder UI.
   *
   * @todo:
   *   Add tests for revision support.
   */
  public function test() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Enable layout support.
    $this->drupalGet('admin/structure/types/manage/bundle_with_section_field/display');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');

    // Ensure the block is not displayed initially.
    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Powered by Drupal');

    // Enter the layout editing mode.
    $this->clickLink('Layout');
    $assert_session->linkExists('Add Section');
    $assert_session->linkNotExists('Add Block');

    // Add a new section.
    $this->clickLink('Add Section');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', '#drupal-off-canvas');

    $this->clickLink('One column');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', '#drupal-off-canvas');

    $assert_session->linkExists('Add Section');
    $assert_session->linkExists('Add Block');

    // Add a new block.
    $this->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->elementExists('css', '#drupal-off-canvas');

    $this->clickLink('Powered by Drupal');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', '#drupal-off-canvas');

    $page->fillField('settings[label]', 'This is the label');
    $page->checkField('settings[label_display]');

    // Save the new block, and ensure it is displayed on the page.
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', '#drupal-off-canvas');

    $assert_session->addressEquals('node/1/layout');
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->pageTextContains('This is the label');

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
    $assert_session->pageTextContains('This is the label');
    $assert_session->elementExists('css', '.layout');

    // Drag one block from one region to another.
    $this->drupalGet('node/1/layout');
    $this->clickLink('Add Section');
    $assert_session->assertWaitOnAjaxRequest();

    $this->clickLink('Two column');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->elementNotExists('css', '.layout__region--second .block-system-powered-by-block');
    $assert_session->elementTextNotContains('css', '.layout__region--second', 'Powered by Drupal');
    // Drag the block from one layout to another.
    $page->find('css', '.layout__region--content .block-system-powered-by-block')->dragTo($page->find('css', '.layout__region--second'));
    $assert_session->assertWaitOnAjaxRequest();
    // Ensure the drag succeeded.
    $assert_session->elementExists('css', '.layout__region--second .block-system-powered-by-block');
    $assert_session->elementTextContains('css', '.layout__region--second', 'Powered by Drupal');
    // Ensure the drag persisted after reload.
    $this->drupalGet('node/1/layout');
    $assert_session->elementExists('css', '.layout__region--second .block-system-powered-by-block');
    $assert_session->elementTextContains('css', '.layout__region--second', 'Powered by Drupal');
    // Ensure the drag persisted after save.
    $this->clickLink('Save Layout');
    $assert_session->elementExists('css', '.layout__region--second .block-system-powered-by-block');
    $assert_session->elementTextContains('css', '.layout__region--second', 'Powered by Drupal');

    // Configure a block.
    $this->drupalGet('node/1/layout');
    $assert_session->assertWaitOnAjaxRequest();
    $this->toggleContextualTriggerVisibility('.block-system-powered-by-block');
    $assert_session->assertWaitOnAjaxRequest();
    $page->find('css', '.block-system-powered-by-block .contextual .trigger')->click();
    $assert_session->assertWaitOnAjaxRequest();

    $this->clickLink('Configure');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', '#drupal-off-canvas');

    $page->fillField('settings[label]', 'This is the new label');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', '#drupal-off-canvas');

    $assert_session->addressEquals('node/1/layout');
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->pageTextContains('This is the new label');
    $assert_session->pageTextNotContains('This is the label');

    // Remove a block.
    $this->drupalGet('node/1/layout');

    $this->toggleContextualTriggerVisibility('.block-system-powered-by-block');
    $page->find('css', '.block-system-powered-by-block .contextual .trigger')->click();
    $this->clickLink('Remove block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', '#drupal-off-canvas');

    $page->pressButton('Remove');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', '#drupal-off-canvas');

    $assert_session->pageTextNotContains('Powered by Drupal');
    $assert_session->linkExists('Add Block');
    $assert_session->addressEquals('node/1/layout');

    $this->clickLink('Save Layout');
    $assert_session->elementExists('css', '.layout');

    // Test deriver-based blocks.
    $this->drupalGet('node/1/layout');
    $this->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();

    $this->clickLink('My custom block');
    $assert_session->assertWaitOnAjaxRequest();

    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('This is the block content');

    // Remove both sections.
    $this->clickLink('Remove section');
    $assert_session->assertWaitOnAjaxRequest();

    $page->pressButton('Remove');
    $assert_session->assertWaitOnAjaxRequest();

    $this->clickLink('Remove section');
    $assert_session->assertWaitOnAjaxRequest();

    $page->pressButton('Remove');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->pageTextNotContains('This is the block content');
    $assert_session->linkNotExists('Add Block');
    $this->clickLink('Save Layout');
    $assert_session->elementNotExists('css', '.layout');
  }

  /**
   * Toggles the visibility of a contextual trigger.
   *
   * @todo Remove this function when related trait added in
   *   https://www.drupal.org/node/2821724.
   *
   * @param string $selector
   *   The selector for the element that contains the contextual link.
   */
  protected function toggleContextualTriggerVisibility($selector) {
    // Hovering over the element itself with should be enough, but does not
    // work. Manually remove the visually-hidden class.
    $this->getSession()->executeScript("jQuery('{$selector} .contextual .trigger').toggleClass('visually-hidden');");
  }

}
