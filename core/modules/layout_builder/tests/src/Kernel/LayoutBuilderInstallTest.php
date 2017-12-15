<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\layout_builder\Section;

/**
 * Ensures that Layout Builder and core EntityViewDisplays are compatible.
 *
 * @group layout_builder
 */
class LayoutBuilderInstallTest extends LayoutBuilderCompatibilityTestBase {

  /**
   * Tests the compatibility of Layout Builder with existing entity displays.
   */
  public function testCompatibility() {
    // Ensure that the fields are shown.
    $original_markup = $this->renderEntity($this->entity);
    $this->assertNotEmpty($this->cssSelect('.field--name-test-display-configurable'));
    $this->assertNotEmpty($this->cssSelect('.field--name-test-field-display-configurable'));
    $this->assertNotEmpty($this->cssSelect('.field--name-test-display-non-configurable'));

    $this->installLayoutBuilder();

    // Without using Layout Builder for an override, the result has not changed.
    $new_markup = $this->renderEntity($this->entity);
    $this->assertSame($original_markup, $new_markup);

    // Add a layout override.
    $this->entity->get('layout_builder__layout')->appendSection(new Section('layout_onecol'));
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
    $this->entity->get('layout_builder__layout')->removeSection(0);
    $this->entity->save();
    $new_markup = $this->renderEntity($this->entity);
    $this->assertSame($original_markup, $new_markup);
  }

}
