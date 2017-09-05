<?php

namespace Drupal\Core\Layout;

/**
 * Provides an interface for generating layout icons from well-formed config.
 */
interface IconGeneratorInterface {

  /**
   * Generates a SVG based on a Layout's icon map.
   *
   * @param array $icon_map
   *   A two dimensional array representing the visual output of the layout.
   *   For the following shape:
   *   |------------------------------|
   *   |                              |
   *   |             100%             |
   *   |                              |
   *   |-------|--------------|-------|
   *   |       |              |       |
   *   |  25%  |      50%     |  25%  |
   *   |       |              |       |
   *   |-------|--------------|-------|
   *   |                              |
   *   |             100%             |
   *   |                              |
   *   |------------------------------|
   *   The corresponding array would be:
   *   - [top]
   *   - [first, second, second, third]
   *   - [bottom].
   * @param int $width
   *   (optional) The width of the generated SVG. Defaults to 250.
   * @param int $height
   *   (optional) The height of the generated SVG. Defaults to 300.
   * @param int $stroke_width
   *   (optional) The width of region borders. Defaults to 2.
   * @param int $padding
   *   (optional) The padding between regions. Any value above 0 is valid.
   *   Defaults to 5.
   * @param string $fill
   *   (optional) The fill color of regions. Defaults to 'lightgray'.
   * @param string $stroke
   *   (optional) The color of region borders. Defaults to 'black'.
   *
   * @return array
   *   A render array representing a SVG icon.
   */
  public function generateSvgFromIconMap(array $icon_map, $width = 250, $height = 300, $stroke_width = 2, $padding = 5, $fill = 'lightgray', $stroke = 'black');

}
