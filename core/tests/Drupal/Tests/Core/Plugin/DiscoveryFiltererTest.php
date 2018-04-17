<?php

namespace Drupal\Tests\Core\Plugin;

use Drupal\Component\Plugin\Context\ContextInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\DiscoveryFilterer;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Plugin\DiscoveryFilterer
 *
 * @group Plugin
 */
class DiscoveryFiltererTest extends UnitTestCase {

  /**
   * @covers ::get
   */
  public function testGet() {
    // Start with two plugins.
    $definitions = [];
    $definitions['plugin1'] = ['id' => 'plugin1'];
    $definitions['plugin2'] = ['id' => 'plugin2'];

    // Define a single context.
    $context = $this->prophesize(ContextInterface::class);

    $type = 'the_type';
    $consumer = 'the_consumer';
    $contexts = ['context1' => $context->reveal()];
    $extra = ['foo' => 'bar'];

    // Only one plugin will remain after filtering.
    $expected = ['plugin1' => ['id' => 'plugin1']];

    $context_handler = $this->prophesize(ContextHandlerInterface::class);
    $context_handler->filterPluginDefinitionsByContexts(['context1' => $context->reveal()], $definitions)->willReturn($expected);

    // After context filtering, the alter hook will be invoked.
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $hooks = ["plugin_filter_{$type}", "plugin_filter_{$type}__{$consumer}"];
    $module_handler->alter($hooks, $expected, $extra, $consumer)->shouldBeCalled();

    $theme_manager = $this->prophesize(ThemeManagerInterface::class);
    $theme_manager->alter($hooks, $expected, $extra, $consumer)->shouldBeCalled();

    $discovery_filterer = new DiscoveryFilterer($module_handler->reveal(), $theme_manager->reveal(), $context_handler->reveal());
    $discovery = $this->prophesize(DiscoveryInterface::class);
    $discovery->getDefinitions()->willReturn($definitions);

    $result = $discovery_filterer->get('the_type', $consumer, $discovery->reveal(), $contexts, $extra);
    $this->assertSame($expected, $result);
  }

}
