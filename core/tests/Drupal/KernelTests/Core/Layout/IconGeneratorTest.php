<?php

namespace Drupal\KernelTests\Core\Layout;

use Drupal\Core\Layout\IconGenerator;
use Drupal\Core\Render\RenderContext;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\Core\Layout\IconGenerator
 * @group Layout
 */
class IconGeneratorTest extends KernelTestBase {

  /**
   * @covers ::generateSvgFromIconMap
   *
   * @dataProvider providerTestGenerateSvgFromIconMap
   */
  public function testGenerateSvgFromIconMap($icon_map, $expected) {
    $renderer = $this->container->get('renderer');
    $icon_generator = new IconGenerator();
    $build = $icon_generator->generateSvgFromIconMap($icon_map);
    $output = (string) $renderer->executeInRenderContext(new RenderContext(), function () use ($build, $renderer) {
      return $renderer->render($build);
    });
    $this->assertSame($expected, $output);
  }

  public function providerTestGenerateSvgFromIconMap() {
    $data = [];
    $data['empty'][] = [];
    $data['empty'][] = <<<'EOD'
<svg width="250" height="300"></svg>

EOD;

    $data['two_column'][] = [['left', 'right']];
    $data['two_column'][] = <<<'EOD'
<svg width="250" height="300"><g><title>left</title>
<rect x="0" y="0" width="120" height="295" fill="lightgray" stroke="black" stroke-width="2" />
</g>
<g><title>right</title>
<rect x="125" y="0" width="120" height="295" fill="lightgray" stroke="black" stroke-width="2" />
</g>
</svg>

EOD;

    $data['stacked'][] = [
      ['sidebar', 'top', 'top'],
      ['sidebar', 'left', 'right'],
      ['sidebar', 'middle', 'middle'],
      ['footer_left', 'footer_right'],
      ['footer_full'],
    ];
    $data['stacked'][] = <<<'EOD'
<svg width="250" height="300"><g><title>sidebar</title>
<rect x="0" y="0" width="78.333333333333" height="175" fill="lightgray" stroke="black" stroke-width="2" />
</g>
<g><title>top</title>
<rect x="83.333333333333" y="0" width="161.66666666667" height="55" fill="lightgray" stroke="black" stroke-width="2" />
</g>
<g><title>left</title>
<rect x="83.333333333333" y="60" width="78.333333333333" height="55" fill="lightgray" stroke="black" stroke-width="2" />
</g>
<g><title>right</title>
<rect x="166.66666666667" y="60" width="78.333333333333" height="55" fill="lightgray" stroke="black" stroke-width="2" />
</g>
<g><title>middle</title>
<rect x="83.333333333333" y="120" width="161.66666666667" height="55" fill="lightgray" stroke="black" stroke-width="2" />
</g>
<g><title>footer_left</title>
<rect x="0" y="180" width="120" height="55" fill="lightgray" stroke="black" stroke-width="2" />
</g>
<g><title>footer_right</title>
<rect x="125" y="180" width="120" height="55" fill="lightgray" stroke="black" stroke-width="2" />
</g>
<g><title>footer_full</title>
<rect x="0" y="240" width="245" height="55" fill="lightgray" stroke="black" stroke-width="2" />
</g>
</svg>

EOD;

    return $data;
  }

}
