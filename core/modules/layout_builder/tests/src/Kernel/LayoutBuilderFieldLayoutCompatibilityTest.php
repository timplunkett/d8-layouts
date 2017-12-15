<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\layout_builder\Section;

/**
 * Ensures that Layout Builder and Field Layout are compatible with each other.
 *
 * @group layout_builder
 */
class LayoutBuilderFieldLayoutCompatibilityTest extends LayoutBuilderCompatibilityTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field_layout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->display
      ->setLayoutId('layout_twocol')
      ->save();
  }

  /**
   * Tests the compatibility of Layout Builder and Field Layout.
   */
  public function testCompatibility() {
    // Ensure that the configurable field is shown in the correct region and
    // that the non-configurable field is shown outside the layout.
    $original_markup = $this->renderEntity($this->entity);
    $this->assertNotEmpty($this->cssSelect('.layout__region--first .field--name-test-display-configurable'));
    $this->assertNotEmpty($this->cssSelect('.layout__region--first .field--name-test-field-display-configurable'));
    $this->assertNotEmpty($this->cssSelect('.field--name-test-display-non-configurable'));
    $this->assertEmpty($this->cssSelect('.layout__region .field--name-test-display-non-configurable'));

    $this->installLayoutBuilder();

    // Without using Layout Builder for an override, the result has not changed.
    $new_markup = $this->renderEntity($this->entity);
    $this->assertSame($original_markup, $new_markup);

    // Add a layout override.
    /** @var \Drupal\layout_builder\SectionStorageInterface $field_list */
    $field_list = $this->entity->layout_builder__layout;
    $field_list->appendSection(new Section('layout_onecol'));
    $this->entity->save();

    // The rendered entity has now changed. The non-configurable field is shown
    // outside the layout, the configurable field is not shown at all, and the
    // layout itself is rendered (but empty).
    $new_markup = $this->renderEntity($this->entity);
    $this->assertNotSame($original_markup, $new_markup);
    $this->assertEmpty($this->cssSelect('.field--name-test-display-configurable'));
    $this->assertEmpty($this->cssSelect('.field--name-test-field-display-configurable'));
    $this->assertNotEmpty($this->cssSelect('.field--name-test-display-non-configurable'));
    $this->assertNotEmpty($this->cssSelect('.layout--onecol'));

    // Removing the layout restores the original rendering of the entity.
    $field_list->removeSection(0);
    $this->entity->save();
    $new_markup = $this->renderEntity($this->entity);
    $this->assertSame($original_markup, $new_markup);
  }

}
