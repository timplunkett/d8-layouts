<?php

namespace Drupal\Core\Plugin;

use Drupal\Component\Plugin\CategorizingPluginManagerInterface;
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
   * @param string $consumer
   *   A string identifying the consumer of these plugin definitions.
   * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $discovery
   *   The plugin discovery, usually the plugin manager.
   * @param Callable[] $filters
   *   An array of callables to filter the definitions.
   * @param Callable[] $sorts
   *   An array of callables to sort the definitions.
   * @param bool $group
   *   (optional) Whether to group the definitions or not, if the discovery
   *   supports it. Defaults to FALSE.
   *
   * @return \Drupal\Component\Plugin\Definition\PluginDefinitionInterface[]|array[]
   *   An array of plugin definitions that are sorted and filtered.
   */
  public function get($type, $consumer, DiscoveryInterface $discovery, array $filters = [], array $sorts = [], $group = FALSE) {
    $definitions = $discovery->getDefinitions();
    $this->moduleHandler->alter("plugin_definition_$type", $filters, $sorts, $consumer);

    foreach ($sorts as $sort) {
      $definitions = call_user_func($sort, $definitions);
    }
    foreach ($filters as $filter) {
      $definitions = call_user_func($filter, $definitions);
    }

    if ($group && $discovery instanceof CategorizingPluginManagerInterface) {
      $definitions = $discovery->getGroupedDefinitions($definitions);
    }
    return $definitions;
  }

}
