<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\outside_in\FunctionalJavascript\OutsideInJavascriptTestBase;

/**
 * Tests the Layout Builder UI.
 *
 * @todo Extending OutsideInJavascriptTestBase for now get OffCanvas related
 *   asserts. Move these asserts to a trait.
 *
 * @group layout_builder
 */
class LayoutBuilderTest extends OutsideInJavascriptTestBase {

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
    $page->checkField('layout[custom]');
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

    $this->clickLink('One column');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->linkExists('Add Section');
    $assert_session->linkExists('Add Block');

    // Add a new block.
    $this->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->elementExists('css', '#drupal-off-canvas');

    $this->clickLink('Powered by Drupal');
    $assert_session->assertWaitOnAjaxRequest();

    $page->fillField('settings[label]', 'This is the label');
    $page->checkField('settings[label_display]');

    // Save the new block, and ensure it is displayed on the page.
    $page->pressButton('Add Block');
    $assert_session->assertWaitOnAjaxRequest();

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
    // @todo Assert that the layout is displayed?
    // $assert_session->pageTextContains('My Sections');

    // Drag one block from one region to another.
    $this->drupalGet('node/1/layout');
    $this->clickLink('Add Section');
    $assert_session->assertWaitOnAjaxRequest();

    $this->clickLink('Two column');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->elementNotExists('css', '.layout__region--second .block-system-powered-by-block');
    // @todo Find out why the rewritten draggable code doesn't work with tests.
    // $page->find('css', '.layout__region--content .block-system-powered-by-block')->dragTo($page->find('css', '.layout__region--second'));
    // $assert_session->assertWaitOnAjaxRequest();
    // $assert_session->elementExists('css', '.layout__region--second .block-system-powered-by-block');
    $this->clickLink('Save Layout');
    // @todo Dragging blocks does not persist, once it does switch from content to second.
    $assert_session->elementTextContains('css', '.layout__region--content', 'Powered by Drupal');

    // Remove a block.
    $this->drupalGet('node/1/layout');

    $this->toggleContextualTriggerVisibility('.block-system-powered-by-block');
    $page->find('css', '.block-system-powered-by-block .contextual .trigger')->click();
    $this->clickLink('Remove block');
    $assert_session->assertWaitOnAjaxRequest();

    $page->pressButton('Remove');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->pageTextNotContains('Powered by Drupal');
    $assert_session->linkExists('Add Block');
    $assert_session->addressEquals('node/1/layout');
    $this->clickLink('Save Layout');
    // @todo Assert that the layout is displayed?
    // $assert_session->pageTextContains('My Sections');

    // Test deriver-based blocks.
    $this->drupalGet('node/1/layout');
    $this->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();

    $this->clickLink('My custom block');
    $assert_session->assertWaitOnAjaxRequest();

    $page->pressButton('Add Block');
    $this->waitForOffCanvasToClose();
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
    // @todo Assert that a layout with no sections has no markup.
    // $assert_session->pageTextNotContains('My Sections');
  }

  /**
   * Toggles the visibility of a contextual link trigger.
   */
  protected function toggleContextualTriggerVisibility($selector) {
    // Hovering over the element itself with should be enough, but does not
    // work. Manually remove the visually-hidden class.
    // @see https://www.drupal.org/node/2821724
    $this->getSession()->executeScript("jQuery('{$selector} .contextual .trigger').toggleClass('visually-hidden');");
  }

}
