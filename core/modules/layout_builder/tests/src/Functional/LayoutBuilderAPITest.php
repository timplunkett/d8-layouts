<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Layout Builder API.
 *
 * @group layout_builder
 */
class LayoutBuilderAPITest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'layout_builder',
    'block',
    'node',
    'layout_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a content type.
    $this->createContentType(['type' => 'bundle_with_section_field']);
  }

  /**
   * {@inheritdoc}
   */
  public function testLayoutBuilderChooseBlocksAlter() {
    // In this test, hook_plugin_definition_PLUGIN_TYPE_alter() will have been
    // implemented by the layout_test module, instructing the "Help" blocks not
    // to appear, as well as the "Sticky at top of lists" field_block.
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]));

    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';

    // From the manage display page, go to manage the layout.
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display-layout/default");

    // Add a new block.
    $assert_session->linkExists('Add Block');
    $this->clickLink('Add Block');
    // Verify that blocks not modified by
    // hook_plugin_definition_PLUGIN_TYPE_alter() are present.
    // 1. A core block.
    $assert_session->linkExists('Powered by Drupal');
    // 2. A field_block.
    $assert_session->linkExists('Default revision');

    // Verify that blocks explicitly removed by
    // hook_plugin_definition_PLUGIN_TYPE_alter() are not present.
    // 1. A core block.
    $assert_session->linkNotExists('Help');
    // 2. A field_block.
    $assert_session->linkNotExists('Sticky at top of lists');
  }

}
