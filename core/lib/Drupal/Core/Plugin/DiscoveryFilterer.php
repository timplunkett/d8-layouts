<?php

namespace Drupal\Core\Plugin;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\DiscoveryFilterInterface;

/**
 * Provides methods to retrieve filtered plugin definitions.
 *
 * This allows modules to alter plugin definitions, which is useful for tasks
 * like hiding definitions from user interfaces based on available contexts.
 *
 * @see \Drupal\Core\Plugin\Discovery\DiscoveryFilterInterface
 */
class DiscoveryFilterer {

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
   * An array of discovery filters.
   *
   * @var \Drupal\Core\Plugin\Discovery\DiscoveryFilterInterface[]
   */
  protected $filters = [];

  /**
   * Adds a discovery filter.
   *
   * @param \Drupal\Core\Plugin\Discovery\DiscoveryFilterInterface $filter
   *   A discovery filter.
   */
  public function addFilter(DiscoveryFilterInterface $filter) {
    $this->filters[] = $filter;
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
   * @param \Drupal\Core\Plugin\Discovery\DiscoveryFilterInterface[] $filters
   *   (optional) An array of discovery filters.
   * @param mixed[] $context
   *   (optional) An associative array containing additional information
   *   provided by the code requesting the filtered definitins.
   *
   * @return \Drupal\Component\Plugin\Definition\PluginDefinitionInterface[]|array[]
   *   An array of plugin definitions that are sorted and filtered.
   */
  public function get($type, $consumer, DiscoveryInterface $discovery, array $filters = [], array $context = []) {
    $definitions = $discovery->getDefinitions();

    /** @var \Drupal\Core\Plugin\Discovery\DiscoveryFilterInterface[] $filters */
    $filters = array_merge($filters, $this->filters);
    foreach ($filters as $filter) {
      $definitions = $filter->filter($type, $consumer, $definitions, $context);
    }
    return $definitions;
  }

}
