<?php

namespace Drupal\layout_builder\Field;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Defines a item list class for layout section fields.
 *
 * @see \Drupal\layout_builder\Plugin\Field\FieldType\LayoutSectionItem
 */
interface LayoutSectionItemListInterface extends FieldItemListInterface {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\layout_builder\LayoutSectionItemInterface|null
   *   The layout section item, if it exists.
   */
  public function get($index);

  /**
   * Adds a new item to the list.
   *
   * If an item exists at the given index, the item at that position and others
   * after it are shifted backward.
   *
   * @param int $index
   *   The position of the item in the list.
   * @param mixed $value
   *   The value of the item to be stored at the specified position.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The item that was appended.
   *
   * @todo Consider moving to \Drupal\Core\TypedData\ListInterface directly.
   */
  public function addItem($index, $value);

}
