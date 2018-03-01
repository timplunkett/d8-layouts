<?php

namespace Drupal\Core\Plugin;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * @todo.
 */
class PluginDefinitionRepository {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new PluginDefinitionRepository.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Gets the plugin definitions for a given type and sorts and filters them.
   *
   * @param string $type
   *   A string identifying the plugin type.
   * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $discovery
   *   The plugin discovery, usually the plugin manager.
   * @param Callable[] $filters
   *   (optional) An array of callables to filter the definitions.
   * @param mixed[] $context
   *   (optional) An associative array containing additional information
   *   provided by the code requesting the filtered definitins.
   *
   * @return \Drupal\Component\Plugin\Definition\PluginDefinitionInterface[]|array[]
   *   An array of plugin definitions that are sorted and filtered.
   */
  public function get($type, DiscoveryInterface $discovery, array $filters = [], array $context = []) {
    $this->moduleHandler->alter("plugin_filter_$type", $filters, $context);

    $definitions = $discovery->getDefinitions();
    foreach ($filters as $filter) {
      $definitions = call_user_func($filter, $definitions);
    }
    return $definitions;
  }

}
