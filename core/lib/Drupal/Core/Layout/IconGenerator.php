<?php

namespace Drupal\Core\Layout;

/**
 * Generates layout icons from well-formed config.
 */
class IconGenerator implements IconGeneratorInterface {

  /**
   * {@inheritdoc}
   */
  public function generateSvgFromIconMap(array $icon_map, $width = 250, $height = 300, $stroke_width = 2, $padding = 5, $fill = 'lightgray', $stroke = 'black') {
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'svg',
      '#attributes' => [
        'width' => $width,
        'height' => $height,
      ],
    ];

    $region_rects = [];
    $num_rows = count($icon_map);
    foreach ($icon_map as $row => $cols) {
      $num_cols = count($cols);
      foreach ($cols as $col => $region) {
        if (!isset($region_rects[$region])) {
          // The first instance of a region is always the starting point.
          $x = $col * ($width / $num_cols);
          $y = ($row / $num_rows) * $height;
          $region_rects[$region] = [
            'x' => $x,
            'y' => $y,
            'width' => ($width / $num_cols) - $padding,
            'height' => ($height / $num_rows) - $padding,
            'last_col' => $col,
            'last_row' => $row,
          ];
        }
        else {
          // Only increase the width/height if we've moved in that direction.
          if ($region_rects[$region]['last_col'] != $col) {
            $region_rects[$region]['width'] += ($width / $num_cols);
            $region_rects[$region]['last_col'] = $col;
          }
          if ($region_rects[$region]['last_row'] != $row) {
            $region_rects[$region]['height'] += ($height / $num_rows);
            $region_rects[$region]['last_row'] = $row;
          }
        }
      }
    }

    // Append each polygon to the SVG.
    foreach ($region_rects as $region => $attributes) {
      // Group our regions allows for metadata, nested elements, and tooltips.
      $build[$region] = [
        '#type' => 'html_tag',
        '#tag' => 'g',
      ];

      $build[$region]['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'title',
        '#value' => $region,
      ];

      // Assemble the rectangle SVG element.
      $build[$region]['rect'] = [
        '#type' => 'html_tag',
        '#tag' => 'rect',
        '#attributes' => [
          'x' => $attributes['x'],
          'y' => $attributes['y'],
          'width' => $attributes['width'],
          'height' => $attributes['height'],
          'fill' => $fill,
          'stroke' => $stroke,
          'stroke-width' => $stroke_width,
        ],
      ];
    }

    return $build;
  }

}
